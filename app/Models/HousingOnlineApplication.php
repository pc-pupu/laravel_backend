<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingOnlineApplication extends Model
{
    use HasFactory;

    protected $table = 'housing_online_application';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'applicant_id',
        'application_type',
        'status',
        'application_data',
    ];

    protected $casts = [
        'application_data' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Applicant
     */
    public function applicant()
    {
        return $this->belongsTo(HousingApplicant::class, 'applicant_id', 'uid');
    }

    /**
     * Scope: Get verified applications
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope: Get pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
