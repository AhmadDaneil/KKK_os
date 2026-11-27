<?php

namespace Tests\Feature;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WoodenHangerNameOrderTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('packageSides')]
    public function test_hanger_names_follow_each_package_side(int $packageCount, string $firstSide): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'package_type' => 'PUTERI_MANIS',
            'side' => $firstSide,
            'first_event_side' => $firstSide,
            'customer_name' => 'Hanger Preview Customer',
        ]);
        $this->get(app(GenerateOrderAccessLinkService::class)->generate($order));

        $response = $this->get(route('orders.dashboard', ['orderId' => $order->order_id]));

        $response->assertSee('Wooden Hanger');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $sections = $xpath->query('//*[@data-live-product-preview]');
        $this->assertGreaterThanOrEqual($packageCount, $sections->length);
        foreach ($sections as $section) {
            $isGroom = $section->getAttribute('data-live-product-preview') === 'LELAKI';
            $hanger = $xpath->query('.//article[contains(@class, "live-product--hanger")]', $section)->item(0);
            $this->assertNotNull($hanger);
            $copies = $xpath->query('.//*[@data-desa-generic-copy or contains(@class, "desa-product-copy-couple") or contains(@class, "hanger-couple-names")]', $hanger);
            $this->assertCount(3, $copies);
            foreach ($copies as $copy) {
                $names = $xpath->query('./*[@data-preview-field]', $copy);
                $this->assertCount(2, $names);
                $this->assertSame($isGroom ? 'groom_display' : 'bride_display', $names->item(0)->getAttribute('data-preview-field'));
                $this->assertSame($isGroom ? 'bride_display' : 'groom_display', $names->item(1)->getAttribute('data-preview-field'));
            }
        }
    }

    public static function packageSides(): array
    {
        return [
            'single bride' => [1, 'PEREMPUAN'],
            'single groom' => [1, 'LELAKI'],
            'two packages bride first' => [2, 'PEREMPUAN'],
            'two packages groom first' => [2, 'LELAKI'],
        ];
    }
}
