<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AdminDonationFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['giving_method'] ?? null, fn (Builder $query, string $method) => $query->where('giving_method', $method))
            ->when($filters['date_from'] ?? null, function (Builder $query, string $date) use ($filters) {
                $query->where('created_at', '>=', self::localDayBoundary($date, $filters, 'start'));
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $date) use ($filters) {
                $query->where('created_at', '<=', self::localDayBoundary($date, $filters, 'end'));
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('donor_name', 'like', "%{$search}%")
                        ->orWhere('donor_email', 'like', "%{$search}%")
                        ->orWhere('gateway_reference', 'like', "%{$search}%");
                });
            });
    }

    private static function localDayBoundary(string $date, array $filters, string $boundary): CarbonImmutable
    {
        $timezone = $filters['timezone'] ?? config('app.timezone', 'UTC');
        $appTimezone = config('app.timezone', 'UTC');
        $localDate = CarbonImmutable::parse($date, $timezone);

        return ($boundary === 'start' ? $localDate->startOfDay() : $localDate->endOfDay())
            ->setTimezone($appTimezone);
    }
}
