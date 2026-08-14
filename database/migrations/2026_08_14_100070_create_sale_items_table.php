<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Name / SKU / cost are snapshotted so a receipt reprinted years
            // later still shows exactly what was sold at that price.
            $table->string('sku', 64)->nullable();
            $table->string('name', 191);
            $table->string('unit', 20)->default('pcs');

            $table->decimal('qty', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('note', 191)->nullable();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
