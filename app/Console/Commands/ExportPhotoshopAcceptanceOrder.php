<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrderService;
use Illuminate\Console\Command;
use RuntimeException;

class ExportPhotoshopAcceptanceOrder extends Command
{
    protected $signature = 'photoshop:acceptance-export
        {orderId : Business order ID, e.g. KKK-260910-0001}
        {--output= : Absolute or project-relative output directory}';

    protected $description = 'Export one confirmed KKK OS order as a Photoshop acceptance-test CSV plus manifest.';

    public function handle(
        GenerateMergeJobsForOrderService $generateMergeJobs,
        GeneratePhotoshopCsvForOrderService $generateCsv,
    ): int {
        $order = Order::query()
            ->where('order_id', $this->argument('orderId'))
            ->first();

        if (! $order) {
            $this->error('Order not found.');
            return self::FAILURE;
        }

        $order->load([
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
            'confirmation',
        ]);

        if (! $order->details_confirmed_at) {
            $this->error('Order is not confirmed. Acceptance export is blocked.');
            return self::FAILURE;
        }

        if (! $order->card_quantity || (int) $order->card_quantity < 1) {
            $this->error('Order has no valid card_quantity. Acceptance export is blocked.');
            return self::FAILURE;
        }

        $expectedSides = $order->packageSides
            ->pluck('side')
            ->sort()
            ->values()
            ->all();

        if (count($expectedSides) !== (int) $order->package_count) {
            $this->error('Package-side count does not match package_count.');
            return self::FAILURE;
        }

        $jobs = $generateMergeJobs->generate($order->fresh());

        if ($jobs->count() !== (int) $order->package_count) {
            $this->error('Merge-job count does not match package_count.');
            return self::FAILURE;
        }

        $base = $this->option('output');

        if (! $base) {
            $base = storage_path('app/photoshop-acceptance/' . $order->order_id);
        } elseif (! str_starts_with($base, DIRECTORY_SEPARATOR)
            && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $base)) {
            $base = base_path($base);
        }

        if (! is_dir($base) && ! mkdir($base, 0775, true) && ! is_dir($base)) {
            throw new RuntimeException("Could not create output directory: {$base}");
        }

        $csvPath = $base . DIRECTORY_SEPARATOR . $order->order_id . '_READY_TO_MERGE.csv';
        $manifestPath = $base . DIRECTORY_SEPARATOR . $order->order_id . '_acceptance_manifest.json';

        $generateCsv->generate($order->fresh(), $csvPath);

        $jobs = $order->fresh()->mergeJobs()->orderBy('side')->get();

        $manifest = [
            'acceptance_schema_version' => 'photoshop_acceptance_v1',
            'generated_at' => now()->toISOString(),
            'order' => [
                'order_id' => $order->order_id,
                'package_count' => (int) $order->package_count,
                'card_quantity' => (int) $order->card_quantity,
                'details_confirmed_at' => optional($order->details_confirmed_at)?->toISOString(),
            ],
            'expected' => [
                'csv_header_count' => 28,
                'csv_data_rows' => (int) $order->package_count,
                'package_sides' => $expectedSides,
                'same_qtykad_for_all_rows' => true,
                'photoshop_stage_folder' => '1 Waiting Customer',
                'per_customer_directories' => ['Export JPEG', 'PSD', 'QR'],
            ],
            'merge_jobs' => $jobs->map(fn ($job) => [
                'job_id' => $job->job_id,
                'side' => $job->side,
                'theme' => data_get($job->canonical_payload, 'design.theme'),
                'design_code' => data_get($job->canonical_payload, 'design.design_code'),
                'qtykad' => data_get($job->canonical_payload, 'source.card_quantity'),
                'qrlink' => data_get($job->canonical_payload, 'event.google_maps_url'),
            ])->values()->all(),
            'files' => [
                'csv' => $csvPath,
            ],
        ];

        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->newLine();
        $this->info('Photoshop acceptance package generated.');
        $this->line('Order: ' . $order->order_id);
        $this->line('Package count: ' . $order->package_count);
        $this->line('Card quantity: ' . $order->card_quantity);
        $this->line('CSV: ' . $csvPath);
        $this->line('Manifest: ' . $manifestPath);
        $this->newLine();

        $this->warn('Run this CSV using the verified KKK Photoshop JSX and the real MASTER template folder.');
        $this->warn('Do not edit the generated CSV manually before the acceptance test.');

        return self::SUCCESS;
    }
}
