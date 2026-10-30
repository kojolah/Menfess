<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();
            $table->string('badge_color', 20)->default('#F6DDE8');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menfesses', function (Blueprint $table) {
            $table->id();
            $table->string('public_number', 32)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->json('platforms');
            $table->string('status', 32)->default('draft')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('menfess_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menfess_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menfess_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderator_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['menfess_id', 'created_at']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->text('reason')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('topup_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('price_idr');
            $table->unsignedInteger('tokens');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_package_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_idr');
            $table->unsignedInteger('tokens');
            $table->string('status', 24)->default('pending')->index();
            $table->string('snap_token')->nullable();
            $table->string('midtrans_transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menfess_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->text('safe_error')->nullable();
            $table->unsignedInteger('tokens_reserved')->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->timestamps();
            $table->unique(['menfess_id', 'platform']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('publication_jobs');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('topup_packages');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('menfess_attachments');
        Schema::dropIfExists('menfesses');
        Schema::dropIfExists('categories');
    }
};
