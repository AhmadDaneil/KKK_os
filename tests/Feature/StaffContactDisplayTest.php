<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffContactDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_contact_names_and_phones_appear_in_order_details(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI']);
        app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'LELAKI' => [
                    'event' => [
                        'contacts' => [
                            1 => ['contact_name' => 'Awi', 'contact_phone' => '0123456789'],
                            2 => ['contact_name' => 'Ayie', 'contact_phone' => '0198765432'],
                            3 => ['contact_name' => 'Liya', 'contact_phone' => '01122334455'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertSeeTextInOrder(['Contacts', 'Awi', '0123456789', 'Ayie', '0198765432', 'Liya', '01122334455']);
    }
}
