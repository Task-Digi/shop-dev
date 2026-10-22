<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('password');
            });
        }

        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('is_admin');
            });
        }

        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        // Remove the incomplete table left by a failed MySQL DDL migration.
        Schema::dropIfExists('service_user');
        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // The legacy users table is MyISAM, so MySQL cannot create a
            // foreign key to it. Application relations enforce this link.
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->unique(['service_id', 'user_id']);
        });

        DB::table('services')->updateOrInsert(
            ['slug' => 'orders'],
            ['name' => 'Orders', 'created_at' => now(), 'updated_at' => now()]
        );

        // Preserve access for accounts that existed before permissions were introduced.
        DB::table('users')->update(['is_admin' => true, 'is_active' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_user');
        Schema::dropIfExists('services');

        if (Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }

        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
