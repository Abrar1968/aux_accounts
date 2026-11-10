<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Roles store explicit permission lists (tick boxes in Users & roles) instead of "*" / "crm.*" wildcards. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $granted = json_decode((string) $role->permissions, true) ?: [];
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(PermissionCatalog::expand($granted))]);
        }
    }

    public function down(): void
    {
        // explicit lists keep working with the wildcard-aware permission check
    }
};
