<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DevRoutesAreLocalOnlyTest extends TestCase
{
    public function test_dev_routes_are_not_registered_outside_local_environment(): void
    {
        $this->assertFalse(app()->environment('local'));

        $devRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'dev/'));

        $this->assertCount(0, $devRoutes);
    }

    public function test_dev_order_creation_endpoint_is_not_reachable_outside_local(): void
    {
        $this->post('/dev/orders', [])
            ->assertNotFound();
    }

    public function test_customer_routes_remain_registered(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values();

        $this->assertContains('orders.dashboard', $names);
        $this->assertContains('orders.draft.update', $names);
        $this->assertContains('orders.review.show', $names);
        $this->assertContains('orders.confirm.store', $names);
        $this->assertContains('orders.artwork.review', $names);
        $this->assertContains('orders.artwork.correction', $names);
        $this->assertContains('orders.artwork.approve', $names);
    }
}
