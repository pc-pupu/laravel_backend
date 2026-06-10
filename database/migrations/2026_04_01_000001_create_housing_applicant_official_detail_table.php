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
        // Create housing_applicant_official_detail table
        Schema::create('housing_applicant_official_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aid');
            $table->string('designation', 255)->nullable();
            $table->string('pay_band', 50)->nullable();
            $table->decimal('pay_in_band', 10, 2)->nullable();
            $table->string('posting_place', 255)->nullable();
            $table->date('dor')->nullable();
            $table->string('office_name', 255)->nullable();
            $table->string('office_street', 255)->nullable();
            $table->string('office_city', 255)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('ddo_id', 10)->nullable();
            $table->date('doa')->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->string('flat_type', 50)->nullable();
            $table->text('reason')->nullable();

            $table->foreign('aid')->references('id')->on('housing_applicant')->onDelete('cascade');
            $table->index('aid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_applicant_official_detail');
    }
};
