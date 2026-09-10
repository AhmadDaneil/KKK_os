<?php

namespace App\Services\Photoshop;

use App\Contracts\Photoshop\CardQuantityProviderContract;
use App\Models\Order;
use Illuminate\Support\Collection;
use RuntimeException;

class GeneratePhotoshopCsvForOrderService
{
    public function __construct(
        private readonly ExportPhotoshopAutoMergeCsvService $csvExporter,
        private readonly CardQuantityProviderContract $quantityProvider,
    ) {
    }

    /**
     * Produce the real Photoshop-compatible CSV for one confirmed business order.
     *
     * 1-package => exactly 1 merge row.
     * 2-package => exactly 2 independent merge rows.
     */
    public function generate(Order $order, string $absolutePath): string
    {
        $order->refresh();
        $order->load('packageSides');

        if ($order->details_confirmed_at === null) {
            throw new RuntimeException(
                "Order {$order->order_id} must be confirmed before Photoshop export."
            );
        }

        $mergeJobs = $order->mergeJobs()
            ->orderBy('side')
            ->get();

        $expected = (int) $order->package_count;

        if ($mergeJobs->count() !== $expected) {
            throw new RuntimeException(
                "Order {$order->order_id} expected {$expected} merge job(s), " .
                "but {$mergeJobs->count()} exist."
            );
        }

        $this->assertMergeJobsMatchPackageSides($order, $mergeJobs);

        return $this->csvExporter->export(
            $mergeJobs,
            $absolutePath,
            fn ($mergeJob) => $this->quantityProvider->quantityFor($mergeJob),
        );
    }

    private function assertMergeJobsMatchPackageSides(
        Order $order,
        Collection $mergeJobs,
    ): void {
        $sideIds = $order->packageSides
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $jobSideIds = $mergeJobs
            ->pluck('order_package_side_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($sideIds !== $jobSideIds) {
            throw new RuntimeException(
                "Merge jobs for order {$order->order_id} do not exactly match its package sides."
            );
        }
    }
}
