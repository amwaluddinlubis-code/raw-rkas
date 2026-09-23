<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_external_checklist_ticks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_package_id')->constrained('spj_packages')->cascadeOnDelete();
            $table->string('item_key', 60);
            $table->boolean('is_checked')->default(false);
            // users hidup di database pusat, bukan database sekolah — tanpa FK.
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['spj_package_id', 'item_key']);
            $table->index(['spj_package_id', 'is_checked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_external_checklist_ticks');
    }
};
