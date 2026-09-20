<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceTestQrCode extends Model
{
    protected $primaryKey = 'guidance_test_qr_code_id';
    protected $guarded = ['guidance_test_qr_code_id'];
    protected $hidden = ['token'];
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(GuidanceAppointment::class, 'guidance_appointment_id');
    }
}
