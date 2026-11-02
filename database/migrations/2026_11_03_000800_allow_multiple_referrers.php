<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several people can share one project's referral: one referrals row per (project, referrer).
 * The composite unique is added first so MySQL keeps an index backing the project_id FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $t) {
            $t->unique(['project_id', 'referrer']);
        });
        Schema::table('referrals', function (Blueprint $t) {
            $t->dropUnique(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $t) {
            $t->unique('project_id');
        });
        Schema::table('referrals', function (Blueprint $t) {
            $t->dropUnique(['project_id', 'referrer']);
        });
    }
};
