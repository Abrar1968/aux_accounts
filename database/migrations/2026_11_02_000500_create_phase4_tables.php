<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (docs/02 "new" requirements): bank accounts & reconciliation, budgets, recurring
 * expense templates, caller targets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('type', 20)->default('bank'); // bank | wallet | cash
            $t->string('account_number')->nullable();
            $t->json('aliases')->nullable(); // ledger `account` / `method` strings that map here
            $t->decimal('opening_balance', 15, 2)->default(0);
            $t->date('opening_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('bank_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $t->date('statement_date');
            $t->decimal('statement_balance', 15, 2);
            $t->decimal('book_balance', 15, 2);
            $t->decimal('difference', 15, 2);
            $t->string('status', 20); // matched | difference
            $t->text('notes')->nullable();
            $t->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['bank_account_id', 'statement_date']);
        });

        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->date('period');
            $t->string('line_key', 60); // FinancialStatementService::LINES key
            $t->decimal('amount', 15, 2);
            $t->timestamps();
            $t->unique(['period', 'line_key']);
        });

        Schema::create('recurring_expenses', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category');
            $t->string('subcategory')->nullable();
            $t->decimal('amount', 15, 2);
            $t->unsignedTinyInteger('day_of_month')->default(1);
            $t->date('start_month');
            $t->date('end_month')->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::table('operating_expenses', function (Blueprint $t) {
            $t->foreignId('recurring_expense_id')->nullable()->after('source_row')->constrained()->nullOnDelete();
            $t->unique(['recurring_expense_id', 'accrual_month']);
        });

        Schema::create('caller_targets', function (Blueprint $t) {
            $t->id();
            $t->string('owner'); // lead owner / caller name (matches leads.owner, lead_calls.caller)
            $t->date('period');
            $t->unsignedInteger('calls_target')->default(0);
            $t->unsignedInteger('leads_target')->default(0);
            $t->unsignedInteger('wins_target')->default(0);
            $t->timestamps();
            $t->unique(['owner', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caller_targets');
        Schema::table('operating_expenses', function (Blueprint $t) {
            $t->dropUnique(['recurring_expense_id', 'accrual_month']);
            $t->dropConstrainedForeignId('recurring_expense_id');
        });
        foreach (['recurring_expenses', 'budgets', 'bank_reconciliations', 'bank_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
