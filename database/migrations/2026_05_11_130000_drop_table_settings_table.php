<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('table_settings');
    }

    public function down(): void
    {
        if (Schema::hasTable('table_settings')) {
            return;
        }

        Schema::create('table_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('resource');
            $table->json('styles')->nullable();
            $table->timestamps();
        });
    }
};
