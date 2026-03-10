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
        if (!Schema::hasTable('error_logs')) {
            return;
        }
        if (Schema::hasColumn('error_logs', 'exception_type')) {
            return;
        }
        Schema::table('error_logs', function (Blueprint $table) {
            $table->string('exception_type', 255)->nullable()->after('message')->comment('Exception/error class or type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('error_logs') && Schema::hasColumn('error_logs', 'exception_type')) {
            Schema::table('error_logs', function (Blueprint $table) {
                $table->dropColumn('exception_type');
            });
        }
    }
};
