<?php

namespace Tests\Feature\Commands;

use App\Models\AutomationRun;
use App\Models\OperatingExpense;
use App\Models\Period;
use App\Models\User;
use App\Notifications\ControlFailed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class AutomationCommandsTest extends TestCase
{
    use SeedsWorkbook;

    public function test_accounting_daily_creates_next_month_once_and_flags_missing_payroll(): void
    {
        Notification::fake();
        $this->travelTo('2026-12-03 02:10:00');
        $this->artisan('accounting:daily')->assertSuccessful();
        $this->assertTrue(Period::query()->where('month', '2026-12-01')->exists());
        $this->artisan('accounting:daily')->assertSuccessful();
        $this->assertSame(1, Period::query()->where('month', '2026-12-01')->count());
        $this->assertSame(2, AutomationRun::query()->where('command', 'accounting:daily')->where('status', 'ok')->count());
        Notification::assertSentTo(User::query()->where('email', 'admin@auxtech.local')->first(), ControlFailed::class,
            fn ($n) => str_contains($n->message, 'Employees missing a payroll row'));
    }

    public function test_crm_and_reminder_commands_run(): void
    {
        $this->travelTo('2026-11-02 08:30:00');
        $this->artisan('crm:safety-check')->assertSuccessful();
        $this->artisan('notify:due')->assertSuccessful();
        $this->artisan('crm:digest')->assertSuccessful();
        $this->artisan('notify:period-close')->assertSuccessful();
        $this->assertSame(0, AutomationRun::query()->where('status', 'error')->where('started_at', '>=', '2026-11-02')->count());
    }

    public function test_backup_writes_gzip_sql(): void
    {
        Storage::fake('local');
        $this->artisan('backup:database')->assertSuccessful();
        $file = Storage::disk('local')->files('backups')[0];
        $sql = gzdecode(Storage::disk('local')->get($file));
        $this->assertStringContainsString('INSERT INTO `customer_payments`', $sql);
    }

    public function test_gd_image_upload_creates_resized_image_and_thumbnail(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $expense = OperatingExpense::query()->first();
        $file = UploadedFile::fake()->image('receipt.jpg', 3000, 2000);
        $this->actingAs($owner)->post('/attachments', ['attachable_type' => 'expense', 'attachable_id' => $expense->id, 'file' => $file])
            ->assertSessionHas('toast.type', 'success');
        $a = $expense->attachments()->first();
        $this->assertSame(1600, $a->width);
        $this->assertSame(1067, $a->height);
        $this->assertSame([320, 213], array_slice(getimagesizefromstring(Storage::disk('local')->get($a->thumb_path)), 0, 2));

        $url = $this->actingAs($owner)->getJson("/attachments/{$a->id}/link")->json('url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/attachments/{$a->id}")->assertForbidden(); // unsigned

        $bad = UploadedFile::fake()->create('script.php', 10, 'text/x-php');
        $this->actingAs($owner)->post('/attachments', ['attachable_type' => 'expense', 'attachable_id' => $expense->id, 'file' => $bad])->assertSessionHasErrors('file');
    }
}
