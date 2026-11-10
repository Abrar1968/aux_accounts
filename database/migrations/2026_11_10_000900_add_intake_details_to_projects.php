<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project Intake Form fields: client contact, domain / hosting responsibility, discount and the
 * assigned project manager. Existing rows keep discount 0, so every historical statement is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->string('client_email', 190)->nullable()->after('customer');
            $t->string('client_phone', 40)->nullable()->after('client_email');
            $t->string('client_address', 255)->nullable()->after('client_phone');
            $t->string('domain', 190)->nullable()->after('client_address');
            $t->string('domain_provided_by', 20)->nullable()->after('domain');   // client | auxtech | not_needed
            $t->string('hosting_provided_by', 20)->nullable()->after('domain_provided_by');
            $t->decimal('discount', 15, 2)->default(0)->after('change_orders');
            $t->string('project_manager', 120)->nullable()->after('referrer');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropColumn(['client_email', 'client_phone', 'client_address', 'domain', 'domain_provided_by', 'hosting_provided_by', 'discount', 'project_manager']);
        });
    }
};
