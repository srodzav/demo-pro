<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'patient_name',
        'patient_email',
        'patient_phone',
        'treatment_type',
        'appointment_date',
        'notes',
        'status',
        'google_calendar_event_id',
    ];

    protected $casts = [
        'appointment_date' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now())
                     ->orderBy('appointment_date', 'asc');
    }

    // Accessors
    public function getTreatmentNameAttribute()
    {
        $treatments = [
            'brackets_metalicos' => 'Brackets Metálicos',
            'brackets_esteticos' => 'Brackets Estéticos',
            'ortodoncia_invisible' => 'Ortodoncia Invisible',
            'ortodoncia_infantil' => 'Ortodoncia Infantil',
        ];

        return $treatments[$this->treatment_type] ?? $this->treatment_type;
    }
}
