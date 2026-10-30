<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('platform', 20);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->json('theme')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'platform', 'is_active']);
        });

        Schema::create('generated_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menfess_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('mime_type', 100)->default('image/png');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['menfess_id', 'platform', 'created_at']);
        });

        Schema::create('publication_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publication_job_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('tokens_charged')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'platform', 'published_at']);
        });

        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menfess_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code', 40);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['menfess_id', 'status']);
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('label', 100);
            $table->string('account_id', 150);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
            $table->unique(['platform', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('publication_usage');
        Schema::dropIfExists('generated_images');
        Schema::dropIfExists('templates');
    }
};
