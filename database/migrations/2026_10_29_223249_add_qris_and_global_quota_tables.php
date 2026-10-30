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
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->string('payment_provider', 32)->default('midtrans')->after('status')->index();
            $table->string('qris_invoice_id', 64)->nullable()->after('payment_provider')->index();
            $table->text('qris_content')->nullable()->after('qris_invoice_id');
            $table->string('qris_nmid', 64)->nullable()->after('qris_content');
            $table->dateTime('qris_request_date')->nullable()->after('qris_nmid');
            $table->dateTime('qris_paid_date')->nullable()->after('qris_request_date');
            $table->string('qris_payment_methodby', 64)->nullable()->after('qris_paid_date');
            $table->string('qris_customer_name', 150)->nullable()->after('qris_payment_methodby');
            $table->timestamp('last_checked_at')->nullable()->after('qris_customer_name');
        });

        Schema::create('daily_global_quotas', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('published_free_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_global_quotas');

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_provider',
                'qris_invoice_id',
                'qris_content',
                'qris_nmid',
                'qris_request_date',
                'qris_paid_date',
                'qris_payment_methodby',
                'qris_customer_name',
                'last_checked_at',
            ]);
        });
    }
};
