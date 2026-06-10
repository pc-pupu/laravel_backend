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
        // Create housing_applicant table
        Schema::create('housing_applicant', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid')->unique();
            $table->string('name', 255);
            $table->date('dob');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('mobile', 10)->unique();
            $table->string('email', 255)->unique();
            $table->string('hrms_id', 10)->unique();
            $table->timestamps();

            $table->foreign('uid')->references('uid')->on('users')->onDelete('cascade');
            $table->index('uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_applicant');
    }
};
