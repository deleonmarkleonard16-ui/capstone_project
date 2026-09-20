<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceTestSecurityLog extends Model
{
    protected $table = 'guidance_test_security_logs';
    protected $primaryKey = 'log_id';
    protected $guarded = ['log_id'];

    protected function casts(): array
    {
        return [
            'strike_number' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(GuidanceAppointment::class, 'guidance_appointment_id');
    }
}
