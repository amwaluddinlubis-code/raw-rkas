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
        Schema::connection('school')->table('goods_receipts', function (Blueprint $table): void {
            $table->date('order_date')->nullable()->after('receipt_date');
            $table->date('bap_date')->nullable()->after('order_date');
            $table->date('bast_date')->nullable()->after('bap_date');
            $table->date('invoice_date')->nullable()->after('bast_date');
            $table->string('invoice_status', 30)->nullable()->after('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school')->table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn(['order_date', 'bap_date', 'bast_date', 'invoice_date', 'invoice_status']);
        });
    }
};
