<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffDigitalDesignerBatchCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_csv_contains_the_ten_paid_orders_on_the_requested_queue_page(): void
    {
        $designer = User::factory()->create([
            'role' => User::ROLE_DIGITAL_DESIGNER,
            'is_active' => true,
        ]);
        Storage::fake('local');
        $this->createPaidOrders(11);

        $response = $this->actingAs($designer, 'staff')
            ->get(route('staff.digital-designer.orders.page-csv', ['page' => 1]));

        $response->assertDownload('ALIVE_CARD_MOTION_CUSTOMERS_PAGE_1.csv');

        $rows = $this->readCsvRows($response);
        $headers = array_shift($rows);
        $this->assertIsArray($headers);
        $invoiceIndex = array_search('noinvoice', $headers, true);

        $this->assertIsInt($invoiceIndex);
        $this->assertCount(10, $rows);
        $this->assertSame([
            'DD-BATCH-011',
            'DD-BATCH-010',
            'DD-BATCH-009',
            'DD-BATCH-008',
            'DD-BATCH-007',
            'DD-BATCH-006',
            'DD-BATCH-005',
            'DD-BATCH-004',
            'DD-BATCH-003',
            'DD-BATCH-002',
        ], array_map(
            static fn (array $row): string => (string) ($row[$invoiceIndex] ?? ''),
            $rows,
        ));
    }

    public function test_bulk_csv_contains_only_the_remaining_order_on_the_last_queue_page(): void
    {
        $designer = User::factory()->create([
            'role' => User::ROLE_DIGITAL_DESIGNER,
            'is_active' => true,
        ]);
        Storage::fake('local');
        $this->createPaidOrders(11);

        $response = $this->actingAs($designer, 'staff')
            ->get(route('staff.digital-designer.orders.page-csv', ['page' => 2]));

        $response->assertDownload('ALIVE_CARD_MOTION_CUSTOMERS_PAGE_2.csv');

        $rows = $this->readCsvRows($response);
        $headers = array_shift($rows);
        $this->assertIsArray($headers);
        $invoiceIndex = array_search('noinvoice', $headers, true);

        $this->assertIsInt($invoiceIndex);
        $this->assertCount(1, $rows);
        $this->assertSame('DD-BATCH-001', $rows[0][$invoiceIndex]);
    }

    public function test_queue_shows_bulk_csv_link_for_the_current_page(): void
    {
        $designer = User::factory()->create([
            'role' => User::ROLE_DIGITAL_DESIGNER,
            'is_active' => true,
        ]);
        $this->createPaidOrders(11);

        $this->actingAs($designer, 'staff')
            ->get(route('staff.digital-designer.orders.index', ['page' => 2]))
            ->assertSee('Download CSV For 10 Customer')
            ->assertSee(route('staff.digital-designer.orders.page-csv', ['page' => 2]), false);
    }

    public function test_other_staff_roles_cannot_download_digital_designer_page_csv(): void
    {
        $designer = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($designer, 'staff')
            ->get(route('staff.digital-designer.orders.page-csv'))
            ->assertForbidden();
    }

    /**
     * @return array<int, Order>
     */
    private function createPaidOrders(int $count): array
    {
        $orders = [];

        for ($number = 1; $number <= $count; $number++) {
            $orders[] = Order::create([
                'order_id' => sprintf('DD-BATCH-%03d', $number),
                'package_count' => 1,
                'customer_name' => 'Digital Designer Customer '.$number,
                'booking_payment_status' => 'PAID',
                'status' => 'DETAILS_INCOMPLETE',
            ]);
        }

        return $orders;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsvRows(\Illuminate\Testing\TestResponse $response): array
    {
        $contents = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertIsString($contents);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];

        $rows = array_map(
            static fn (string $line): array => str_getcsv($line),
            $lines,
        );
        Storage::disk('local')->deleteDirectory('digital-designer-downloads');

        return $rows;
    }
}
