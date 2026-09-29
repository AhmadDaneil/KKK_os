<?php

namespace App\Services\Admin;

use App\Models\PaymentTransaction;
use Carbon\CarbonImmutable;

class BuildSalesAnalysisService
{
    public function build(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $todayStart = $now->startOfDay();
        $monthStart = $now->startOfMonth();
        $yearStart = $now->startOfYear();
        $historyStart = $now->subYears(4)->startOfYear();

        $paid = PaymentTransaction::query()
            ->where('status', 'PAID')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $historyStart)
            ->get(['order_id', 'payment_type', 'amount', 'paid_at']);

        $todayPayments = $paid->filter(fn ($payment) => $payment->paid_at->betweenIncluded($todayStart, $now));
        $monthPayments = $paid->filter(fn ($payment) => $payment->paid_at->betweenIncluded($monthStart, $now));
        $yearPayments = $paid->filter(fn ($payment) => $payment->paid_at->betweenIncluded($yearStart, $now));
        $previousMonthStart = $monthStart->subMonth();
        $previousMonthEnd = $monthStart->subSecond();
        $previousMonthSales = (float) $paid
            ->filter(fn ($payment) => $payment->paid_at->betweenIncluded($previousMonthStart, $previousMonthEnd))
            ->sum('amount');
        $monthSales = (float) $monthPayments->sum('amount');

        $pendingQuery = PaymentTransaction::query()->where('status', 'PENDING');
        $pendingAmount = (float) (clone $pendingQuery)->sum('amount');
        $pendingCount = (clone $pendingQuery)->count();
        $orderTotals = $monthPayments->groupBy('order_id')->map->sum('amount');

        $daily = collect(range(13, 0))->map(function (int $daysAgo) use ($now, $paid): array {
            $date = $now->subDays($daysAgo);

            return [
                'label' => $date->format('d M'),
                'amount' => (float) $paid->filter(fn ($payment) => $payment->paid_at->isSameDay($date))->sum('amount'),
            ];
        })->all();

        $monthLabels = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
        $monthly = collect(range(1, 12))->map(function (int $month) use ($now, $paid, $monthLabels): array {
            return [
                'label' => $monthLabels[$month - 1],
                'amount' => (float) $paid->filter(fn ($payment) => $payment->paid_at->year === $now->year && $payment->paid_at->month === $month)->sum('amount'),
            ];
        })->all();

        $annual = collect(range(4, 0))->map(function (int $yearsAgo) use ($now, $paid): array {
            $year = $now->subYears($yearsAgo)->year;

            return [
                'label' => (string) $year,
                'amount' => (float) $paid->filter(fn ($payment) => $payment->paid_at->year === $year)->sum('amount'),
            ];
        })->all();

        $breakdownLabels = [
            'BOOKING_DEPOSIT' => 'Deposit tempahan',
            'BALANCE' => 'Bayaran baki',
            'ARTWORK_CORRECTION' => 'Caj pembetulan',
        ];
        $breakdown = collect($breakdownLabels)->map(fn (string $label, string $type): array => [
            'type' => $type,
            'label' => $label,
            'amount' => (float) $yearPayments->where('payment_type', $type)->sum('amount'),
            'transactions' => $yearPayments->where('payment_type', $type)->count(),
        ])->values()->all();

        $bestMonth = collect($monthly)->sortByDesc('amount')->first();

        return [
            'summary' => [
                'today' => (float) $todayPayments->sum('amount'),
                'today_transactions' => $todayPayments->count(),
                'month' => $monthSales,
                'month_transactions' => $monthPayments->count(),
                'year' => (float) $yearPayments->sum('amount'),
                'year_transactions' => $yearPayments->count(),
                'year_orders' => $yearPayments->pluck('order_id')->unique()->count(),
                'average_order_value' => $orderTotals->isEmpty() ? 0.0 : (float) $orderTotals->average(),
                'pending_amount' => $pendingAmount,
                'pending_count' => $pendingCount,
                'month_change' => $previousMonthSales > 0
                    ? (($monthSales - $previousMonthSales) / $previousMonthSales) * 100
                    : ($monthSales > 0 ? 100.0 : 0.0),
                'best_month' => ($bestMonth['amount'] ?? 0) > 0 ? $bestMonth : null,
            ],
            'daily' => $this->withBarPercentages($daily),
            'monthly' => $this->withBarPercentages($monthly),
            'annual' => $this->withBarPercentages($annual),
            'breakdown' => $breakdown,
        ];
    }

    private function withBarPercentages(array $series): array
    {
        $maximum = max(1, ...array_column($series, 'amount'));

        return array_map(function (array $point) use ($maximum): array {
            $point['percentage'] = $point['amount'] > 0
                ? max(3, ($point['amount'] / $maximum) * 100)
                : 0;

            return $point;
        }, $series);
    }
}
