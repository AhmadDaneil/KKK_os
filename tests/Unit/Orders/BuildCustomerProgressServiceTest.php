<?php

namespace Tests\Unit\Orders;

use App\Models\Order;
use App\Services\Orders\BuildCustomerProgressService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuildCustomerProgressServiceTest extends TestCase
{
    #[DataProvider('progressStatuses')]
    public function test_status_maps_to_expected_percentage_and_colour(
        string $status,
        int $percentage,
        string $tone,
    ): void {
        $order = new Order(['status' => $status]);

        $progress = (new BuildCustomerProgressService)->build($order);

        $this->assertSame($percentage, $progress['percentage']);
        $this->assertSame($tone, $progress['tone']);
    }

    public static function progressStatuses(): array
    {
        return [
            'red at 35 percent' => ['DETAILS_CONFIRMED', 35, 'red'],
            'yellow starts at 40 percent' => ['READY_FOR_DESIGN', 40, 'yellow'],
            'yellow below 80 percent' => ['BALANCE_PENDING', 75, 'yellow'],
            'green starts at 80 percent' => ['PAID', 80, 'green'],
            'green at completion' => ['COMPLETED', 100, 'green'],
        ];
    }

    public function test_it_returns_the_established_nine_step_customer_lifecycle(): void
    {
        $progress = (new BuildCustomerProgressService)->build(
            new Order(['status' => 'BALANCE_PENDING'])
        );

        $this->assertSame([
            'Tempahan',
            'Maklumat',
            'Design',
            'Kelulusan',
            'Bayaran Baki',
            'Cetakan',
            'Pembungkusan',
            'Penghantaran / Pengambilan',
            'Selesai',
        ], array_column($progress['stages'], 'label'));
        $this->assertSame('complete', $progress['stages'][3]['state']);
        $this->assertSame('current', $progress['stages'][4]['state']);
        $this->assertSame('pending', $progress['stages'][5]['state']);
    }
}
