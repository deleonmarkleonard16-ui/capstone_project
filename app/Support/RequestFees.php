<?php

namespace App\Support;

final class RequestFees
{
    public const PESOS = ['good-moral' => 60, 'personality' => 60, 'psychological' => 60, 'career' => 60];

    public static function total(string $service, array $tests = [], int $copies = 1): ?int
    {
        if ($service === 'testing') return array_sum(array_intersect_key(self::PESOS, array_flip(array_unique($tests))));
        return isset(self::PESOS[$service]) ? self::PESOS[$service] * max(1, $copies) : null;
    }
}
