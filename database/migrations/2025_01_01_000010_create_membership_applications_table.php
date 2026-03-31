<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('national_id', 20);
            $table->date('date_of_birth');
            $table->text('address');
            $table->string('city', 100);
            $table->string('occupation', 150)->nullable();
            $table->string('employer', 150)->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->string('next_of_kin_name', 150);
            $table->string('next_of_kin_phone', 30);
            $table->string('application_form_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
