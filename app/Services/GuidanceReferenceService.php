<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuidanceReferenceService
{
    public function reserve(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = $this->candidate();
            try {
                // A savepoint lets a collision retry safely inside the request transaction.
                DB::transaction(fn () => DB::table('guidance_reference_codes')->insert(['code' => $code, 'created_at' => now()]));
                return $code;
            } catch (UniqueConstraintViolationException $exception) {
                continue;
            }
        }
        throw ValidationException::withMessages(['reference' => 'Unable to allocate a tracking reference. Please retry.']);
    }

    protected function candidate(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = 'G-';
        for ($index = 0; $index < 4; $index++) $code .= $alphabet[random_int(0, 35)];
        return $code;
    }
}
