<?php

namespace Tests\Unit\Invoices;

use App\Models\Order;
use App\Services\Invoices\GenerateInvoiceNumberService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GenerateInvoiceNumberServiceTest extends TestCase
{
    #[DataProvider('brandPrefixes')]
    public function test_invoice_number_uses_requested_brand_prefix(
        string $source,
        ?string $channel,
        string $servicePhone,
        string $customerReference,
        string $expected,
    ): void {
        $order = new Order([
            'source' => $source,
            'wabot_channel' => $channel,
            'serving_phone_number' => $servicePhone,
        ]);

        $this->assertSame(
            $expected,
            app(GenerateInvoiceNumberService::class)->generate($order, $customerReference),
        );
    }

    public static function brandPrefixes(): array
    {
        return [
            'KingKadKahwin' => ['DIRECT', null, '601165656968', 'KKK261019-001', 'KKK261019-001'],
            'Destinasi Raja Sehari' => ['WABOT', 'destinasi-raja-sehari', '60177197596', 'DRS261019-001', 'DRS261019-001'],
            'Royalicious' => ['WABOT', 'royalicious', '601135623239', 'RYC261019-001', 'RYL261019-001'],
            'Cetaking' => ['WABOT', 'cetaking', '60175863239', 'CK261019-001', 'CK261019-001'],
        ];
    }

    public function test_invoice_number_rejects_a_reference_from_another_brand(): void
    {
        $order = new Order([
            'source' => 'WABOT',
            'wabot_channel' => 'destinasi-raja-sehari',
            'serving_phone_number' => '60177197596',
        ]);

        $this->expectException(RuntimeException::class);
        app(GenerateInvoiceNumberService::class)->generate($order, 'CK261019-001');
    }

    public function test_invoice_prefix_uses_saved_serving_phone_instead_of_channel_guess(): void
    {
        $order = new Order([
            'source' => 'WABOT',
            'wabot_channel' => 'kingkadkahwin',
            'serving_phone_number' => '+60 11-3562 3239',
        ]);

        $this->assertSame(
            'RYL261019-001',
            app(GenerateInvoiceNumberService::class)->generate($order, 'KKK261019-001'),
        );
    }
}
