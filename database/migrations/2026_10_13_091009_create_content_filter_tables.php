<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_filter_rules', function (Blueprint $table) {
            $table->id();
            $table->string('phrase', 160)->unique();
            $table->string('category', 40)->index();
            $table->string('action', 20)->default('censor');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_filter_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('phrase', 160)->unique();
            $table->string('reason', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('menfesses', function (Blueprint $table) {
            $table->unsignedSmallInteger('censored_count')->default(0)->after('body');
            $table->string('filter_status', 20)->default('not_processed')->after('censored_count')->index();
            $table->timestamp('filtered_at')->nullable()->after('filter_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menfesses', function (Blueprint $table) {
            $table->dropColumn(['censored_count', 'filter_status', 'filtered_at']);
        });
        Schema::dropIfExists('content_filter_exceptions');
        Schema::dropIfExists('content_filter_rules');
    }
};
