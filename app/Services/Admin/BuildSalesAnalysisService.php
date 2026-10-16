<?php

namespace App\Services\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPricingSnapshot;
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

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
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
            'BOOKING_DEPOSIT' => 'Order deposit',
            'BALANCE' => 'Balance payment',
            'ARTWORK_CORRECTION' => 'Artwork correction fee',
        ];
        $breakdown = collect($breakdownLabels)->map(fn (string $label, string $type): array => [
            'type' => $type,
            'label' => $label,
            'amount' => (float) $yearPayments->where('payment_type', $type)->sum('amount'),
            'transactions' => $yearPayments->where('payment_type', $type)->count(),
        ])->values()->all();

        $bestMonth = collect($monthly)->sortByDesc('amount')->first();
        $businessSummary = [
            'gross_sales' => (float) Invoice::query()->sum('total_amount'),
            'collected' => (float) PaymentTransaction::query()->where('status', 'PAID')->sum('amount'),
            'outstanding' => (float) Invoice::query()->sum('balance_due'),
            'total_orders' => Order::query()->where('status', '!=', 'DETAILS_INCOMPLETE')->count(),
            'addon_sales' => (float) OrderItem::query()->where('item_type', 'ADDON')->sum('line_total'),
            'postage_sales' => (float) OrderPricingSnapshot::query()->sum('postage_amount'),
            'booking_collected' => (float) PaymentTransaction::query()
                ->where('status', 'PAID')->where('payment_type', 'BOOKING_DEPOSIT')->sum('amount'),
            'balance_collected' => (float) PaymentTransaction::query()
                ->where('status', 'PAID')->where('payment_type', 'BALANCE')->sum('amount'),
        ];
        $packageBreakdown = Order::query()
            ->where('status', '!=', 'DETAILS_INCOMPLETE')
            ->selectRaw('package_count, count(*) as total')
            ->groupBy('package_count')
            ->pluck('total', 'package_count')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return [
            'business_summary' => $businessSummary,
            'package_breakdown' => [
                1 => $packageBreakdown[1] ?? 0,
                2 => $packageBreakdown[2] ?? 0,
            ],
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
