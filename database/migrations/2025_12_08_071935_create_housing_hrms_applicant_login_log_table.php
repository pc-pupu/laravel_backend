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
        if (!Schema::hasTable('housing_hrms_applicant_login_log')) {
            Schema::create('housing_hrms_applicant_login_log', function (Blueprint $table) {
                $table->id();
                $table->string('hrms_id', 255)->nullable()->index();
                $table->text('json_encrypted_data')->nullable();
                $table->text('json_decrypted_data')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('hrms_id');
            });
        } else {
            // Table exists, ensure columns exist
            Schema::table('housing_hrms_applicant_login_log', function (Blueprint $table) {
                if (!Schema::hasColumn('housing_hrms_applicant_login_log', 'hrms_id')) {
                    $table->string('hrms_id', 255)->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('housing_hrms_applicant_login_log', 'json_encrypted_data')) {
                    $table->text('json_encrypted_data')->nullable()->after('hrms_id');
                }
                if (!Schema::hasColumn('housing_hrms_applicant_login_log', 'json_decrypted_data')) {
                    $table->text('json_decrypted_data')->nullable()->after('json_encrypted_data');
                }
                if (!Schema::hasColumn('housing_hrms_applicant_login_log', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('json_decrypted_data');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_hrms_applicant_login_log');
    }
};
