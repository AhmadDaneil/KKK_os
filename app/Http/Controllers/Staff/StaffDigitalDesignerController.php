<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DigitalDesigner\GenerateDigitalDesignerCsvForOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffDigitalDesignerController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->where('booking_payment_status', 'PAID')
            ->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('staff.digital-designer.orders.index', compact('orders'));
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
}
