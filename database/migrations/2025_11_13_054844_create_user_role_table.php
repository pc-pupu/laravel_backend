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
        Schema::create('user_role', function (Blueprint $table) {
            $table->unsignedBigInteger('uid');
            $table->unsignedBigInteger('rid');
            $table->timestamps();
            
            $table->primary(['uid', 'rid']);
            $table->foreign('uid')->references('uid')->on('users')->onDelete('cascade');
            $table->foreign('rid')->references('id')->on('roles')->onDelete('cascade');
            $table->index('rid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};
