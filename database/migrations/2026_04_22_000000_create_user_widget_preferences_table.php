<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_widget_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel', 32);
            $table->string('page', 128);
            $table->json('visible_widgets')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'panel', 'page'], 'user_widget_prefs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_widget_preferences');
    }
};

