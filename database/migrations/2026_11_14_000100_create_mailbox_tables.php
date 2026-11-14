<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox (new feature): messages read from the company mailbox over IMAP, threaded with the emails
 * sent from the app (Message-ID / In-Reply-To), plus their attachments. Grants email.inbox to
 * every role that can send email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_emails', function (Blueprint $t) {
            $t->string('message_id', 255)->nullable()->after('subject')->index();
            $t->foreignId('thread_root_id')->nullable()->after('message_id')->constrained('sent_emails')->nullOnDelete();
            $t->unsignedBigInteger('reply_to_mail_id')->nullable()->after('thread_root_id')->index();
        });

        Schema::create('mail_messages', function (Blueprint $t) {
            $t->id();
            $t->string('folder', 60)->default('INBOX');
            $t->unsignedBigInteger('uidvalidity');
            $t->unsignedBigInteger('uid');
            $t->string('message_id', 255)->nullable()->index();
            $t->string('in_reply_to', 255)->nullable()->index();
            $t->text('references')->nullable();
            $t->string('from_email', 190)->index();
            $t->string('from_name', 190)->nullable();
            $t->text('to')->nullable();
            $t->text('cc')->nullable();
            $t->string('subject', 500)->default('');
            $t->timestamp('sent_at')->nullable()->index();
            $t->string('snippet', 300)->default('');
            $t->longText('text_body')->nullable();
            $t->longText('html_body')->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->boolean('has_attachments')->default(false);
            $t->foreignId('sent_email_id')->nullable()->constrained('sent_emails')->nullOnDelete();
            $t->timestamp('read_at')->nullable()->index();
            $t->timestamp('replied_at')->nullable();
            $t->timestamp('archived_at')->nullable()->index();
            $t->timestamps();
            $t->unique(['folder', 'uidvalidity', 'uid']);
        });

        Schema::create('mail_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_message_id')->constrained('mail_messages')->cascadeOnDelete();
            $t->string('filename', 255);
            $t->string('mime', 120)->default('application/octet-stream');
            $t->unsignedInteger('size')->default(0);
            $t->string('content_id', 255)->nullable();
            $t->boolean('inline')->default(false);
            $t->string('path', 255);
            $t->timestamps();
        });

        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $perms = json_decode((string) $role->permissions, true) ?: [];
            if (in_array('email.send', $perms, true) && ! in_array('email.inbox', $perms, true)) {
                $perms[] = 'email.inbox';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($perms))]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_messages');
        Schema::table('sent_emails', function (Blueprint $t) {
            $t->dropConstrainedForeignId('thread_root_id');
            $t->dropColumn(['message_id', 'reply_to_mail_id']);
        });
    }
};
