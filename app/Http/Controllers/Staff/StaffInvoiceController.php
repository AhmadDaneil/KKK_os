<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\BuildInvoiceLineItemsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StaffInvoiceController extends Controller
{
    public function print(Invoice $invoice): View
    {
        return view('staff.invoices.print', $this->invoiceViewData($invoice));
    }

    public function download(Invoice $invoice): Response
    {
        $pdf = Pdf::loadView('staff.invoices.pdf', $this->invoiceViewData($invoice))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'defaultMediaType' => 'print',
            ]);

        return $pdf->download($invoice->invoice_number.'.pdf');
    }

    /** @return array<string, mixed> */
    private function invoiceViewData(Invoice $invoice): array
    {
        $lineItems = app(BuildInvoiceLineItemsService::class);
        $invoice->loadMissing([
            'order.items',
            'order.packageSides.design',
            'order.packageSides.event',
            'order.couples',
            'order.fulfilment',
        ]);

        abort_if($invoice->issued_at === null || $invoice->order->booking_payment_status !== 'PAID', 404);

        $portalPrefix = request()->routeIs('admin.invoices.print') ? 'admin.' : 'staff.';

        return [
            'invoice' => $invoice,
            'order' => $invoice->order,
            'companyName' => config('invoice.business_name'),
            'registrationNumber' => config('invoice.registration_number'),
            'businessAddress' => config('invoice.business_address'),
            'businessEmail' => config('invoice.business_email'),
            'businessPhone' => config('invoice.business_phone'),
            'coupleNames' => $invoice->order->couples
                ->map(fn ($couple): string => trim(implode(' & ', array_filter([
                    $couple->groom_name,
                    $couple->bride_name,
                ]))))
                ->filter()
                ->implode(' / '),
            'eventDates' => $invoice->order->packageSides
                ->map(fn ($side): ?string => $side->event?->event_date?->format('d/m/Y'))
                ->filter()
                ->unique()
                ->implode(', '),
            'packageNames' => $invoice->order->packageSides
                ->map(fn ($side): ?string => $side->design?->design_code ?: $side->design?->theme)
                ->filter()
                ->unique()
                ->implode(', '),
            'terms' => $this->terms(),
            'orderRoute' => $portalPrefix.'orders.show',
            'invoiceLines' => $lineItems->build($invoice->order, $invoice),
            'deliveryLabel' => $lineItems->deliveryLabel($invoice->order),
        ];
    }

    /** @return array<string, list<string>> */
    private function terms(): array
    {
        return [
            'Order Process' => [
                'The customer confirms the package, quantity and order details with the person in charge.',
                'A deposit is required to confirm the order. Processing begins after payment is received.',
                'A design proof will be prepared for review.',
                'The customer is responsible for checking all submitted names, dates, times, addresses and other details.',
                'After final confirmation, the order proceeds to printing or preparation.',
                'The remaining balance must be paid in full before delivery or collection.',
                'The order is delivered using the selected fulfilment method.',
            ],
            'Terms & Conditions' => [
                'Deposits are non-refundable if the customer cancels the order.',
                'The company is not responsible for errors after the customer confirms the submitted details.',
                'Changes after final confirmation may incur additional charges.',
                'Completion time depends on final confirmation and receipt of payment.',
                'Slight colour differences between the screen and the printed product are normal.',
                'The company is not responsible for courier delays or damage, but will assist with claims.',
                'All payments must be made through the official KKK OS channels provided for the order.',
                'By making payment, the customer agrees to these terms and conditions.',
            ],
        ];
    }
}
