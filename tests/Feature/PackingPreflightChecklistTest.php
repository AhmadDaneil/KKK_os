<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PackingJob;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use App\Services\Packing\BuildPackingChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PackingPreflightChecklistTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('packages')]
    public function test_package_checklist_is_required_before_packing_starts(string $package, int $expectedCount): void
    {
        [$order, $job, $om] = $this->packingOrder($package);
        $checklist = app(BuildPackingChecklistService::class)->build($order);
        $this->assertCount($expectedCount, $checklist);
        $this->actingAs($om, 'staff')->get(route('staff.orders.show', $order->order_id))
            ->assertSee('Package checklist before packing')
            ->assertSee('Kad Kahwin 4x6 50 pcs');

        $this->post(route('staff.packing-jobs.start', $job))->assertSessionHasErrors('packing_checks');
        $this->assertSame('READY_FOR_PACKING', $job->fresh()->status);
        $this->assertNull($job->fresh()->started_at);
        $this->assertDatabaseMissing('packing_job_events', ['packing_job_id' => $job->id, 'event_type' => 'PACKING_STARTED']);

        $checks = array_fill_keys(array_column($checklist, 'key'), '1');
        $firstKey = array_key_first($checks);
        unset($checks[$firstKey]);
        $this->post(route('staff.packing-jobs.start', $job), ['packing_checks' => $checks])
            ->assertSessionHasErrors('packing_checks.'.$firstKey);
        $this->assertSame('READY_FOR_PACKING', $job->fresh()->status);

        $checks[$firstKey] = '0';
        $this->post(route('staff.packing-jobs.start', $job), ['packing_checks' => $checks])
            ->assertSessionHasErrors('packing_checks.'.$firstKey);
        $this->assertSame('READY_FOR_PACKING', $job->fresh()->status);

        $checks[$firstKey] = '1';
        $this->post(route('staff.packing-jobs.start', $job), ['packing_checks' => $checks])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PACKING', $job->fresh()->status);
        $event = $job->events()->where('event_type', 'PACKING_STARTED')->firstOrFail();
        $this->assertSame($om->id, $event->actor_user_id);
        $this->assertSame($checklist, $event->metadata['package_checklist']);
        $this->post(route('staff.packing-jobs.start', $job))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $job->events()->where('event_type', 'PACKING_STARTED')->count());
        $this->get(route('staff.orders.show', $order->order_id))->assertSee('Package checklist confirmed before packing');
    }

    public static function packages(): array
    {
        return ['puteri manis' => ['PUTERI_MANIS', 7], 'jelata' => ['JELATA', 2]];
    }

    public function test_two_package_checklist_keeps_each_side_and_includes_physical_addons(): void
    {
        [$order] = $this->packingOrder('PUTERI_MANIS', 2);
        $order->items()->create([
            'item_type' => 'ADD_ON', 'item_code' => 'GUEST_BOOK_A4', 'description' => 'Guest Book A4',
            'quantity' => 1, 'unit_price' => 19, 'line_total' => 19,
        ]);

        $checklist = app(BuildPackingChecklistService::class)->build($order);

        $this->assertCount(10, $checklist);
        $this->assertSame([50, 30], array_values(array_column(array_filter($checklist, fn (array $row): bool => str_starts_with($row['description'], 'Kad Kahwin')), 'quantity')));
        $this->assertContains('Guest Book A4', array_column($checklist, 'description'));
        $this->assertNotContains('FREE Motion Video', array_column($checklist, 'description'));
        $this->assertNotContains('E-Card (PDF File)', array_column($checklist, 'description'));
        $this->assertCount(10, array_unique(array_column($checklist, 'key')));
    }

    public function test_empty_package_cannot_start_packing(): void
    {
        [$order, $job, $om] = $this->packingOrder('JELATA');
        $order->packageSides()->update(['card_quantity' => 0]);
        $order->update(['card_quantity' => 0]);

        $this->actingAs($om, 'staff')->post(route('staff.packing-jobs.start', $job), ['packing_checks' => ['invented' => '1']])
            ->assertSessionHasErrors('packing_job');

        $this->assertSame('READY_FOR_PACKING', $job->fresh()->status);
    }

    /** @return array{Order, PackingJob, User} */
    private function packingOrder(string $package, int $count = 1): array
    {
        $om = User::factory()->create(['role' => User::ROLE_OM, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create([
            'package_count' => $count, 'side' => 'LELAKI', 'package_type' => $package, 'customer_name' => 'Packing Customer',
        ]);
        foreach ($order->packageSides as $side) {
            $side->update(['card_quantity' => $side->side === 'LELAKI' ? 50 : 30]);
        }
        $order->update(['status' => 'READY_FOR_PACKING', 'card_quantity' => $count === 1 ? 50 : 80]);
        $job = PackingJob::create(['order_id' => $order->id, 'status' => 'READY_FOR_PACKING', 'assigned_user_id' => $om->id]);

        return [$order->fresh(), $job, $om];
    }
}
