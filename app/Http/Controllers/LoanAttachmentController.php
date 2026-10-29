<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use App\Models\LoanAttachment;
use App\Models\User;
use Illuminate\Support\Str;

class LoanAttachmentController extends Controller
{
    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function currentRole(): string
    {
        return $this->user()->role ?? '';
    }

    private function canProcessLoans(): bool
    {
        return in_array($this->currentRole(), ['Administrator', 'super_admin', 'manager', 'loan_officer']);
    }

    public function store(Request $request, Loan $loan)
{
    if (!$this->canProcessLoans()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $request->validate([
        'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        'document_type' => 'required|in:proof,agreement,id,other',
        'description' => 'nullable|string|max:255',
    ]);

    $file = $request->file('document');
    // Extension from the file's content (what "mimes" checked), never the name the browser sent, e.g. "id.php"
    $extension = $file->extension() ?: 'bin';
    $encryptedFilename = Str::uuid() . '_' . time() . '.' . $extension;

    // Private disk: served only through the token-checked view / download routes
    $path = $file->storeAs(
        'protected/loan-attachments/' . $loan->id,
        $encryptedFilename,
        LoanAttachment::DISK
    );

    $attachment = LoanAttachment::create([
        'loan_id' => $loan->id,
        'filename' => $encryptedFilename,
        'original_filename' => $file->getClientOriginalName(),
        'file_path' => $path,
        'mime_type' => $file->getMimeType(),
        'file_size' => $file->getSize(),
        'document_type' => $request->document_type,
        'description' => $request->description,
        'uploaded_by' => auth()->id(),
    ]);

    return response()->json([
        'message' => 'Document uploaded successfully',
        'attachment' => $attachment
    ]);
}

public function view(Request $request, LoanAttachment $attachment)
{
    $token = $request->get('token');
    $data = LoanAttachment::validateToken($token);

    if (!$data || $data['attachment_id'] != $attachment->id) {
        abort(403, 'Invalid or expired link.');
    }

    $filePath = $attachment->absolutePath();

    if (!$filePath) {
        abort(404, 'File not found.');
    }

    return $attachment->sendAs(response()->file($filePath, ['Content-Type' => $attachment->mime_type]), 'inline');
}

    public function download(Request $request, LoanAttachment $attachment)
    {
        $token = $request->get('token');
        $data = LoanAttachment::validateToken($token);

        if (!$data || $data['attachment_id'] != $attachment->id) {
            abort(403, 'Invalid or expired link.');
        }

        $filePath = $attachment->absolutePath();

        if (!$filePath) {
            abort(404, 'File not found.');
        }

        return $attachment->sendAs(response()->download($filePath), 'attachment');
    }

    public function destroy(LoanAttachment $attachment)
    {
        if (!$this->canProcessLoans()) {
            abort(403, 'Unauthorized');
        }

        if ($attachment->loan->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete attachments for processed loans.'
            ], 403);
        }

        $attachment->deleteFile();
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }

    public function index(Loan $loan)
    {
        $attachments = $loan->attachments()->with('uploader')->get()->map(function($att) {
            return [
                'id' => $att->id,
                'original_filename' => $att->original_filename,
                'document_type' => $att->document_type,
                'file_size_formatted' => $att->file_size_formatted,
                'mime_type' => $att->mime_type,
                'description' => $att->description,
                'view_url' => $att->getTemporaryUrl(),
                'download_url' => route('loans.attachment.download', [
                    'attachment' => $att->id, 
                    'token' => $att->generateToken()
                ]),
                'uploaded_by' => $att->uploader ? $att->uploader->fname . ' ' . $att->uploader->lname : 'Unknown',
                'created_at' => $att->created_at->format('M d, Y H:i'),
            ];
        });

        return response()->json(['attachments' => $attachments]);
    }
}
