<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps the branch onto every operational record.
 *
 * `users.outlet_id` is nullable on purpose: an Owner may oversee all
 * branches. For Kasir and Supervisor the application requires it, which is
 * what stops an operator being registered against the wrong outlet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('tenant_id')
                    ->constrained('outlets')->nullOnDelete();
            }
        });

        foreach (['sales', 'shifts', 'stock_movements', 'held_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'outlet_id')) {
                    $table->foreignId('outlet_id')->nullable()->after('tenant_id')
                        ->constrained('outlets')->nullOnDelete();
                    $table->index(['outlet_id', 'created_at']);
                }
            });
        }

        // Existing records all belong to the tenant's single default outlet.
        $defaults = DB::table('outlets')->where('is_default', true)->pluck('id', 'tenant_id');

        foreach ($defaults as $tenantId => $outletId) {
            foreach (['users', 'sales', 'shifts', 'stock_movements', 'held_orders'] as $name) {
                DB::table($name)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('outlet_id')
                    ->update(['outlet_id' => $outletId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'sales', 'shifts', 'stock_movements', 'held_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'outlet_id')) {
                    $table->dropConstrainedForeignId('outlet_id');
                }
            });
        }
    }
};
