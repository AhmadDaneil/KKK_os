<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
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
        ];
    }

    /** @return array<string, list<string>> */
    private function terms(): array
    {
        return [
            'Proses Tempahan' => [
                'Pelanggan mengesahkan pakej, kuantiti dan butiran tempahan bersama PIC.',
                'Bayaran deposit diperlukan untuk mengesahkan tempahan. Tempahan diproses selepas bayaran diterima.',
                'Draf reka bentuk akan dihantar kepada pelanggan untuk semakan.',
                'Pelanggan bertanggungjawab menyemak draf termasuk ejaan nama, tarikh, masa, alamat dan maklumat lain.',
                'Selepas pengesahan akhir diterima, tempahan dihantar untuk proses cetakan atau penyediaan.',
                'Baki bayaran perlu dijelaskan sepenuhnya sebelum penghantaran atau pengambilan tempahan.',
                'Tempahan dihantar mengikut kaedah penghantaran yang telah dipilih.',
            ],
            'Terma & Syarat' => [
                'Deposit yang telah dibayar tidak akan dikembalikan sekiranya tempahan dibatalkan oleh pelanggan.',
                'Pihak syarikat tidak bertanggungjawab atas kesilapan selepas draf disahkan oleh pelanggan.',
                'Sebarang pindaan selepas pengesahan akhir mungkin dikenakan caj tambahan.',
                'Tempoh siap tempahan bergantung kepada tarikh pengesahan draf dan penerimaan bayaran.',
                'Sedikit perbezaan warna antara paparan skrin dan hasil cetakan adalah perkara biasa.',
                'Pihak syarikat tidak bertanggungjawab atas kelewatan atau kerosakan oleh pihak kurier, namun akan membantu membuat tuntutan.',
                'Semua bayaran hendaklah dibuat melalui saluran rasmi KKK OS yang diberikan untuk tempahan.',
                'Dengan membuat bayaran, pelanggan dianggap bersetuju dengan semua terma dan syarat ini.',
            ],
        ];
    }
}
