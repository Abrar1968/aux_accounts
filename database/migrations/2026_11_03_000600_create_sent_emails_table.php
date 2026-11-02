<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Outbound email log for the admin composer (docs/16 §email). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_emails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('template', 40);
            $t->string('record_ref', 80)->nullable();
            $t->text('to');
            $t->text('cc')->nullable();
            $t->string('subject', 200);
            $t->json('content');
            $t->string('status', 10); // sent | failed
            $t->text('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
