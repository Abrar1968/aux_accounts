<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_outcomes', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('stage', 30);
            $t->unsignedTinyInteger('score')->default(0);
            $t->unsignedSmallInteger('followup_days')->default(0);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_options', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30); // service|source|owner|business_type|industry
            $t->string('value');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['type', 'value']);
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->date('lead_date');
            $t->string('phone');
            $t->string('normalized_phone', 20)->nullable()->index();
            $t->string('business_type')->nullable();
            $t->string('industry')->nullable();
            $t->string('service')->nullable();
            $t->string('source')->nullable();
            $t->string('outcome')->default('New');
            $t->string('stage', 30)->default('New');
            $t->unsignedTinyInteger('score')->default(0);
            $t->string('owner')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('quoted_price', 15, 2)->nullable();
            $t->dateTime('followup_override')->nullable();
            $t->dateTime('next_followup')->nullable()->index();
            $t->dateTime('meeting_at')->nullable();
            $t->string('meeting_details')->nullable();
            $t->dateTime('last_activity_at')->nullable();
            $t->date('closed_date')->nullable();
            $t->decimal('deal_value', 15, 2)->nullable();
            $t->string('lost_reason')->nullable();
            $t->unsignedInteger('calls_made')->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['stage', 'lead_date']);
        });

        Schema::create('lead_calls', function (Blueprint $t) {
            $t->id();
            $t->uuid('call_uuid')->unique();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->dateTime('recorded_at');
            $t->string('call_type', 30); // Follow-up|Initial|Count update|Count correction
            $t->string('outcome')->nullable();
            $t->string('caller')->nullable();
            $t->text('notes')->nullable();
            $t->dateTime('next_followup')->nullable();
            $t->string('status', 20)->default('Saved');
            $t->integer('count_change')->default(1);
            $t->string('entry_source', 40)->default('Call');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['lead_id', 'recorded_at']);
        });

        Schema::create('crm_reports', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10); // Daily|Monthly|Yearly
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 20); // CLOSED|IN_PROGRESS
            $t->string('hash', 64);
            $t->string('mode', 30)->default('Automatic');
            $t->json('payload');
            $t->timestamps();
            $t->unique(['type', 'period_start', 'period_end', 'hash']);
        });
    }

    public function down(): void
    {
        foreach (['crm_reports', 'lead_calls', 'leads', 'crm_options', 'lead_outcomes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
