<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A branch of the business. The tenant owns the catalogue, prices and
 * settings; each outlet holds its own stock, staff, shifts and takings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            // Short code printed on receipts and woven into invoice numbers
            // so two branches can never mint the same number.
            $table->string('code', 12);

            $table->text('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('phone', 40)->nullable();

            // Falls back to the tenant's own footer when left empty.
            $table->text('receipt_footer')->nullable();

            $table->boolean('is_active')->default(true);
            // The branch new operators and stock default to.
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Every existing tenant gets one outlet so nothing is left orphaned.
        foreach (DB::table('tenants')->get() as $tenant) {
            DB::table('outlets')->insert([
                'tenant_id' => $tenant->id,
                'name' => $tenant->name.' — Pusat',
                'code' => 'PST',
                'address' => $tenant->address,
                'city' => $tenant->city,
                'phone' => $tenant->phone,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
