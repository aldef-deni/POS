<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shift is one cash-drawer session. The cashier declares the opening float,
 * sells against the shift, then counts the drawer at close so the system can
 * report the variance between expected and counted cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('cash_sales', 15, 2)->default(0);
            $table->decimal('non_cash_sales', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('cash_variance', 15, 2)->default(0);

            $table->decimal('total_sales', 15, 2)->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('total_refunds', 15, 2)->default(0);

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
