<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create housing_online_application table
        Schema::create('housing_online_application', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->string('application_type', 100);
            $table->enum('status', ['pending', 'verified', 'rejected', 'approved'])->default('pending');
            $table->longText('application_data')->nullable();
            $table->timestamps();

            $table->foreign('applicant_id')->references('uid')->on('housing_applicant')->onDelete('cascade');
            $table->index('applicant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_online_application');
    }
};
