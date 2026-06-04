<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $fillable = [
        'clinic_name',
        'legal_name',
        'email',
        'phone',
        'whatsapp',
        'address',
        'city',
        'state',
        'primary_color',
        'accent_color',
        'logo_url',
        'business_hours',
        'social_links',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'social_links' => 'array',
    ];
}
