<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_certificates', function (Blueprint $table): void {
            $table->string('file_path', 500)->nullable()->after('notes');
            $table->string('file_name', 255)->nullable()->after('file_path');
            $table->string('mime_type', 120)->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_certificates', function (Blueprint $table): void {
            $table->dropColumn(['file_path', 'file_name', 'mime_type']);
        });
    }
};
