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
        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid');
            $table->string('password_hash');
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
        Schema::dropIfExists('password_history');
    }
};
