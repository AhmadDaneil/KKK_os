<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminInvoiceNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('operationsPages')]
    public function test_sales_invoice_remains_in_the_admin_operations_sidebar(array $filters): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', $filters))
            ->assertSee('Sales Invoice')
            ->assertSee('href="'.route('admin.invoices.index').'"', false);
    }

    public static function operationsPages(): array
    {
        return [
            'all orders' => [[]],
            'payment review' => [['attention' => 'pending_payment']],
            'design queue' => [['workstream' => 'design']],
            'production queue' => [['workstream' => 'printing']],
            'packing queue' => [['workstream' => 'packing']],
        ];
    }
}
