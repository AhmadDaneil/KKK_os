<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrderService;
use App\Services\Photoshop\LaunchPhotoshopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffPhotoshopController extends Controller
{
    public function downloadCsv(
        Request $request,
        Order $order,
        GeneratePhotoshopCsvForOrderService $service
    ): BinaryFileResponse|RedirectResponse {
        $this->authorizeAssignedDesigner($request, $order);

        $directory = storage_path('app/private/photoshop-downloads');
        $downloadName = $order->order_id.'_READY_TO_MERGE.csv';
        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.csv';

        try {
            $service->generate($order, $path);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['photoshop' => $exception->getMessage()]);
        }

        return response()
            ->download($path, $downloadName, ['Content-Type' => 'text/csv; charset=UTF-8'])
            ->deleteFileAfterSend(true);
    }

    public function launch(
        Request $request,
        Order $order,
        LaunchPhotoshopService $service
    ): RedirectResponse {
        $this->authorizeAssignedDesigner($request, $order);

        try {
            $service->launch();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['photoshop' => $exception->getMessage()]);
        }

        return back()->with(
            'status',
            'Photoshop is opening with the V11 script. Select the ROOT folder, then select the downloaded CSV file.'
        );
    }

    private function authorizeAssignedDesigner(Request $request, Order $order): void
    {
        abort_unless(
            $order->designJobs()
                ->where('assigned_user_id', $request->user()->id)
                ->exists(),
            404
        );
    }
}
