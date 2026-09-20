<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceSecurityIncident extends Model
{
    public const TYPES = [
        'app_switch' => 'App switch / Page hidden',
        'focus_loss' => 'External app / Focus loss',
        'back_navigation' => 'Back navigation attempt',
        'screenshot' => 'Screenshot/Capture shortcut',
        'print' => 'Print attempt',
        'save' => 'Save page attempt',
        'devtools' => 'Developer tools shortcut',
        'copy' => 'Copy attempt',
        'context_menu' => 'Context menu attempt',
        'fullscreen_exit' => 'Fullscreen exit',
    ];
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['created_at' => 'datetime', 'strike_number' => 'integer']; }
    public function appointment(): BelongsTo { return $this->belongsTo(GuidanceAppointment::class, 'guidance_appointment_id'); }
}
