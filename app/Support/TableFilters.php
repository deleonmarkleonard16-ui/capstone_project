<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TableFilters
{
    public static function validate(Request $request, array $statuses): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'course' => ['nullable', Rule::in(array_keys(CourseCatalog::allOptions()))],
            'status' => ['nullable', Rule::in($statuses)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    public static function dates(Builder $query, array $filters): Builder
    {
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        return $query;
    }

    public static function words(?string $value): array
    {
        return preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
