<?php

namespace App\Support;

final class RequestFees
{
    public const PESOS = [
        'good-moral'    => 60,
        'personality'   => 60,
        'psychological' => 60,
        'career'        => 60,
    ];

    /**
     * Standardized fee calculation across guidance services:
     * - Good Moral Certificate: ₱60.00
     * - Psychological Assessment: ₱60.00
     * - Personality Test: ₱60.00
     * - Career Test: ₱60.00
     * - Exit Form: Free (null)
     */
    public static function total(?string $service, array $tests = [], int $copies = 1): ?int
    {
        if ($service === 'exit-form') {
            return null;
        }

        if ($service === 'testing') {
            $selectedTests = array_unique(array_filter((array) $tests));
            if (! empty($selectedTests)) {
                $matched = array_intersect_key(self::PESOS, array_flip($selectedTests));
                if (! empty($matched)) {
                    return (int) array_sum($matched);
                }
            }
            return 60;
        }

        if ($service && isset(self::PESOS[$service])) {
            return (int) (self::PESOS[$service] * max(1, $copies));
        }

        return 60;
    }
}
