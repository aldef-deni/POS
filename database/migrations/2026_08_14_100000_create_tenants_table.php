<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant is one business / outlet subscribing to the POS. Every other
 * table hangs off this row, which is what makes the product multi-tenant.
 * Store profile, tax rules, receipt layout and the product-ID mechanism all
 * live here so an Owner can reconfigure them without touching code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('legal_name', 150)->nullable();
            $table->string('business_type', 60)->nullable();

            // Store profile, printed on every receipt.
            $table->text('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('website', 120)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->string('logo_path', 191)->nullable();

            // Money formatting.
            $table->string('currency', 8)->default('IDR');
            $table->string('currency_symbol', 8)->default('Rp');

            // Tax & surcharge rules applied at checkout.
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('tax_percent', 5, 2)->default(11.00);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->enum('rounding_mode', ['none', 'nearest_100', 'nearest_500', 'nearest_1000'])
                ->default('none');

            // Receipt appearance.
            $table->enum('receipt_paper', ['58mm', '80mm', 'a4'])->default('80mm');
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->boolean('receipt_show_qr')->default(true);
            $table->boolean('receipt_show_logo')->default(true);

            // Product-ID (SKU) mechanism, configurable from the dashboard.
            $table->string('sku_prefix', 12)->default('PRD');
            $table->string('sku_separator', 2)->default('-');
            $table->boolean('sku_include_category')->default(true);
            $table->enum('sku_date_segment', ['none', 'yy', 'yymm', 'yymmdd'])->default('yymm');
            $table->unsignedTinyInteger('sku_sequence_length')->default(4);
            $table->unsignedBigInteger('sku_next_number')->default(1);
            $table->enum('barcode_type', ['C128', 'EAN13'])->default('C128');

            // Inventory behaviour.
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('allow_negative_stock')->default(false);

            // SaaS subscription state.
            $table->enum('plan', ['free', 'pro', 'enterprise'])->default('pro');
            $table->date('plan_expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
