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
            'DESIGN_IN_PROGRESS' => [50, 'Design Sedang Disediakan', 'Designer sedang menyediakan artwork tempahan anda.'],
            'DESIGN_READY' => [60, 'Artwork Sedia Untuk Semakan', 'Artwork anda telah tersedia untuk semakan.'],
            'CORRECTION_REQUESTED' => [60, 'Pembetulan Artwork Sedang Diproses', 'Permintaan pembetulan anda telah diterima.'],
            'DESIGN_APPROVED' => [70, 'Artwork Diluluskan', 'Artwork anda telah diluluskan.'],
            'BALANCE_PENDING' => [75, 'Menunggu Bayaran Baki', 'Bayaran baki diperlukan sebelum proses seterusnya.'],
            'PAID' => [80, 'Bayaran Selesai', 'Bayaran tempahan anda telah selesai.'],
            'READY_FOR_PRINT' => [82, 'Menunggu Proses Cetakan', 'Tempahan anda berada dalam giliran cetakan.'],
            'PRINTING' => [86, 'Dalam Proses Cetakan', 'Tempahan anda sedang dicetak.'],
            'READY_FOR_PACKING' => [90, 'Menunggu Pembungkusan', 'Tempahan anda sedang menunggu proses pembungkusan.'],
            'PACKING' => [92, 'Dalam Proses Pembungkusan', 'Tempahan anda sedang dibungkus.'],
            'PACKED' => [95, 'Pembungkusan Selesai', 'Tempahan anda telah siap dibungkus.'],
            'READY_FOR_PICKUP' => [95, 'Sedia Untuk Pickup', 'Tempahan anda telah sedia untuk diambil.'],
            'SHIPPED' => [95, 'Telah Dihantar', 'Tempahan anda telah diserahkan kepada courier.'],
            'COMPLETED' => [100, 'Tempahan Selesai', 'Tempahan anda telah selesai.'],
            'CANCELLED' => [0, 'Tempahan Dibatalkan', 'Tempahan ini telah dibatalkan.'],
            'ARCHIVED' => [100, 'Tempahan Diarkibkan', 'Tempahan ini telah diarkibkan.'],
            default => [0, 'Status Tempahan', 'Status tempahan anda sedang dikemas kini.'],
        };

        return [
            'percentage' => $percentage,
            'tone' => $this->tone($percentage),
            'label' => $label,
            'message' => $message,
            'stages' => $this->stages($percentage),
        ];
    }

    private function tone(int $percentage): string
    {
        return match (true) {
            $percentage <= 39 => 'red',
            $percentage <= 79 => 'yellow',
            default => 'green',
        };
    }

    private function stages(int $percentage): array
    {
        return collect([
            ['label' => 'Maklumat', 'threshold' => 20],
            ['label' => 'Design', 'threshold' => 40],
            ['label' => 'Bayaran', 'threshold' => 80],
            ['label' => 'Cetakan', 'threshold' => 86],
            ['label' => 'Siap', 'threshold' => 100],
        ])->map(function (array $stage) use ($percentage) {
            $stage['complete'] = $percentage >= $stage['threshold'];

            return $stage;
        })->all();
    }
}
