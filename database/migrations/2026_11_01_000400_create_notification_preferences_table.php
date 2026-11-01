<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per user, per notification type: which channels are on (docs/07-notifications-realtime.md).
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 60);
            $t->boolean('in_app')->default(true);
            $t->boolean('email')->default(false);
            $t->boolean('push')->default(true); // realtime toast via Pusher
            $t->timestamps();
            $t->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
