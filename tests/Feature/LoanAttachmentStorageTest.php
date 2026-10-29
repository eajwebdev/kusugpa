<?php

namespace Tests\Feature;

use App\Models\LoanAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SecurityTestSchema;
use Tests\TestCase;

/**
 * Loan attachments are kept off the public disk and only served through the token-checked routes
 */
class LoanAttachmentStorageTest extends TestCase
{
    use SecurityTestSchema;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private int $loanId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecuritySchema();
        Storage::fake('local');
        Storage::fake('public');

        foreach (['2026_05_10_093216_create_loans_table.php', '2026_05_10_140832_create_loan_attachments_table.php'] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        $admin = $this->makeUser('Administrator');
        $this->actingAs($admin);
        $typeId = DB::table('loan_types')->insertGetId(['name' => 'Crop Loan']);
        $this->loanId = DB::table('loans')->insertGetId([
            'loan_number' => 'LN-0001', 'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN', 'loan_type_id' => $typeId,
            'principal_amount' => 1000, 'interest_rate' => 0, 'term_months' => 1, 'monthly_amortization' => 1000,
            'total_amount' => 1000, 'balance' => 1000, 'status' => 'pending', 'application_date' => now(),
            'crop_year' => '20262027', 'created_by' => $admin->id,
        ]);
    }

    private function attachmentOn(string $disk): LoanAttachment
    {
        $path = 'protected/loan-attachments/' . $this->loanId . '/old.pdf';
        Storage::disk($disk)->put($path, self::PDF);

        return LoanAttachment::create([
            'loan_id' => $this->loanId, 'filename' => 'old.pdf', 'original_filename' => 'old.pdf', 'file_path' => $path,
            'mime_type' => 'application/pdf', 'file_size' => strlen(self::PDF), 'document_type' => 'id', 'uploaded_by' => auth()->id(),
        ]);
    }

    public function test_uploads_go_to_the_private_disk_named_by_their_content(): void
    {
        $file = UploadedFile::fake()->createWithContent("Juan's ID / \"front\".pdf", self::PDF);

        $this->postJson(route('loans.attachments.store', $this->loanId), ['document' => $file, 'document_type' => 'id'])->assertOk();

        $attachment = LoanAttachment::firstOrFail();
        $this->assertStringEndsWith('.pdf', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_view_and_download_send_a_safe_file_name(): void
    {
        $attachment = $this->attachmentOn('local');
        $attachment->update(['original_filename' => "Juan's ID / \"front\" ₱.pdf"]);

        $view = $this->get($attachment->getTemporaryUrl())->assertOk();
        $this->assertStringStartsWith('inline;', $view->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('/', $view->headers->get('Content-Disposition'));

        $download = $this->get(route('loans.attachment.download', ['attachment' => $attachment->id, 'token' => $attachment->generateToken()]))->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));

        $this->get(route('loans.attachment.view', ['attachment' => $attachment->id, 'token' => 'forged']))->assertForbidden();
    }

    public function test_older_public_uploads_still_open_and_the_migration_makes_them_private(): void
    {
        $attachment = $this->attachmentOn('public');
        $this->get($attachment->getTemporaryUrl())->assertOk();

        (require database_path('migrations/2026_10_28_000000_move_loan_attachments_to_private_disk.php'))->up();

        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertSame(self::PDF, Storage::disk('local')->get($attachment->file_path));
        $this->get($attachment->getTemporaryUrl())->assertOk();
    }
}
