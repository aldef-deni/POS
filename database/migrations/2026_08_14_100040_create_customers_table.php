<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('name', 150);
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_member')->default(false);
            $table->unsignedInteger('points')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->unsignedInteger('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'phone']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
