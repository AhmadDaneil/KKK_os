<?php

namespace App\Services\Orders;

use App\Models\Order;

class BuildCustomerProgressService
{
    public function build(Order $order): array
    {
        $status = $order->status;

        [$percentage, $label, $message] = match ($status) {
            'BOOKING_PENDING' => [5, 'Order Processing', 'Your order is being processed.'],
            'BOOKED', 'DEPOSIT_PAID' => [10, 'Order Received', 'Your order has been received.'],
            'DETAILS_INCOMPLETE' => [20, 'Details Incomplete', 'Complete the required details before confirming your order.'],
            'DETAILS_CONFIRMED' => [35, 'Awaiting Deposit Confirmation', 'Your deposit is awaiting staff review.'],
            'READY_FOR_DESIGN' => [40, 'Awaiting Design', 'Your details have been received and are ready for design.'],
            'DESIGN_IN_PROGRESS' => [50, ...$this->designProgressCopy($order)],
            'DESIGN_READY' => [60, 'Internal Design Review', 'Our team is reviewing the design.'],
            'CORRECTION_REQUESTED' => [60, 'Design Corrections in Progress', 'Your correction request has been received.'],
            'DESIGN_APPROVED' => [70, 'Design Approved', 'The design has been approved internally. Please pay the remaining balance to proceed to printing.'],
            'BALANCE_PENDING' => [75, 'Awaiting Balance Payment', 'The remaining balance is required before the next step.'],
            'PAID' => [80, 'Payment Complete', 'Your order has been paid in full.'],
            'READY_FOR_PRINT' => [82, 'Awaiting Printing', 'Your order is in the printing queue.'],
            'PRINTING' => [86, 'Printing in Progress', 'Your order is being printed.'],
            'PRINTED' => [88, 'Printing Complete', 'Your order has been printed and will proceed to packing.'],
            'READY_FOR_PACKING' => [90, 'Awaiting Packing', 'Your order is waiting to be packed.'],
            'PACKING' => [92, 'Packing in Progress', 'Your order is being packed.'],
            'PACKED' => [95, 'Packing Complete', 'Your order has been packed.'],
            'READY_FOR_PICKUP' => [95, 'Ready for Pickup', 'Your order is ready for collection.'],
            'SHIPPED' => [95, 'Shipped', 'Your order has been handed to the courier.'],
            'COMPLETED' => [100, 'Order Complete', 'Your order is complete.'],
            'CANCELLED' => [0, 'CANCELLED', 'This order has been cancelled.'],
            'ARCHIVED' => [100, 'Order Archived', 'This order has been archived.'],
            default => [0, 'Order Status', 'Your order status is being updated.'],
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
        $labels = ['LELAKI' => "groom's side", 'PEREMPUAN' => "bride's side"];
        $active = collect($labels)->filter(fn ($label, $side) => isset($sides[$side]))->values();

        if ($active->isEmpty()) {
            return ['Design in Progress', 'Our designer is preparing your order.'];
        }

        $subject = $active->count() > 1 ? "the groom's and bride's sides" : $active->first();

        return ["Design in Progress: {$subject}", "Our designer is preparing {$subject} for your order."];
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
            : ['Order', 'Details', 'Deposit Confirmation', 'Design', 'Approval', 'Balance Payment', 'Printing', 'Packing', 'Delivery / Pickup', 'Complete'];

        return collect([
            ['label' => 'Order', 'description' => 'Order received'],
            ['label' => 'Details', 'description' => 'Details confirmed'],
            ['label' => 'Deposit Confirmation', 'description' => 'Deposit reviewed and confirmed'],
            ['label' => 'Design', 'description' => 'Design being prepared'],
            ['label' => 'Approval', 'description' => 'Internal design review'],
            ['label' => 'Balance Payment', 'description' => 'Full payment'],
            ['label' => 'Printing', 'description' => 'Cards printed'],
            ['label' => 'Packing', 'description' => 'Cards packed'],
            ['label' => 'Delivery / Pickup', 'description' => 'Shipped or collected'],
            ['label' => 'Complete', 'description' => 'Order complete'],
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
