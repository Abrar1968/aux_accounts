<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('label');
            $t->json('permissions');
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $t->string('avatar_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group')->default('general');
            $t->string('key')->unique();
            $t->json('value')->nullable();
            $t->string('label')->nullable();
            $t->boolean('editable')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('periods', function (Blueprint $t) {
            $t->id();
            $t->date('month')->unique();
            $t->string('status', 20)->default('open'); // historical|open|closed
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('sequences', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->unsignedBigInteger('value')->default(0);
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->morphs('attachable');
            $t->string('disk')->default('local');
            $t->string('path');
            $t->string('thumb_path')->nullable();
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedInteger('size');
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $t) {
            $t->id();
            $t->string('command');
            $t->string('status', 20); // ok|error
            $t->json('summary')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->index(['command', 'started_at']);
        });

        Schema::create('source_aliases', function (Blueprint $t) {
            $t->id();
            $t->string('alias');
            $t->string('canonical');
            $t->string('type')->nullable();
            $t->string('decision')->nullable();
            $t->boolean('confirmed')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['source_aliases', 'automation_runs', 'attachments', 'audit_logs', 'sequences', 'periods', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('role_id');
            $t->dropColumn(['avatar_path', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('roles');
    }
};
