<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_items') || Schema::hasColumn('transaction_items', 'sort_order')) {
            return;
        }

        Schema::table('transaction_items', function (Blueprint $blueprint): void {
            $blueprint->unsignedInteger('sort_order')->default(0)->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_items') || ! Schema::hasColumn('transaction_items', 'sort_order')) {
            return;
        }

        Schema::table('transaction_items', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('sort_order');
        });
    }
};
