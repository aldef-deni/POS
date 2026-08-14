<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock is held per outlet, not per product.
 *
 * The product row stays the single catalogue entry (name, price, barcode);
 * this table answers "how many of it does *this branch* have", which is what
 * stops one outlet selling another outlet's inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->decimal('stock', 15, 3)->default(0);
            // Reorder point for this branch; seeded from the product default.
            $table->decimal('min_stock', 15, 3)->default(0);

            $table->timestamps();

            $table->unique(['outlet_id', 'product_id']);
            $table->index(['tenant_id', 'outlet_id']);
            $table->index('product_id');
        });

        // Move whatever stock products already carry into their tenant's
        // default outlet, so no quantity is lost by the upgrade.
        $defaults = DB::table('outlets')
            ->where('is_default', true)
            ->pluck('id', 'tenant_id');

        $rows = [];

        foreach (DB::table('products')->get() as $product) {
            $outletId = $defaults[$product->tenant_id] ?? null;

            if (! $outletId) {
                continue;
            }

            $rows[] = [
                'tenant_id' => $product->tenant_id,
                'outlet_id' => $outletId,
                'product_id' => $product->id,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('outlet_stocks')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_stocks');
    }
};
