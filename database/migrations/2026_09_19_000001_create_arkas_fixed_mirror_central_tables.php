<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('arkas_mirror.central', []);

        foreach ($tables as $entry) {
            $mirror = $entry['mirror'];

            if (Schema::hasTable($mirror)) {
                continue;
            }

            Schema::create($mirror, function (Blueprint $table): void {
                $table->id();
                $table->string('source_key', 255)->unique();
                $table->longText('payload');
                $table->string('source_hash', 64)->index();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->index(['updated_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('arkas_mirror.central', []) as $entry) {
            Schema::dropIfExists($entry['mirror']);
        }
    }
};
