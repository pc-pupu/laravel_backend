<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add created_at and updated_at columns to tables that need them for Laravel
     */
    public function up(): void
    {
        $tables = [
            'housing_flat_occupant',
            'housing_allotment_roaster_counter',
            'housing_allotment_roaster_details',
            'housing_special_recommended_log',
            'housing_allotment_process',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                // Check if created_at column exists before adding
                if (!Schema::hasColumn($tableName, 'created_at')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->timestamp('created_at')->nullable();
                    });
                }
                
                // Check if updated_at column exists before adding
                if (!Schema::hasColumn($tableName, 'updated_at')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->timestamp('updated_at')->nullable();
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'housing_flat_occupant',
            'housing_allotment_roaster_counter',
            'housing_allotment_roaster_details',
            'housing_special_recommended_log',
            'housing_allotment_process',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'updated_at')) {
                        $table->dropColumn('updated_at');
                    }
                    if (Schema::hasColumn($tableName, 'created_at')) {
                        $table->dropColumn('created_at');
                    }
                });
            }
        }
    }
};
