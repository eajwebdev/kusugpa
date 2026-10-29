<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'filename', 'original_filename', 'file_path',
        'mime_type', 'file_size', 'document_type', 'description', 'uploaded_by'
    ];

    // Attachments live on the private disk (storage/app), out of reach of the web server;
    // uploads made before that were on the public disk until the move migration ran
    public const DISK = 'local';
    private const LEGACY_DISK = 'public';

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Full path of the stored file, or null when it is missing
     */
    public function absolutePath(): ?string
    {
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if ($this->file_path && Storage::disk($disk)->exists($this->file_path)) {
                return Storage::disk($disk)->path($this->file_path);
            }
        }
        return null;
    }

    public function deleteFile(): void
    {
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            Storage::disk($disk)->delete($this->file_path);
        }
    }

    /**
     * Original file name as an attachment / inline response header (no path separators, ASCII fallback)
     */
    public function sendAs($response, string $disposition)
    {
        $name = str_replace(['/', '\\'], '_', (string) $this->original_filename) ?: $this->filename;
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($name)) ?: 'attachment';

        return $response->setContentDisposition($disposition, $name, $fallback);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get temporary URL for viewing the file
     */
    public function getTemporaryUrl($minutes = 5)
    {
        return route('loans.attachment.view', [
            'attachment' => $this->id,
            'token' => $this->generateToken()
        ]);
    }

    /**
     * Generate a secure token for file access
     */
    public function generateToken()
    {
        $data = [
            'attachment_id' => $this->id,
            'loan_id' => $this->loan_id,
            'expires' => now()->addMinutes($minutes ?? 5)->timestamp,
        ];
        return Crypt::encrypt($data);
    }

    /**
     * Validate a token and return data or null
     */
    public static function validateToken($token)
    {
        try {
            $data = Crypt::decrypt($token);
            if (!isset($data['expires']) || $data['expires'] < now()->timestamp) {
                return null;
            }
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}