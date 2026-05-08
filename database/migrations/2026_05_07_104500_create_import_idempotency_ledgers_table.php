<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_idempotency_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 120);
            $table->string('idempotency_key', 191)->unique();
            $table->string('file_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_idempotency_ledgers');
    }
};

