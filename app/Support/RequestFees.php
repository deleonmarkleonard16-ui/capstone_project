<?php

namespace App\Support;

final class RequestFees
{
    public const PESOS = [
        'good-moral'    => 60,
        'personality'   => 60,
        'psychological' => 60,
        'career'        => 60,
        'exit-form'     => 60,
    ];

    public static function total(?string $service, array $tests = [], int $copies = 1): float
    {
        if ($service === 'testing') {
            $selectedTests = array_unique(array_filter((array) $tests));
            if (! empty($selectedTests)) {
                $matched = array_intersect_key(self::PESOS, array_flip($selectedTests));
                if (! empty($matched)) {
                    return (float) array_sum($matched);
                }
            }
            return 60.00;
        }

        if ($service && isset(self::PESOS[$service])) {
            return (float) (self::PESOS[$service] * max(1, $copies));
        }

        return 60.00;
    }
}
