<?php

namespace Tests\Feature;

use App\Models\MergeJob;
use App\Models\User;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DesignAssignmentControlTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('portals')]
    public function test_management_can_reassign_the_default_leader_job(string $portal, string $role): void
    {
        $manager = User::factory()->create(['role' => $role, 'is_active' => true]);
        $leader = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true, 'is_design_lead' => true]);
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true, 'is_design_lead' => false]);
        $inactive = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => false]);
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'PEREMPUAN', 'customer_name' => 'Assignment Customer']);
        $order->update(['status' => 'DETAILS_CONFIRMED']);
        MergeJob::create([
            'job_id' => 'MERGE-ASSIGNMENT', 'order_id' => $order->id,
            'order_package_side_id' => $order->packageSides->first()->id,
            'side' => 'PEREMPUAN', 'canonical_payload' => [], 'generated_at' => now(),
        ]);
        $job = app(InitializeDesignJobsForOrderService::class)->initialize($order)->first();
        $this->assertSame($leader->id, $job->assigned_user_id);
        $this->actingAs($manager, $portal)->get(route($portal.'.orders.show', $order->order_id))
            ->assertSee('Assign Design')
            ->assertSee('Leader Designer')
            ->assertSee('value="'.$designer->id.'"', false)
            ->assertDontSee('value="'.$inactive->id.'"', false);

        foreach ([$designer, $leader] as $assignee) {
            $this->post(route($portal.'.design-jobs.assign', $job), ['assigned_user_id' => $assignee->id])->assertRedirect();
            $this->assertSame($assignee->id, $job->fresh()->assigned_user_id);
            $this->assertDatabaseHas('design_job_events', ['design_job_id' => $job->id, 'event_type' => 'DESIGNER_ASSIGNED', 'actor_user_id' => $manager->id]);
        }
    }

    public static function portals(): array
    {
        return ['admin' => ['admin', User::ROLE_ADMIN], 'operations' => ['staff', User::ROLE_OM]];
    }
}
