<?php

namespace App\Support;

final class AdmissionSecurityIncident
{
    public const LABELS = [
        'focus_loss' => 'Possible Screenshot / Screen Overlay (Focus Loss)',
        'print_screen' => 'Screenshot Shortcut (Print Screen)',
        'screenshot' => 'Screenshot Shortcut',
        'restricted_gesture' => 'Restricted Multi-touch / Possible Screenshot',
        'print' => 'Print Attempt',
        'back_button' => 'Back Navigation',
        'fullscreen_exit' => 'Fullscreen Exit',
        'tab_switch' => 'Tab / App Switch',
        'page_exit' => 'Page Exit / Navigation',
        'page_backgrounded' => 'Page Backgrounded',
    ];

    public static function label(?string $type): string
    {
        return self::LABELS[$type ?? ''] ?? ucwords(str_replace('_', ' ', $type ?? ''));
    }
}
