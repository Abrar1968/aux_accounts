<?php

namespace Tests\Feature\Http;

use App\Models\Project;
use App\Models\User;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\InvoiceService;
use App\Services\Shared\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class BrandingInvoiceTest extends TestCase
{
    use SeedsWorkbook;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-11-02 10:00:00');
        $this->owner = User::query()->where('email', 'admin@auxtech.local')->firstOrFail();
        $this->actingAs($this->owner);
    }

    /** Real image bytes in any GD format. */
    private function image(string $ext): UploadedFile
    {
        $img = imagecreatetruecolor(300, 120);
        imagefill($img, 0, 0, imagecolorallocate($img, 20, 90, 200));
        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$ext;
        match ($ext) {
            'png' => imagepng($img, $path), 'jpg', 'jpeg' => imagejpeg($img, $path), 'gif' => imagegif($img, $path),
            'webp' => imagewebp($img, $path), 'bmp' => imagebmp($img, $path),
        };

        return new UploadedFile($path, "file.$ext", null, null, true);
    }

    public function test_logo_and_seal_upload_in_any_format_and_are_served(): void
    {
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'] as $ext) {
            $this->post('/settings/branding', ['asset' => 'logo', 'image' => $this->image($ext)])->assertSessionHas('toast.type', 'success');
        }
        $this->post('/settings/branding', ['asset' => 'seal', 'image' => $this->image('bmp')])->assertSessionHas('toast.type', 'success');
        app(SettingsService::class)->flush();
        $logo = app(SettingsService::class)->get('branding.logo');
        $this->assertSame(300, $logo['width']);
        $this->assertCount(6, Storage::disk('local')->allFiles('branding'), 'old logo files are deleted on replace (logo png/jpg/favicon/touch + seal png/jpg)');

        $this->get('/branding/logo.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $fav = $this->get('/branding/icon/favicon.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([64, 64], array_slice(getimagesizefromstring($fav->streamedContent() ?: file_get_contents($fav->baseResponse->getFile()->getPathname())), 0, 2), 'square favicon from the logo');
        $this->get('/branding/seal.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        auth()->logout();
        $this->get('/branding/logo.png')->assertOk(); // public, used in emails
        $this->get('/branding/seal.png')->assertForbidden();
        $this->get('/login')->assertSee('/branding/icon/favicon.png', false);
    }

    public function test_non_image_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('notes.txt', 3, 'text/plain');
        $this->post('/settings/branding', ['asset' => 'logo', 'image' => $file])->assertSessionHasErrors('image');
    }

    public function test_client_signature_on_project_only_documents(): void
    {
        $this->post('/accounting/projects', ['name' => 'Signed Shop', 'customer' => 'Signed Ltd', 'kind' => 'project', 'start_date' => '2026-11-01',
            'revenue_method' => 'Point in Time', 'base_value' => 50000, 'client_signature' => $this->image('gif')])->assertSessionHas('toast.type', 'success');
        $p = Project::query()->where('name', 'Signed Shop')->firstOrFail();
        $this->assertIsArray($p->client_signature);
        $this->get("/accounting/projects/{$p->id}/signature.jpg")->assertOk();

        $inv = app(InvoiceService::class)->invoice('project', $p->id);
        $this->assertSame('Signed Ltd', $inv['client_signature']['name']);
        $this->assertNull(app(InvoiceService::class)->invoice('opening', 1)['client_signature'], 'no client signature on non-project documents');

        $this->delete("/accounting/projects/{$p->id}/signature")->assertSessionHas('toast.type', 'success');
        $this->assertNull($p->fresh()->client_signature);
    }

    public function test_invoice_status_watermark_follows_payments(): void
    {
        $this->post('/accounting/projects', ['name' => 'Wm Shop', 'kind' => 'project', 'start_date' => '2026-11-01', 'revenue_method' => 'Point in Time', 'base_value' => 10000]);
        $p = Project::query()->where('name', 'Wm Shop')->firstOrFail();
        $svc = app(InvoiceService::class);
        $this->assertSame([InvoiceService::UNPAID, 10000.0], [$svc->invoice('project', $p->id)['status'], $svc->invoice('project', $p->id)['balance']]);

        $pay = fn (float $cash, float $fee = 0) => app(CustomerPaymentService::class)->record(['receivable_type' => 'project', 'receivable_id' => $p->id,
            'paid_at' => '2026-11-02 09:00:00', 'cash_received' => $cash, 'processing_fee' => $fee]);
        $pay(3925, 75);
        $inv = $svc->invoice('project', $p->id);
        $this->assertSame([InvoiceService::PARTIAL, 4000.0, 6000.0], [$inv['status'], $inv['paid'], $inv['balance']]);
        $pay(6000);
        $this->assertSame(InvoiceService::PAID, $svc->invoice('project', $p->id)['status']);

        $this->get("/accounting/receivables/project/{$p->id}/invoice")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Invoice')->where('invoice.status', 'PAID')->where('invoice.number', 'INV-'.$p->code));
        $this->get('/accounting/receivables/opening/1/statement')->assertOk()
            ->assertInertia(fn ($page) => $page->has('statement.status'));
    }
}
