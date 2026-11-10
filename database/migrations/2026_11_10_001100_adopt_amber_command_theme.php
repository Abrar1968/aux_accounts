<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Command glass" redesign: amber is the new house accent. Drop any accent saved under the old
 * design so every install switches to amber; Settings → Branding can still pick another palette.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'branding.accent')->delete();
    }

    public function down(): void
    {
        // nothing to restore — the previous default (blue) applied when no row existed
    }
};
