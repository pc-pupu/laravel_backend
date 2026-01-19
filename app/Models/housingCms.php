<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class housingCms extends Model
{
    protected $table = 'housing_cms';  // your table name
    protected $primaryKey = 'housing_cms_id';             // change if your PK is different
    public $timestamps = false;               // set true if you have created_at/updated_at

    protected $fillable = [
        'content_type',
        'content_title',
        'link_title',
        'order_no',
        'meta_keyword',
        'meta_description',
        'date_of_notification',
        'content_description',
        'is_active',
        'is_new',
        'url',
        'file_name',
        'file_path',
        'created_date',
        'updated_date',
        // add more columns as needed
    ];
}
