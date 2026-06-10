<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingNewAllotmentApplication extends Model
{
    use HasFactory;

    protected $table = 'housing_new_allotment_application';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'applicant_id',
        'application_type',
        'status',
        'allotment_data',
        'flat_assigned',
        'category',
    ];

    protected $casts = [
        'allotment_data' => 'json',
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
     * Scope: Get pending allotments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get allotted applications
     */
    public function scopeAllotted($query)
    {
        return $query->where('status', 'allotted');
    }

    /**
     * Scope: Get rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
