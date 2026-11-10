<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Emails are signed "Minhazur Rahman, Chief Operating Officer, Auxtech" (company name "Auxtech", not "Auxtech Software"). */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'company.name' => 'Auxtech',
            'company.signature_name' => 'Minhazur Rahman',
            'company.signature_title' => 'Chief Operating Officer',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode(['v' => $value]), 'group' => 'company', 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        // keep the signature
    }
};
