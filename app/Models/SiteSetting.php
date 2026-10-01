<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['hero_title', 'hero_subtitle', 'hero_cta', 'about_title', 'about_text', 'phone', 'email', 'address', 'map_embed_url', 'map_directions_url', 'instagram_url', 'facebook_url', 'reservations_enabled', 'testimonials_enabled', 'carousel_enabled', 'jobs_enabled', 'jobs_email', 'privacy_policy', 'legal_notice', 'reservation_mail_from_name', 'reservation_mail_from_address', 'reservation_mail_received', 'reservation_mail_confirmed', 'reservation_mail_cancelled', 'seo_site_name', 'seo_title', 'seo_description', 'seo_keywords', 'seo_canonical_url', 'seo_og_image', 'seo_business_type', 'seo_indexing_enabled', 'legal_business_name', 'business_tax_id', 'social_security_account', 'payroll_address'];

    protected function casts(): array
    {
        return ['reservations_enabled' => 'boolean', 'testimonials_enabled' => 'boolean', 'carousel_enabled' => 'boolean', 'jobs_enabled' => 'boolean', 'seo_indexing_enabled' => 'boolean'];
    }
}
