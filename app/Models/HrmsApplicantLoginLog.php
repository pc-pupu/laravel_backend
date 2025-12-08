<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrmsApplicantLoginLog extends Model
{
    protected $table = 'housing_hrms_applicant_login_log';
    
    public $timestamps = false; // Using created_at manually
    
    protected $fillable = [
        'hrms_id',
        'json_encrypted_data',
        'json_decrypted_data',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
