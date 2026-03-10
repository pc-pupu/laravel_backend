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
        if (Schema::hasTable('error_logs')) {
            return;
        }

        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('error');
            $table->text('message')->nullable();
            $table->string('exception_type', 255)->nullable()->comment('Exception/error class or type');
            $table->json('context')->nullable();
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->text('trace')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('User uid who triggered the error');
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('error_logs', function (Blueprint $table) {
            $table->index('level');
            $table->index('user_id');
            $table->index('created_at');
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
