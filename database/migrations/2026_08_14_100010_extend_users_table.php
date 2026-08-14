<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds POS-specific columns to Laravel's stock users table. Each column is
 * guarded with hasColumn so the migration is safe to run against a database
 * that already received part of the earlier starter schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['Owner', 'Supervisor', 'Kasir'])
                    ->default('Kasir')->after('email');
            }

            if (! Schema::hasColumn('users', 'username')) {
                // Cashiers sign in at the terminal with a short username
                // rather than a full email address.
                $table->string('username', 60)->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'pos_pin')) {
                // Hashed 4-6 digit PIN for the fast terminal keypad login.
                $table->string('pos_pin', 191)->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 40)->nullable()->after('pos_pin');
            }

            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 191)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('avatar_path');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('users', 'last_pos_login_at')) {
                $table->timestamp('last_pos_login_at')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }

            foreach ([
                'role', 'username', 'pos_pin', 'phone', 'avatar_path',
                'is_active', 'last_login_at', 'last_pos_login_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
