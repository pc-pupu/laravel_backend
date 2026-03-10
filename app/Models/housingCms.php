<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CMS content (housing_cms).
 * Mirrors Drupal cms_content module: faq, about_us, contact_us, what_is_new, notice, user_manual.
 */
class housingCms extends Model
{
    protected $table = 'housing_cms';
    protected $primaryKey = 'housing_cms_id';
    public $timestamps = false;

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
    ];
}
