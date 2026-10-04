<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_operator_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_package_id')->constrained('spj_packages')->cascadeOnDelete();
            $table->text('body');
            // users hidup di database pusat, bukan database sekolah — tanpa FK.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['spj_package_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_operator_notes');
    }
};
