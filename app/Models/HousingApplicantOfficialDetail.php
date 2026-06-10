<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingApplicantOfficialDetail extends Model
{
    use HasFactory;

    protected $table = 'housing_applicant_official_detail';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'aid',
        'designation',
        'pay_band',
        'pay_in_band',
        'posting_place',
        'dor',
        'office_name',
        'office_street',
        'office_city',
        'pincode',
        'ddo_id',
        'doa',
        'serial_no',
        'remarks',
        'flat_type',
        'reason',
    ];

    protected $casts = [
        'dor' => 'date',
        'doa' => 'date',
    ];

    /**
     * Relationship: Applicant
     */
    public function applicant()
    {
        return $this->belongsTo(HousingApplicant::class, 'aid', 'id');
    }
}
