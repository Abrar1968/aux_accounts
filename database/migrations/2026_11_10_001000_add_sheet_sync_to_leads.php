<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Google Sheets → Lead CRM sync bookkeeping: sheet row, content hash (skip unchanged rows), last sync. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->unsignedInteger('sheet_row')->nullable()->after('project_id');
            $t->char('sheet_hash', 40)->nullable()->after('sheet_row');
            $t->timestamp('sheet_synced_at')->nullable()->after('sheet_hash');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->dropColumn(['sheet_row', 'sheet_hash', 'sheet_synced_at']);
        });
    }
};
