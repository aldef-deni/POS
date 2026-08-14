<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parked carts. Lets a cashier suspend a basket to serve the next customer
 * and resume it later without losing the line items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('label', 100)->nullable();
            $table->json('payload');
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_orders');
    }
};
