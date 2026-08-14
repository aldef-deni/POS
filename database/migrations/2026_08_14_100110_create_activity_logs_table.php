<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for sensitive actions: voids, price edits, stock adjustments,
 * user changes and logins. Owners can review who did what, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guard', 20)->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 191)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
