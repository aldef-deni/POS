<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per tender. Keeping payments in their own table is what makes
 * split payment (part cash, part QRIS) possible without schema gymnastics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'qris', 'transfer', 'ewallet', 'voucher'])
                ->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('provider', 60)->nullable();
            $table->timestamps();

            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
