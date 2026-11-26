<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DigitalDesigner\GenerateDigitalDesignerCsvForOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffDigitalDesignerController extends Controller
{
    public function index(): View
    {
        $orders = $this->paidOrdersQuery()
            ->paginate(10)
            ->withQueryString();

        return view('staff.digital-designer.orders.index', compact('orders'));
    }

    public function downloadPageCsv(
        Request $request,
        GenerateDigitalDesignerCsvForOrderService $service,
    ): BinaryFileResponse|RedirectResponse {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $orders = $this->paidOrdersQuery()->paginate(10, ['*'], 'page', $page);

        abort_if($orders->isEmpty(), 404);

        $relativePath = 'digital-designer-downloads/'.Str::uuid().'.csv';
        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $absolutePath = Storage::disk('local')->path($relativePath);

        try {
            $service->generateForOrders(
                new EloquentCollection($orders->items()),
                $absolutePath,
            );
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($relativePath);

            return back()->withErrors(['digital_designer' => $exception->getMessage()]);
        }

        $downloadName = 'ALIVE_CARD_MOTION_CUSTOMERS_PAGE_'.$page.'.csv';

        return response()
            ->download($absolutePath, $downloadName, ['Content-Type' => 'text/csv; charset=UTF-8'])
            ->deleteFileAfterSend(true);
    }

    public function downloadCsv(
        Order $order,
        GenerateDigitalDesignerCsvForOrderService $service,
    ): BinaryFileResponse|RedirectResponse {
        abort_unless(
            $order->booking_payment_status === 'PAID'
                && ! $order->isTerminalOperationalStatus(),
            404,
        );

        $relativePath = 'digital-designer-downloads/'.Str::uuid().'.csv';
        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $absolutePath = Storage::disk('local')->path($relativePath);

        try {
            $service->generate($order, $absolutePath);
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($relativePath);

            return back()->withErrors(['digital_designer' => $exception->getMessage()]);
        }

        $reference = $order->displayCustomerReference() ?: $order->order_id;
        $downloadName = (Str::slug($reference) ?: $order->order_id).'_DIGITAL_DESIGNER.csv';

        return response()
            ->download($absolutePath, $downloadName, ['Content-Type' => 'text/csv; charset=UTF-8'])
            ->deleteFileAfterSend(true);
    }

    private function paidOrdersQuery(): Builder
    {
        return Order::query()
            ->where('booking_payment_status', 'PAID')
            ->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES)
            ->latest('id');
    }
}
