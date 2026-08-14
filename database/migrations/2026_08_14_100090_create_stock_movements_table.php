<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable ledger of every stock change. `qty` is signed (negative for
 * outgoing) and each row records the balance before and after, so inventory
 * can always be audited back to zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', ['in', 'out', 'sale', 'void_return', 'adjustment', 'opname'])
                ->default('in');
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('stock_before', 15, 3)->default(0);
            $table->decimal('stock_after', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);

            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 191)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
