<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->unsignedInteger('base_amount_idr')->nullable()->after('topup_package_id');
            $table->unsignedInteger('unique_code')->default(0)->after('base_amount_idr');
        });

        // Backfill base_amount_idr from amount_idr for any existing orders
        DB::table('payment_orders')
            ->whereNull('base_amount_idr')
            ->update([
                'base_amount_idr' => DB::raw('amount_idr'),
                'unique_code' => 0,
            ]);

        Schema::create('active_payment_amounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('amount_idr')->unique();
            $table->string('order_id', 64)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_payment_amounts');

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn(['base_amount_idr', 'unique_code']);
        });
    }
};
