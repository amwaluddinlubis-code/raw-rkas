<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->string('committee_signature_path')->nullable()->after('committee_name');
            $table->string('principal_signature_path')->nullable()->after('principal_phone');
            $table->string('treasurer_signature_path')->nullable()->after('treasurer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'committee_signature_path',
                'principal_signature_path',
                'treasurer_signature_path',
            ]);
        });
    }
};
