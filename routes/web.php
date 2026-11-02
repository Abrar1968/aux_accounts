<?php

use App\Http\Controllers\Accounting;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Crm;
use App\Http\Controllers\System;
use Illuminate\Support\Facades\Route;

/* Route map: docs/05-routes-and-pages.md */

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});

Route::get('/attachments/{attachment}', [System\AttachmentController::class, 'show'])->middleware('signed')->name('attachments.show');
// Letterhead images: logo is public (emails); seal is checked for a signed-in user inside the controller.
Route::get('/branding/{asset}.{format}', [System\BrandingController::class, 'show'])->whereIn('asset', ['logo', 'seal'])->whereIn('format', ['png', 'jpg'])->name('branding.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', Accounting\DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    // ---------------------------------------------------------------- Accounting
    Route::prefix('accounting')->group(function () {
        Route::middleware('permission:accounting.reports.view')->group(function () {
            Route::get('/statements/grid', [Accounting\StatementController::class, 'grid'])->name('statements.grid');
            Route::get('/statements/{month?}', [Accounting\StatementController::class, 'show'])->where('month', '\d{4}-\d{2}')->name('statements.show');
            Route::get('/controls', Accounting\ControlController::class)->name('controls');
        });

        Route::get('/projects', [Accounting\ProjectController::class, 'index'])->middleware('permission:accounting.projects.view')->name('projects.index');
        Route::get('/projects/{project}', [Accounting\ProjectController::class, 'show'])->middleware('permission:accounting.projects.view')->name('projects.show');
        Route::post('/projects', [Accounting\ProjectController::class, 'store'])->middleware('permission:accounting.projects.create')->name('projects.store');
        Route::put('/projects/{project}', [Accounting\ProjectController::class, 'update'])->middleware('permission:accounting.projects.update')->name('projects.update');
        Route::get('/projects/{project}/signature.{format}', [Accounting\ProjectController::class, 'signature'])->whereIn('format', ['png', 'jpg'])->middleware('permission:accounting.projects.view')->name('projects.signature');
        Route::post('/projects/{project}/signature', [Accounting\ProjectController::class, 'storeSignature'])->middleware('permission:accounting.projects.update')->name('projects.signature.store');
        Route::delete('/projects/{project}/signature', [Accounting\ProjectController::class, 'destroySignature'])->middleware('permission:accounting.projects.update')->name('projects.signature.destroy');
        Route::post('/projects/{project}/deliver', [Accounting\ProjectController::class, 'deliver'])->middleware('permission:accounting.projects.update')->name('projects.deliver');

        Route::get('/recognition', [Accounting\RecognitionController::class, 'index'])->middleware('permission:accounting.projects.view')->name('recognition.index');
        Route::post('/recognition', [Accounting\RecognitionController::class, 'store'])->middleware('permission:accounting.projects.update')->name('recognition.store');
        Route::put('/recognition/{recognition}', [Accounting\RecognitionController::class, 'update'])->middleware('permission:accounting.projects.update')->name('recognition.update');
        Route::delete('/recognition/{recognition}', [Accounting\RecognitionController::class, 'destroy'])->middleware('permission:accounting.projects.update')->name('recognition.destroy');

        Route::get('/payments', [Accounting\PaymentController::class, 'index'])->middleware('permission:accounting.payments.view')->name('payments.index');
        Route::post('/payments', [Accounting\PaymentController::class, 'store'])->middleware('permission:accounting.payments.create')->name('payments.store');
        Route::delete('/payments/{payment}', [Accounting\PaymentController::class, 'destroy'])->middleware('permission:accounting.payments.delete')->name('payments.destroy');
        Route::get('/receivables', Accounting\ReceivableController::class)->middleware('permission:accounting.payments.view')->name('receivables');

        Route::get('/marketing', [Accounting\MarketingController::class, 'index'])->middleware('permission:accounting.projects.view')->name('marketing.index');
        Route::post('/marketing', [Accounting\MarketingController::class, 'store'])->middleware('permission:accounting.projects.create')->name('marketing.store');
        Route::put('/marketing/{marketing}', [Accounting\MarketingController::class, 'update'])->middleware('permission:accounting.projects.update')->name('marketing.update');

        Route::get('/partnerships', [Accounting\PartnershipController::class, 'index'])->middleware('permission:accounting.projects.view')->name('partnerships.index');
        Route::post('/partnerships/collaborators', [Accounting\PartnershipController::class, 'storeCollaborator'])->middleware('permission:accounting.projects.create')->name('partnerships.collaborators.store');
        Route::post('/partnerships/assignments', [Accounting\PartnershipController::class, 'storeAssignment'])->middleware('permission:accounting.projects.create')->name('partnerships.assignments.store');
        Route::put('/partnerships/revenues/{revenue}', [Accounting\PartnershipController::class, 'setEarning'])->middleware('permission:accounting.projects.update')->name('partnerships.revenues.update');

        Route::get('/expenses', [Accounting\ExpenseController::class, 'index'])->middleware('permission:accounting.expenses.view')->name('expenses.index');
        Route::get('/expenses/{expense}', [Accounting\ExpenseController::class, 'show'])->middleware('permission:accounting.expenses.view')->name('expenses.show');
        Route::post('/expenses', [Accounting\ExpenseController::class, 'store'])->middleware('permission:accounting.expenses.create')->name('expenses.store');
        Route::put('/expenses/{expense}', [Accounting\ExpenseController::class, 'update'])->middleware('permission:accounting.expenses.update')->name('expenses.update');
        Route::post('/expenses/{expense}/payments', [Accounting\ExpenseController::class, 'pay'])->middleware('permission:accounting.expenses.pay')->name('expenses.pay');

        Route::get('/employees', [Accounting\EmployeeController::class, 'index'])->middleware('permission:accounting.payroll.view')->name('employees.index');
        Route::post('/employees', [Accounting\EmployeeController::class, 'store'])->middleware('permission:accounting.payroll.create')->name('employees.store');
        Route::put('/employees/{employee}', [Accounting\EmployeeController::class, 'update'])->middleware('permission:accounting.payroll.update')->name('employees.update');

        Route::get('/payroll', [Accounting\PayrollController::class, 'index'])->middleware('permission:accounting.payroll.view')->name('payroll.index');
        Route::post('/payroll', [Accounting\PayrollController::class, 'store'])->middleware('permission:accounting.payroll.create')->name('payroll.store');
        Route::post('/payroll/{month}/draft', [Accounting\PayrollController::class, 'draft'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.payroll.create')->name('payroll.draft');
        Route::post('/payroll/{payroll}/payments', [Accounting\PayrollController::class, 'pay'])->middleware('permission:accounting.payroll.pay')->name('payroll.pay');
        Route::get('/payroll/{payroll}/payslip', [Accounting\PayrollController::class, 'payslip'])->middleware('permission:accounting.payroll.view')->name('payroll.payslip');

        Route::get('/advances', [Accounting\AdvanceController::class, 'index'])->middleware('permission:accounting.payroll.view')->name('advances.index');
        Route::post('/advances', [Accounting\AdvanceController::class, 'store'])->middleware('permission:accounting.payroll.create')->name('advances.store');
        Route::get('/referrals', [Accounting\ReferralController::class, 'index'])->middleware('permission:accounting.payroll.view')->name('referrals.index');
        Route::put('/referrals/{referral}', [Accounting\ReferralController::class, 'update'])->middleware('permission:accounting.payroll.pay')->name('referrals.update');

        Route::get('/assets', [Accounting\AssetController::class, 'index'])->middleware('permission:accounting.ledger.view')->name('assets.index');
        Route::post('/assets', [Accounting\AssetController::class, 'store'])->middleware('permission:accounting.ledger.create')->name('assets.store');
        Route::put('/assets/{asset}', [Accounting\AssetController::class, 'update'])->middleware('permission:accounting.ledger.update')->name('assets.update');
        Route::get('/liabilities', [Accounting\LiabilityController::class, 'index'])->middleware('permission:accounting.ledger.view')->name('liabilities.index');
        Route::post('/liabilities', [Accounting\LiabilityController::class, 'store'])->middleware('permission:accounting.ledger.create')->name('liabilities.store');
        Route::get('/capital', [Accounting\CapitalController::class, 'index'])->middleware('permission:accounting.ledger.view')->name('capital.index');
        Route::post('/capital', [Accounting\CapitalController::class, 'store'])->middleware('permission:accounting.ledger.create')->name('capital.store');

        Route::get('/receivables/{type}/{id}/invoice', [Accounting\ReceivableController::class, 'invoice'])->whereIn('type', ['project', 'marketing', 'opening', 'partnership'])->middleware('permission:accounting.payments.view')->name('receivables.invoice');
        Route::get('/receivables/{type}/{id}/statement', [Accounting\ReceivableController::class, 'statement'])->whereIn('type', ['project', 'marketing', 'opening', 'partnership'])->middleware('permission:accounting.payments.view')->name('receivables.statement');

        Route::get('/banking', [Accounting\BankingController::class, 'index'])->middleware('permission:accounting.banking.view')->name('banking.index');
        Route::get('/banking/{account}', [Accounting\BankingController::class, 'show'])->middleware('permission:accounting.banking.view')->name('banking.show');
        Route::post('/banking', [Accounting\BankingController::class, 'store'])->middleware('permission:accounting.banking.manage')->name('banking.store');
        Route::put('/banking/{account}', [Accounting\BankingController::class, 'update'])->middleware('permission:accounting.banking.manage')->name('banking.update');
        Route::post('/banking/{account}/reconcile', [Accounting\BankingController::class, 'reconcile'])->middleware('permission:accounting.banking.reconcile')->name('banking.reconcile');

        Route::get('/budgets', [Accounting\BudgetController::class, 'index'])->middleware('permission:accounting.budgets.view')->name('budgets.index');
        Route::put('/budgets/{month}', [Accounting\BudgetController::class, 'update'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.budgets.manage')->name('budgets.update');
        Route::post('/budgets/{month}/copy', [Accounting\BudgetController::class, 'copy'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.budgets.manage')->name('budgets.copy');
        Route::get('/forecast', Accounting\ForecastController::class)->middleware('permission:accounting.reports.view')->name('forecast');

        Route::get('/recurring', [Accounting\RecurringExpenseController::class, 'index'])->middleware('permission:accounting.expenses.view')->name('recurring.index');
        Route::post('/recurring', [Accounting\RecurringExpenseController::class, 'store'])->middleware('permission:accounting.expenses.create')->name('recurring.store');
        Route::put('/recurring/{recurring}', [Accounting\RecurringExpenseController::class, 'update'])->middleware('permission:accounting.expenses.create')->name('recurring.update');
        Route::post('/recurring/generate/{month}', [Accounting\RecurringExpenseController::class, 'generate'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.expenses.create')->name('recurring.generate');

        Route::get('/periods', [Accounting\PeriodController::class, 'index'])->middleware('permission:accounting.reports.view')->name('periods.index');
        Route::post('/periods/{month}/close', [Accounting\PeriodController::class, 'close'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.periods.manage')->name('periods.close');
        Route::post('/periods/{month}/reopen', [Accounting\PeriodController::class, 'reopen'])->where('month', '\d{4}-\d{2}')->middleware('permission:accounting.periods.reopen')->name('periods.reopen');
    });

    // ---------------------------------------------------------------- CRM
    Route::prefix('crm')->group(function () {
        Route::middleware('permission:crm.leads.view')->group(function () {
            Route::get('/', Crm\TodayController::class)->name('crm.today');
            Route::get('/leads', [Crm\LeadController::class, 'index'])->name('leads.index');
            Route::get('/leads/{lead}', [Crm\LeadController::class, 'show'])->name('leads.show');
            Route::get('/pipeline', [Crm\PipelineController::class, 'index'])->name('pipeline.index');
            Route::get('/follow-ups', [Crm\FollowUpController::class, 'index'])->name('crm.follow-ups');
            Route::get('/meetings', [Crm\FollowUpController::class, 'meetings'])->name('crm.meetings');
        });
        Route::post('/leads', [Crm\LeadController::class, 'store'])->middleware('permission:crm.leads.create')->name('leads.store');
        Route::put('/leads/{lead}', [Crm\LeadController::class, 'update'])->middleware('permission:crm.leads.update')->name('leads.update');
        Route::delete('/leads/{lead}', [Crm\LeadController::class, 'destroy'])->middleware('permission:crm.leads.delete')->name('leads.destroy');
        Route::post('/leads/{lead}/calls', [Crm\LeadController::class, 'storeCall'])->middleware('permission:crm.calls.create')->name('leads.calls.store');
        Route::put('/leads/{lead}/calls-made', [Crm\LeadController::class, 'callsMade'])->middleware('permission:crm.calls.create')->name('leads.calls-made');
        Route::post('/leads/{lead}/convert', [Crm\LeadController::class, 'convert'])->middleware('permission:accounting.projects.create')->name('leads.convert');
        Route::put('/pipeline/{lead}', [Crm\PipelineController::class, 'move'])->middleware('permission:crm.leads.update')->name('pipeline.move');

        Route::middleware('permission:crm.reports.view')->group(function () {
            Route::get('/reports', [Crm\ReportController::class, 'index'])->name('crm.reports.index');
            Route::post('/reports', [Crm\ReportController::class, 'store'])->name('crm.reports.store');
            Route::get('/reports/{report}', [Crm\ReportController::class, 'show'])->name('crm.reports.show');
            Route::get('/analytics', [Crm\ReportController::class, 'analytics'])->name('crm.analytics');
        });
        Route::get('/leaderboard', [Crm\LeaderboardController::class, 'index'])->middleware('permission:crm.reports.view')->name('crm.leaderboard');
        Route::post('/leaderboard/targets', [Crm\LeaderboardController::class, 'target'])->middleware('permission:crm.targets.manage')->name('crm.targets');
        Route::middleware('permission:crm.leads.import')->group(function () {
            Route::get('/import', [Crm\ImportController::class, 'create'])->name('crm.import');
            Route::post('/import/preview', [Crm\ImportController::class, 'preview'])->name('crm.import.preview');
            Route::post('/import/commit', [Crm\ImportController::class, 'commit'])->name('crm.import.commit');
            Route::get('/import/template', [Crm\ImportController::class, 'template'])->name('crm.import.template');
        });
        Route::middleware('permission:crm.settings.manage')->group(function () {
            Route::post('/settings/outcomes', [Crm\CrmSettingController::class, 'saveOutcome'])->name('crm.settings.outcomes');
            Route::post('/settings/options', [Crm\CrmSettingController::class, 'addOption'])->name('crm.settings.options.store');
            Route::delete('/settings/options', [Crm\CrmSettingController::class, 'removeOption'])->name('crm.settings.options.destroy');
            Route::put('/settings/target', [Crm\CrmSettingController::class, 'target'])->name('crm.settings.target');
        });
    });

    // ---------------------------------------------------------------- System
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [System\SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [System\SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/branding', [System\BrandingController::class, 'store'])->name('branding.store');
        Route::delete('/settings/branding/{asset}', [System\BrandingController::class, 'destroy'])->whereIn('asset', ['logo', 'seal'])->name('branding.destroy');
        Route::get('/system/automation', [System\AutomationController::class, 'index'])->name('automation');
    });
    Route::middleware('permission:email.send')->group(function () {
        Route::get('/system/email', [System\EmailController::class, 'index'])->name('email.index');
        Route::get('/system/email/records', [System\EmailController::class, 'records'])->name('email.records');
        Route::post('/system/email/preview', [System\EmailController::class, 'preview'])->name('email.preview');
        Route::post('/system/email', [System\EmailController::class, 'send'])->middleware('throttle:30,1')->name('email.send');
    });
    Route::get('/system/audit', [System\AutomationController::class, 'audit'])->middleware('permission:audit.view')->name('audit');
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/system/users', [System\UserController::class, 'index'])->name('users.index');
        Route::post('/system/users', [System\UserController::class, 'store'])->name('users.store');
        Route::put('/system/users/{user}', [System\UserController::class, 'update'])->name('users.update');
        Route::get('/system/roles', [System\UserController::class, 'roles'])->name('roles.index');
        Route::put('/system/roles/{role}', [System\UserController::class, 'updateRole'])->name('roles.update');
    });

    Route::get('/notifications', [System\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [System\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [System\NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/profile', [System\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [System\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/notifications', [System\NotificationController::class, 'preferences'])->name('profile.notifications');
    Route::put('/profile/notifications', [System\NotificationController::class, 'savePreferences'])->name('profile.notifications.update');

    Route::post('/attachments', [System\AttachmentController::class, 'store'])->middleware('permission:attachments.create')->name('attachments.store');
    Route::get('/attachments/{attachment}/link', [System\AttachmentController::class, 'link'])->middleware('permission:attachments.view')->name('attachments.link');
    Route::delete('/attachments/{attachment}', [System\AttachmentController::class, 'destroy'])->middleware('permission:attachments.delete')->name('attachments.destroy');

    // JSON helpers (session + CSRF)
    Route::prefix('ajax')->group(function () {
        Route::get('/search', System\SearchController::class)->name('ajax.search');
        Route::get('/receivables', [Accounting\PaymentController::class, 'receivables'])->middleware('permission:accounting.payments.create')->name('ajax.receivables');
        Route::get('/leads/phone-check', [Crm\LeadController::class, 'phoneCheck'])->middleware('permission:crm.leads.create')->name('ajax.phone');
        Route::get('/notifications', [System\NotificationController::class, 'latest'])->name('ajax.notifications');
    });
});
