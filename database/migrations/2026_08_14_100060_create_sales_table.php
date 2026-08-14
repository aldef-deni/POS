<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40);
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->enum('discount_type', ['none', 'amount', 'percent'])->default('none');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('service_charge_amount', 15, 2)->default(0);
            $table->decimal('rounding_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);

            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('total_qty', 15, 3)->default(0);

            // Snapshot of margin at the moment of sale, so later cost edits
            // never rewrite historical profit figures.
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);

            $table->enum('status', ['completed', 'voided', 'refunded'])->default('completed');
            $table->text('note')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 191)->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
