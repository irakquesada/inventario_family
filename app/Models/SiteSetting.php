<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['hero_title', 'hero_subtitle', 'hero_cta', 'about_title', 'about_text', 'phone', 'email', 'address', 'map_embed_url', 'instagram_url', 'facebook_url', 'reservations_enabled', 'testimonials_enabled', 'carousel_enabled', 'jobs_enabled', 'jobs_email', 'privacy_policy', 'legal_notice'];

    protected function casts(): array
    {
        return ['reservations_enabled' => 'boolean', 'testimonials_enabled' => 'boolean', 'carousel_enabled' => 'boolean', 'jobs_enabled' => 'boolean'];
    }
}
