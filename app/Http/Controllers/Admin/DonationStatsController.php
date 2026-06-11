<?php

namespace App\Http\Controllers\Admin;

use App\Constants\DonationOptions;
use App\Http\Controllers\Controller;
use App\Http\Resources\DonationStatsResource;
use App\Models\Donation;
use Illuminate\Support\Facades\DB;

class DonationStatsController extends Controller
{
    public function __invoke(): DonationStatsResource
    {
        $paid = Donation::where('status', DonationOptions::STATUS_PAID);
        $driver = DB::connection()->getDriverName();
        $monthExpression = match ($driver) {
            'pgsql' => "to_char(paid_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "date_format(paid_at, '%Y-%m')",
            default => "strftime('%Y-%m', paid_at)",
        };

        return new DonationStatsResource([
            'total_donations' => Donation::count(),
            'total_paid_amount' => (clone $paid)->sum('amount'),
            'pending_bank_transfers' => Donation::where('giving_method', DonationOptions::METHOD_BANK_TRANSFER)
                ->where('status', DonationOptions::STATUS_UNDER_REVIEW)
                ->count(),
            'rejected_donations' => Donation::where('status', DonationOptions::STATUS_REJECTED)->count(),
            'totals_by_category' => (clone $paid)
                ->select('category', DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get(),
            'totals_by_giving_method' => Donation::where('status', DonationOptions::STATUS_PAID)
                ->select('giving_method', DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupBy('giving_method')
                ->get(),
            'monthly_totals' => Donation::where('status', DonationOptions::STATUS_PAID)
                ->select(DB::raw($monthExpression.' as month'), DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
                ->groupByRaw($monthExpression)
                ->orderBy('month')
                ->get(),
        ]);
    }
}
