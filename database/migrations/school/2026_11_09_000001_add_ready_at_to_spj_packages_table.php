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
        Schema::connection('school')->table('spj_packages', function (Blueprint $table): void {
            $table->timestamp('ready_at')->nullable()->after('status');
            $table->index(['status', 'ready_at'], 'spj_packages_ready_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school')->table('spj_packages', function (Blueprint $table): void {
            $table->dropIndex('spj_packages_ready_order_index');
            $table->dropColumn('ready_at');
        });
    }
};
