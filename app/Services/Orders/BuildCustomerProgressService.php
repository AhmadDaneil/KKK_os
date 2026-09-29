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
            'DETAILS_CONFIRMED' => [35, 'Maklumat Telah Disahkan', 'Maklumat tempahan anda telah berjaya disahkan.'],
            'READY_FOR_DESIGN' => [40, 'Menunggu Proses Design', 'Maklumat anda telah diterima dan sedia untuk proses design.'],
            'DESIGN_IN_PROGRESS' => [50, ...$this->designProgressCopy($order)],
            'DESIGN_READY' => [60, 'Hasil Reka Bentuk Sedia Untuk Semakan', 'Hasil reka bentuk anda telah tersedia untuk semakan.'],
            'CORRECTION_REQUESTED' => [60, 'Pembetulan Hasil Reka Bentuk Sedang Diproses', 'Permintaan pembetulan anda telah diterima.'],
            'DESIGN_APPROVED' => [70, 'Hasil Reka Bentuk Diluluskan', 'Hasil reka bentuk anda telah diluluskan.'],
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
            'CANCELLED' => [0, 'Tempahan Dibatalkan', 'Tempahan ini telah dibatalkan.'],
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
                ? ['Reka Bentuk Sedang Disediakan', 'Pereka sedang menyediakan hasil reka bentuk tempahan anda.']
                : ['Reka Bentuk Sedang Disediakan', 'Pereka sedang menyediakan artwork tempahan anda.'];
        }

        $subject = $active->count() > 1
            ? ($english ? "the groom's and bride's sides" : 'lelaki dan perempuan')
            : $active->first();

        return $english
            ? ["Reka Bentuk {$subject} Sedang Disediakan", "Pereka sedang menyediakan reka bentuk {$subject} untuk tempahan anda."]
            : ["Reka Bentuk {$subject} Sedang Disediakan", "Pereka sedang menyediakan reka bentuk {$subject} untuk tempahan anda."];
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
            'DETAILS_CONFIRMED', 'READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS' => 2,
            'DESIGN_READY', 'CORRECTION_REQUESTED' => 3,
            'DESIGN_APPROVED', 'BALANCE_PENDING' => 4,
            'PAID', 'READY_FOR_PRINT', 'PRINTING', 'PRINTED' => 5,
            'READY_FOR_PACKING', 'PACKING', 'PACKED', 'READY_FOR_FULFILMENT' => 6,
            'READY_FOR_PICKUP', 'SHIPPED' => 7,
            'COMPLETED', 'ARCHIVED' => 8,
            default => null,
        };

        $stageLabels = app()->bound('translator')
            ? __('ui.stages')
            : ['Tempahan', 'Maklumat', 'Reka Bentuk', 'Kelulusan', 'Bayaran Baki', 'Cetakan', 'Pembungkusan', 'Penghantaran / Pengambilan', 'Selesai'];

        return collect([
            ['label' => 'Tempahan', 'description' => 'Tempahan diterima'],
            ['label' => 'Maklumat', 'description' => 'Maklumat disahkan'],
            ['label' => 'Reka Bentuk', 'description' => 'Hasil reka bentuk disediakan'],
            ['label' => 'Kelulusan', 'description' => 'Semakan hasil reka bentuk'],
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
                $currentStage === 8 && $index === 8 => 'complete',
                $index < $currentStage => 'complete',
                $index === $currentStage => 'current',
                default => 'pending',
            };

            return $stage;
        })->all();
    }
}
