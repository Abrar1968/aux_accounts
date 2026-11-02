<?php

namespace Tests\Feature\Http;

use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CrmReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Every route is protected, and no page errors for any role (403 is fine, 500 is not). */
class RouteSecurityTest extends TestCase
{
    use SeedsWorkbook;

    /** Routes that are intentionally reachable without login. */
    private const PUBLIC = ['login', 'password.request', 'password.email', 'password.reset', 'password.update', 'attachments.show', 'storage.local', 'storage.local.upload', 'branding.show'];

    /** Authenticated routes every signed-in user may use (own profile / notifications / search). */
    private const ANY_USER = ['logout', 'notifications.index', 'notifications.read-all', 'notifications.read', 'profile.edit', 'profile.update',
        'profile.notifications', 'profile.notifications.update', 'ajax.search', 'ajax.notifications'];

    public function test_every_route_requires_auth_and_a_permission(): void
    {
        $unprotected = [];
        foreach (Route::getRoutes() as $r) {
            $name = $r->getName();
            $uri = $r->uri();
            if ($uri === 'up' || $uri === 'login' || str_starts_with($uri, 'broadcasting') || str_starts_with($uri, '_ignition') || in_array($name, self::PUBLIC, true)) {
                continue;
            }
            $mw = implode('|', $r->gatherMiddleware());
            if (! str_contains($mw, 'auth')) {
                $unprotected[] = "$uri (no auth)";
            } elseif (! str_contains($mw, 'permission:') && ! in_array($name, self::ANY_USER, true)) {
                $unprotected[] = "$uri (no permission)";
            }
        }
        $this->assertSame([], $unprotected);
    }

    public function test_no_get_page_errors_for_any_role(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $report = app(CrmReportService::class)->snapshot('Monthly', '2026-10-31');
        // real ids, never literals: MySQL/MariaDB keep auto-increment counters across rolled-back test transactions
        $first = fn (string $table) => (int) DB::table($table)->min('id');
        $params = ['project' => $first('projects'), 'expense' => $first('operating_expenses'), 'payroll' => $first('payrolls'), 'lead' => $first('leads'),
            'report' => $report->id, 'account' => $first('bank_accounts'), 'type' => 'opening', 'id' => $first('opening_receivables'),
            'month' => '2026-10', 'token' => 'x'];
        $failures = [];
        foreach (Role::query()->pluck('name') as $role) {
            $user = $this->userWithRole($role, ucfirst($role).' Tester');
            foreach (Route::getRoutes() as $r) {
                if (! in_array('GET', $r->methods(), true) || in_array($r->getName(), self::PUBLIC, true) || in_array($r->getName(), ['logout'], true)
                    || str_starts_with($r->uri(), 'broadcasting') || str_starts_with($r->uri(), 'storage') || $r->uri() === 'up' || in_array($r->getName(), ['attachments.link', 'projects.signature'], true)) { // link needs an existing upload (covered in AutomationCommandsTest)
                    continue;
                }
                $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($params[$m[1]] ?? ''), $r->uri());
                $status = $this->actingAs($user)->get('/'.ltrim($uri, '/'))->getStatusCode();
                if (! in_array($status, [200, 302, 403], true)) {
                    $failures[] = "$role GET /$uri → $status";
                }
            }
        }
        $this->assertSame([], $failures);
    }
}
