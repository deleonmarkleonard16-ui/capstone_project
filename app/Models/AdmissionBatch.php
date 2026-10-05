<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionBatch extends Model
{
    protected $fillable = ['admission_cycle_id', 'batch_name', 'batch_date', 'room'];

    protected function casts(): array
    {
        return ['batch_date' => 'date'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AdmissionSession::class, 'admission_batch_id');
    }
}
