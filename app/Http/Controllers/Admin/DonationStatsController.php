<?php

namespace App\Http\Controllers\Admin;

use App\Constants\DonationOptions;
use App\Http\Controllers\Controller;
use App\Http\Resources\DonationStatsResource;
use App\Models\Donation;
use App\Support\AdminDonationFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationStatsController extends Controller
{
    public function __invoke(Request $request): DonationStatsResource
    {
        abort_unless($request->user()->hasPermission('donations.view'), 403);

        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', DonationOptions::STATUSES)],
            'category' => ['nullable', 'in:'.implode(',', DonationOptions::CATEGORIES)],
            'giving_method' => ['nullable', 'in:'.implode(',', DonationOptions::METHODS)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'timezone' => ['nullable', 'timezone'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $baseQuery = AdminDonationFilters::apply(Donation::query(), $filters);
        $paid = (clone $baseQuery)->where('status', DonationOptions::STATUS_PAID);
        $summary = (clone $baseQuery)
            ->selectRaw('count(*) as total_donations')
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as total_paid_amount', [DonationOptions::STATUS_PAID])
            ->selectRaw('sum(case when giving_method = ? and status = ? then 1 else 0 end) as pending_bank_transfers', [
                DonationOptions::METHOD_BANK_TRANSFER,
                DonationOptions::STATUS_UNDER_REVIEW,
            ])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_donations', [DonationOptions::STATUS_REJECTED])
            ->first();
        $driver = DB::connection()->getDriverName();
        $monthExpression = match ($driver) {
            'pgsql' => "to_char(paid_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "date_format(paid_at, '%Y-%m')",
            default => "strftime('%Y-%m', paid_at)",
        };

        return new DonationStatsResource([
            'total_donations' => (int) $summary->total_donations,
            'total_paid_amount' => (float) $summary->total_paid_amount,
            'pending_bank_transfers' => (int) $summary->pending_bank_transfers,
            'rejected_donations' => (int) $summary->rejected_donations,
            'totals_by_category' => (clone $paid)
                ->select('category', DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get(),
            'totals_by_giving_method' => (clone $paid)
                ->select('giving_method', DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupBy('giving_method')
                ->get(),
            'monthly_totals' => (clone $paid)
                ->select(DB::raw($monthExpression.' as month'), DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupByRaw($monthExpression)
                ->orderBy('month')
                ->get(),
        ]);
    }
}
