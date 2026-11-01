<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('role_label')->nullable();
            $t->boolean('is_equity')->default(true);
            $t->decimal('capital_commitment', 15, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('investor_ownerships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investor_id')->constrained()->cascadeOnDelete();
            $t->date('effective_from');
            $t->decimal('percent', 7, 4);
            $t->timestamps();
            $t->unique(['investor_id', 'effective_from']);
        });

        Schema::create('capital_transactions', function (Blueprint $t) {
            $t->id();
            $t->date('txn_date');
            $t->date('period');
            $t->foreignId('investor_id')->constrained();
            $t->string('type');
            $t->decimal('cash_impact', 15, 2)->default(0);
            $t->decimal('equity_impact', 15, 2)->default(0);
            $t->string('counterparty')->nullable();
            $t->string('ownership_impact')->nullable();
            $t->string('source_status')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('period');
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('customer')->nullable();
            $t->string('kind', 30)->default('project'); // project|international|lead
            $t->date('source_month')->nullable();
            $t->date('start_date')->nullable();
            $t->date('est_delivery_date')->nullable();
            $t->date('actual_delivery_date')->nullable();
            $t->string('revenue_method', 60);
            $t->decimal('base_value', 15, 2)->default(0);
            $t->decimal('change_orders', 15, 2)->default(0);
            $t->string('referrer')->nullable();
            $t->string('previous_status')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['kind', 'source_month']);
        });

        Schema::create('revenue_recognitions', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('period');
            $t->foreignId('project_id')->constrained();
            $t->string('method_label')->nullable();
            $t->decimal('opening_progress', 7, 4)->nullable();
            $t->decimal('ending_progress', 7, 4)->nullable();
            $t->decimal('contract_value', 15, 2)->default(0);
            $t->decimal('auto_revenue', 15, 2)->default(0);
            $t->decimal('override_amount', 15, 2)->nullable();
            $t->decimal('recognized_amount', 15, 2)->default(0);
            $t->decimal('siam_eligible', 15, 2)->default(0);
            $t->decimal('siam_share', 15, 2)->default(0);
            $t->date('recognition_month');
            $t->string('entry_check')->default('OK');
            $t->boolean('is_historical')->default(false);
            $t->boolean('auto_generated')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['recognition_month']);
        });

        Schema::create('opening_receivables', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('customer');
            $t->decimal('opening_amount', 15, 2);
            $t->string('details')->nullable();
            $t->string('status')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('marketing_services', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('service_month');
            $t->string('client');
            $t->decimal('revenue', 15, 2);
            $t->string('source')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('service_month');
        });

        Schema::create('collaborators', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('status', 20)->default('Draft');
            $t->date('agreement_start')->nullable();
            $t->date('agreement_end')->nullable();
            $t->string('contact')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('partnership_packages', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('status', 20)->default('Active');
            $t->string('description')->nullable();
            $t->string('accounting_note')->nullable();
            $t->timestamps();
        });

        Schema::create('partnership_assignments', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('collaborator_id')->constrained();
            $t->foreignId('package_id')->constrained('partnership_packages');
            $t->date('effective_from')->nullable();
            $t->date('effective_through')->nullable();
            $t->string('status', 20)->default('Draft');
            $t->string('earning_method', 30)->default('Direct Amount'); // Direct Amount|Share of Sales
            $t->decimal('share_percent', 7, 4)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('partnership_revenues', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('earned_month');
            $t->foreignId('assignment_id')->constrained('partnership_assignments');
            $t->string('earning_method', 30);
            $t->decimal('share_percent', 7, 4)->nullable();
            $t->decimal('gross_sales', 15, 2)->nullable();
            $t->decimal('direct_amount', 15, 2)->nullable();
            $t->decimal('recognized_revenue', 15, 2)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['assignment_id', 'earned_month']);
        });

        Schema::create('customer_payments', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->dateTime('paid_at');
            $t->date('period');
            $t->string('receivable_type'); // project|marketing|opening|partnership
            $t->unsignedBigInteger('receivable_id');
            $t->string('revenue_code');
            $t->string('source_name')->nullable();
            $t->string('canonical_name')->nullable();
            $t->decimal('cash_received', 15, 2);
            $t->decimal('processing_fee', 15, 2)->default(0);
            $t->string('method')->nullable();
            $t->string('account')->nullable();
            $t->string('source_workbook')->nullable();
            $t->uuid('request_id')->nullable()->unique();
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['receivable_type', 'receivable_id']);
            $t->index('revenue_code');
            $t->index('paid_at');
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('statement_line')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('operating_expenses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('accrual_period')->nullable();
            $t->dateTime('expense_date')->nullable();
            $t->date('accrual_month');
            $t->string('category');
            $t->string('subcategory')->nullable();
            $t->string('description')->nullable();
            $t->decimal('amount', 15, 2);
            $t->string('source_kind')->default('Expense');
            $t->string('source_workbook')->nullable();
            $t->string('source_sheet')->nullable();
            $t->string('source_row')->nullable();
            $t->json('legacy')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['accrual_month', 'category']);
        });

        Schema::create('expense_payments', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('paid_on');
            $t->foreignId('operating_expense_id')->constrained();
            $t->decimal('amount', 15, 2);
            $t->string('method')->nullable();
            $t->string('account')->nullable();
            $t->text('notes')->nullable();
            $t->string('origin')->default('Recorded from expense');
            $t->string('request_id')->nullable()->unique();
            $t->timestamp('posted_at')->nullable();
            $t->string('posted_by')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('expense_payments');
            $t->timestamps();
            $t->index('paid_on');
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('slug')->unique();
            $t->string('designation')->nullable();
            $t->decimal('base_salary', 15, 2)->nullable();
            $t->date('joined_on')->nullable();
            $t->date('left_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('phone')->nullable();
            $t->string('bank_account')->nullable();
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('period');
            $t->foreignId('employee_id')->constrained();
            $t->decimal('gross_salary', 15, 2);
            $t->decimal('source_net_paid', 15, 2)->nullable();
            $t->decimal('legacy_referral_snapshot', 15, 2)->default(0);
            $t->decimal('legacy_unpaid_snapshot', 15, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'period']);
            $t->index('period');
        });

        Schema::create('salary_payments', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('paid_on');
            $t->foreignId('payroll_id')->constrained();
            $t->decimal('amount', 15, 2);
            $t->string('method')->nullable();
            $t->string('account')->nullable();
            $t->text('notes')->nullable();
            $t->string('origin')->default('Recorded from salary');
            $t->string('request_id')->nullable()->unique();
            $t->timestamp('posted_at')->nullable();
            $t->string('posted_by')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('salary_payments');
            $t->timestamps();
            $t->index('paid_on');
        });

        Schema::create('employee_advances', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('txn_date');
            $t->foreignId('employee_id')->constrained();
            $t->string('type', 20); // Disbursement|Recovery
            $t->decimal('amount', 15, 2);
            $t->date('related_salary_period')->nullable();
            $t->foreignId('payroll_id')->nullable()->constrained();
            $t->string('source_workbook')->nullable();
            $t->string('source_detail')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['type', 'txn_date']);
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('project_id')->unique()->constrained();
            $t->string('referrer');
            $t->decimal('memo_amount', 15, 2)->default(0);
            $t->date('paid_on')->nullable();
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('paid_on');
        });

        Schema::create('fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('category')->nullable();
            $t->date('acquisition_date')->nullable();
            $t->date('in_service_date')->nullable();
            $t->decimal('cost', 15, 2);
            $t->decimal('residual_value', 15, 2)->default(0);
            $t->unsignedSmallInteger('useful_life_months');
            $t->decimal('opening_accumulated_dep', 15, 2)->default(0);
            $t->date('disposal_date')->nullable();
            $t->decimal('cash_paid', 15, 2)->default(0);
            $t->date('cash_payment_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('liabilities', function (Blueprint $t) {
            $t->id();
            $t->date('txn_date');
            $t->date('period');
            $t->string('liability_code', 40);
            $t->string('liability_type');
            $t->string('description')->nullable();
            $t->decimal('increase', 15, 2)->default(0);
            $t->decimal('decrease', 15, 2)->default(0);
            $t->decimal('cash_impact', 15, 2)->default(0);
            $t->string('counterparty')->nullable();
            $t->string('source_status')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['liability_code', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['liabilities', 'fixed_assets', 'referrals', 'employee_advances', 'salary_payments', 'payrolls',
            'employees', 'expense_payments', 'operating_expenses', 'expense_categories', 'customer_payments',
            'partnership_revenues', 'partnership_assignments', 'partnership_packages', 'collaborators',
            'marketing_services', 'opening_receivables', 'revenue_recognitions', 'projects',
            'capital_transactions', 'investor_ownerships', 'investors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
