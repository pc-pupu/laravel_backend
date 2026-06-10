<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingApplicant extends Model
{
    use HasFactory;

    protected $table = 'housing_applicant';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'uid',
        'name',
        'dob',
        'gender',
        'mobile',
        'email',
        'hrms_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: User who registered
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    /**
     * Relationship: Official details
     */
    public function officialDetail()
    {
        return $this->hasOne(HousingApplicantOfficialDetail::class, 'aid', 'id');
    }

    /**
     * Relationship: Online applications
     */
    public function onlineApplications()
    {
        return $this->hasMany(HousingOnlineApplication::class, 'applicant_id', 'uid');
    }

    /**
     * Relationship: Allotment applications
     */
    public function allotmentApplications()
    {
        return $this->hasMany(HousingNewAllotmentApplication::class, 'applicant_id', 'uid');
    }

    /**
     * Scope: Get active applicants
     */
    public function scopeActive($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('status', 1);
        });
    }
}
