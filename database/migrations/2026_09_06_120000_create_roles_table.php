<?php

use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // NULL company_id => a built-in system role shared by every tenant.
            $table->string('company_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->boolean('is_system')->default(false)->index();
            $table->text('description')->nullable();
            // { "<module>": ["<action>", ...], ... } — same module/action
            // vocabulary as App\Services\Auth\PermissionChecker::MODULE_ACTIONS.
            $table->json('permissions')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'slug']);
        });

        // Seed the built-in roles as shared system rows so the roles-management
        // UI and staff invite dropdown can list them alongside custom roles,
        // and PermissionChecker resolves everyone through the same path.
        $now = now();
        foreach (User::ROLES as $slug => $name) {
            DB::table('roles')->updateOrInsert(
                ['company_id' => null, 'slug' => $slug],
                [
                    'name' => $name,
                    'is_system' => true,
                    'description' => 'Built-in '.$name.' role.',
                    'permissions' => json_encode(PermissionChecker::getRoleDefaults($slug)),
                    'is_demo' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
