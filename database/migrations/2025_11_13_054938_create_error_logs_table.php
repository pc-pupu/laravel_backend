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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('error');
            $table->text('message');
            $table->text('context')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->text('trace')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            $table->index('level');
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
