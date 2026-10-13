<?php

namespace App\Services\Orders;

use App\Models\Order;

class BuildCustomerProgressService
{
    public function build(Order $order): array
    {
        $status = $order->status;

        [$percentage, $label, $message] = match ($status) {
            'BOOKING_PENDING' => [5, 'Tempahan Sedang Diproses', 'Tempahan anda sedang diproses.'],
            'BOOKED', 'DEPOSIT_PAID' => [10, 'Tempahan Diterima', 'Tempahan anda telah diterima.'],
            'DETAILS_INCOMPLETE' => [20, 'Maklumat Belum Lengkap', 'Lengkapkan maklumat yang diperlukan sebelum membuat pengesahan.'],
            'DETAILS_CONFIRMED' => [35, 'Menunggu Pengesahan Deposit', 'Resit deposit anda sedang menunggu semakan staf.'],
            'READY_FOR_DESIGN' => [40, 'Menunggu Proses Design', 'Maklumat anda telah diterima dan sedia untuk proses design.'],
            'DESIGN_IN_PROGRESS' => [50, ...$this->designProgressCopy($order)],
            'DESIGN_READY' => [60, 'Hasil Design Sedia Untuk Semakan', 'Hasil design anda telah tersedia untuk semakan.'],
            'CORRECTION_REQUESTED' => [60, 'Pembetulan Hasil Design Sedang Diproses', 'Permintaan pembetulan anda telah diterima.'],
            'DESIGN_APPROVED' => [70, 'Hasil Design Diluluskan', 'Hasil design anda telah diluluskan.'],
            'BALANCE_PENDING' => [75, 'Menunggu Bayaran Baki', 'Bayaran baki diperlukan sebelum proses seterusnya.'],
            'PAID' => [80, 'Bayaran Selesai', 'Bayaran tempahan anda telah selesai.'],
            'READY_FOR_PRINT' => [82, 'Menunggu Proses Cetakan', 'Tempahan anda berada dalam giliran cetakan.'],
            'PRINTING' => [86, 'Dalam Proses Cetakan', 'Tempahan anda sedang dicetak.'],
            'PRINTED' => [88, 'Cetakan Selesai', 'Cetakan tempahan anda telah siap dan akan diteruskan ke proses pembungkusan.'],
            'READY_FOR_PACKING' => [90, 'Menunggu Pembungkusan', 'Tempahan anda sedang menunggu proses pembungkusan.'],
            'PACKING' => [92, 'Dalam Proses Pembungkusan', 'Tempahan anda sedang dibungkus.'],
            'PACKED' => [95, 'Pembungkusan Selesai', 'Tempahan anda telah siap dibungkus.'],
            'READY_FOR_PICKUP' => [95, 'Sedia Untuk Pengambilan', 'Tempahan anda telah sedia untuk diambil.'],
            'SHIPPED' => [95, 'Telah Dihantar', 'Tempahan anda telah diserahkan kepada kurier.'],
            'COMPLETED' => [100, 'Tempahan Selesai', 'Tempahan anda telah selesai.'],
            'CANCELLED' => [0, 'TELAH DIBATALKAN', 'Tempahan ini telah dibatalkan.'],
            'ARCHIVED' => [100, 'Tempahan Diarkibkan', 'Tempahan ini telah diarkibkan.'],
            default => [0, 'Status Tempahan', 'Status tempahan anda sedang dikemas kini.'],
        };

        if ($status !== 'DESIGN_IN_PROGRESS' && app()->bound('translator')) {
            $translationStatus = $status === 'DEPOSIT_PAID' ? 'BOOKED' : $status;
            $translations = __('ui.progress');
            [$label, $message] = $translations[array_key_exists($translationStatus, $translations) ? $translationStatus : 'DEFAULT'];
        }

        return [
            'percentage' => $percentage,
            'tone' => $this->tone($percentage),
            'label' => $label,
            'message' => $message,
            'stages' => $this->stages($status),
        ];
    }

    /**
     * @return array{string, string}
     */
    private function designProgressCopy(Order $order): array
    {
        $jobs = $order->relationLoaded('designJobs')
            ? $order->designJobs
            : $order->designJobs()->get();

        $sides = $jobs->mapWithKeys(fn ($job) => [strtoupper((string) $job->side) => $job->status])->all();
        $english = app()->getLocale() === 'en';
        $labels = $english
            ? ['LELAKI' => "groom's side", 'PEREMPUAN' => "bride's side"]
            : ['LELAKI' => 'lelaki', 'PEREMPUAN' => 'perempuan'];
        $active = collect($labels)->filter(fn ($label, $side) => isset($sides[$side]))->values();

        if ($active->isEmpty()) {
            return $english
                ? ['Design Sedang Disediakan', 'Pereka sedang menyediakan hasil design tempahan anda.']
                : ['Design Sedang Disediakan', 'Pereka sedang menyediakan artwork tempahan anda.'];
        }

        $subject = $active->count() > 1
            ? ($english ? "the groom's and bride's sides" : 'lelaki dan perempuan')
            : $active->first();

        return $english
            ? ["Design {$subject} Sedang Disediakan", "Pereka sedang menyediakan design {$subject} untuk tempahan anda."]
            : ["Design {$subject} Sedang Disediakan", "Pereka sedang menyediakan design {$subject} untuk tempahan anda."];
    }

    private function tone(int $percentage): string
    {
        return match (true) {
            $percentage <= 39 => 'red',
            $percentage <= 79 => 'yellow',
            default => 'green',
        };
    }

    private function stages(string $status): array
    {
        $currentStage = match ($status) {
            'BOOKING_PENDING' => 0,
            'BOOKED', 'DEPOSIT_PAID', 'DETAILS_INCOMPLETE' => 1,
            'DETAILS_CONFIRMED' => 2,
            'READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS' => 3,
            'DESIGN_READY', 'CORRECTION_REQUESTED' => 4,
            'DESIGN_APPROVED', 'BALANCE_PENDING' => 5,
            'PAID', 'READY_FOR_PRINT', 'PRINTING', 'PRINTED' => 6,
            'READY_FOR_PACKING', 'PACKING', 'PACKED', 'READY_FOR_FULFILMENT' => 7,
            'READY_FOR_PICKUP', 'SHIPPED' => 8,
            'COMPLETED', 'ARCHIVED' => 9,
            default => null,
        };

        $stageLabels = app()->bound('translator')
            ? __('ui.stages')
            : ['Tempahan', 'Maklumat', 'Pengesahan Deposit', 'Design', 'Kelulusan', 'Bayaran Baki', 'Cetakan', 'Pembungkusan', 'Penghantaran / Pengambilan', 'Selesai'];

        return collect([
            ['label' => 'Tempahan', 'description' => 'Tempahan diterima'],
            ['label' => 'Maklumat', 'description' => 'Maklumat disahkan'],
            ['label' => 'Pengesahan Deposit', 'description' => 'Deposit disemak dan disahkan'],
            ['label' => 'Design', 'description' => 'Hasil design disediakan'],
            ['label' => 'Kelulusan', 'description' => 'Semakan hasil design'],
            ['label' => 'Bayaran Baki', 'description' => 'Bayaran penuh'],
            ['label' => 'Cetakan', 'description' => 'Kad dicetak'],
            ['label' => 'Pembungkusan', 'description' => 'Kad dibungkus'],
            ['label' => 'Penghantaran / Pengambilan', 'description' => 'Dihantar atau diambil'],
            ['label' => 'Selesai', 'description' => 'Tempahan selesai'],
        ])->map(function (array $stage, int $index) use ($currentStage, $stageLabels) {
            $stage['label'] = $stageLabels[$index];
            $stage['number'] = $index + 1;
            $stage['state'] = match (true) {
                $currentStage === null => 'pending',
                $currentStage === 9 && $index === 9 => 'complete',
                $index < $currentStage => 'complete',
                $index === $currentStage => 'current',
                default => 'pending',
            };

            return $stage;
        })->all();
    }
}
