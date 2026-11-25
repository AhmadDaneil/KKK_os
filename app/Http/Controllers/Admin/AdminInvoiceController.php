<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:UNPAID,PARTIALLY_PAID,PAID'],
            'staff' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $year = array_key_exists('year', $request->query())
            ? ($filters['year'] ?? null)
            : now()->year;
        $month = $filters['month'] ?? null;
        $day = $filters['day'] ?? null;
        $exactDate = $filters['date'] ?? null;
        $status = $filters['status'] ?? null;
        $staffId = $filters['staff'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $invoicesQuery = Invoice::query()
            ->with('order')
            ->whereNotNull('issued_at');

        if ($exactDate !== null) {
            $invoicesQuery->whereDate('issued_at', $exactDate);
        } else {
            if ($year !== null) {
                $invoicesQuery->whereYear('issued_at', $year);
            }

            if ($month !== null) {
                $invoicesQuery->whereMonth('issued_at', $month);
            }

            if ($day !== null) {
                $invoicesQuery->whereDay('issued_at', $day);
            }
        }

        if ($status !== null) {
            $invoicesQuery->where('payment_status', $status);
        }

        if ($staffId !== null) {
            $invoicesQuery->whereHas('order', function (Builder $orderQuery) use ($staffId): void {
                $orderQuery
                    ->where('printing_assigned_user_id', $staffId)
                    ->orWhere('packing_assigned_user_id', $staffId)
                    ->orWhereHas('designJobs', fn (Builder $designQuery) => $designQuery->where('assigned_user_id', $staffId));
            });
        }

        if ($search !== '') {
            $invoicesQuery->where(function (Builder $query) use ($search): void {
                $query->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function (Builder $orderQuery) use ($search): void {
                        $orderQuery->where('order_id', 'like', '%'.$search.'%')
                            ->orWhere('customer_reference', 'like', '%'.$search.'%')
                            ->orWhere('customer_name', 'like', '%'.$search.'%')
                            ->orWhere('customer_phone', 'like', '%'.$search.'%');
                    });
            });
        }

        $earliestIssuedAt = Invoice::query()->min('issued_at');
        $earliestYear = $earliestIssuedAt === null
            ? now()->year
            : CarbonImmutable::parse($earliestIssuedAt)->year;
        $currentYear = now()->year;
        $years = range($currentYear, min($currentYear, $earliestYear), -1);
        $staffOptions = User::query()
            ->where('is_active', true)
            ->whereIn('role', User::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.invoices.index', [
            'invoices' => $invoicesQuery->orderByDesc('issued_at')->paginate(15)->withQueryString(),
            'years' => $years,
            'staffOptions' => $staffOptions,
            'filters' => [
                'year' => $year === null ? '' : (string) $year,
                'month' => $month === null ? '' : (string) $month,
                'day' => $day === null ? '' : (string) $day,
                'date' => $exactDate ?? '',
                'status' => $status ?? '',
                'staff' => $staffId === null ? '' : (string) $staffId,
                'search' => $search,
            ],
        ]);
    }
}
