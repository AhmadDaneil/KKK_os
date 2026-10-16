<?php

namespace Tests\Feature\Orders;

use App\Models\ArtworkVersion;
use App\Models\FulfilmentJob;
use App\Models\OrderProgressUpdate;
use App\Models\PackingJob;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderProgressVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_designer_can_upload_customer_visible_progress_for_assigned_one_package_order(): void
    {
        Storage::fake('local');
        $designer = $this->staff(User::ROLE_DESIGNER);
        $order = $this->orderWithDesignJobs(1);
        $order->designJobs()->firstOrFail()->update(['assigned_user_id' => $designer->id]);

        $this->actingAs($designer, 'staff')
            ->post(route('staff.orders.progress-updates.store', $order), [
                'message' => 'Susun atur muka hadapan sedang disiapkan.',
                'attachment' => UploadedFile::fake()->image('design-progress.jpg'),
                'customer_visible' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $update = OrderProgressUpdate::sole();
        $this->assertSame($designer->id, $update->uploader_user_id);
        $this->assertSame(User::ROLE_DESIGNER, $update->uploader_role);
        $this->assertSame($order->status, $update->order_status);
        $this->assertTrue($update->isCustomerVisible());
        $this->assertNotNull($update->attachment_sha256);
        Storage::disk('local')->assertExists($update->attachment_path);
    }

    public function test_two_package_designer_can_upload_only_when_assigned_to_at_least_one_side(): void
    {
        $assigned = $this->staff(User::ROLE_DESIGNER);
        $unassigned = $this->staff(User::ROLE_DESIGNER);
        $order = $this->orderWithDesignJobs(2);
        $order->designJobs()->orderBy('id')->firstOrFail()->update(['assigned_user_id' => $assigned->id]);

        $this->actingAs($assigned, 'staff')
            ->post(route('staff.orders.progress-updates.store', $order), [
                'message' => 'Progress pakej yang ditugaskan.',
            ])
            ->assertRedirect();

        $this->actingAs($unassigned, 'staff')
            ->post(route('staff.orders.progress-updates.store', $order), [
                'message' => 'Tidak dibenarkan.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('order_progress_updates', 1);
    }

    public function test_production_and_om_uploads_are_scoped_to_their_operational_orders(): void
    {
        $production = $this->staff(User::ROLE_PRODUCTION);
        $otherProduction = $this->staff(User::ROLE_PRODUCTION);
        $om = $this->staff(User::ROLE_OM);
        $productionOrder = $this->orderWithDesignJobs(1);
        $designJob = $productionOrder->designJobs()->firstOrFail();
        $artwork = ArtworkVersion::create([
            'design_job_id' => $designJob->id,
            'version_number' => 1,
            'storage_path' => 'test/artwork.pdf',
        ]);
        PrintJob::create([
            'order_id' => $productionOrder->id,
            'design_job_id' => $designJob->id,
            'order_package_side_id' => $designJob->order_package_side_id,
            'artwork_version_id' => $artwork->id,
            'side' => $designJob->side,
            'status' => 'PRINTING',
            'assigned_user_id' => $production->id,
        ]);
        $packingOrder = $this->confirmedOrder(2);
        PackingJob::create([
            'order_id' => $packingOrder->id,
            'status' => 'PACKING',
            'assigned_user_id' => $om->id,
        ]);

        $this->actingAs($production, 'staff')
            ->post(route('staff.orders.progress-updates.store', $productionOrder), [
                'message' => 'Cetakan warna sedang berjalan.',
                'customer_visible' => '1',
            ])->assertRedirect();
        $this->actingAs($otherProduction, 'staff')
            ->post(route('staff.orders.progress-updates.store', $productionOrder), [
                'message' => 'Bukan tugasan saya.',
            ])->assertNotFound();
        $this->actingAs($om, 'staff')
            ->post(route('staff.orders.progress-updates.store', $packingOrder), [
                'message' => 'Dua pakej sedang dibungkus.',
                'customer_visible' => '1',
            ])->assertRedirect();

        $this->assertDatabaseCount('order_progress_updates', 2);
    }

    public function test_customer_service_and_admin_cannot_use_staff_progress_upload_endpoint(): void
    {
        $order = $this->orderWithDesignJobs(1);

        foreach ([User::ROLE_CUSTOMER_SERVICE => 403, User::ROLE_ADMIN => 404] as $role => $expectedStatus) {
            $this->actingAs($this->staff($role), 'staff')
                ->post(route('staff.orders.progress-updates.store', $order), ['message' => 'Tidak dibenarkan'])
                ->assertStatus($expectedStatus);
        }

        $this->assertDatabaseCount('order_progress_updates', 0);
    }

    public function test_progress_is_private_by_default_and_duplicate_input_is_rejected(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $order = $this->orderWithDesignJobs(1);
        $order->designJobs()->firstOrFail()->update(['assigned_user_id' => $designer->id]);
        $payload = ['message' => 'Semakan internal sahaja.'];

        $this->actingAs($designer, 'staff')
            ->post(route('staff.orders.progress-updates.store', $order), $payload)
            ->assertRedirect();
        $this->actingAs($designer, 'staff')
            ->post(route('staff.orders.progress-updates.store', $order), $payload)
            ->assertSessionHasErrors('progress_update');

        $update = OrderProgressUpdate::sole();
        $this->assertSame(OrderProgressUpdate::VISIBILITY_INTERNAL, $update->visibility);
        $this->assertNull($update->published_at);
    }

    public function test_admin_om_and_customer_service_can_view_updates_from_all_uploader_roles(): void
    {
        $order = $this->confirmedOrder(1);
        foreach ([User::ROLE_DESIGNER, User::ROLE_PRODUCTION, User::ROLE_OM] as $index => $role) {
            $uploader = $this->staff($role);
            $this->makeUpdate($order, $uploader, "Audit update {$index}", false);
        }

        $admin = $this->staff(User::ROLE_ADMIN);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Audit update 0')
            ->assertSee('Audit update 1')
            ->assertSee('Audit update 2');

        $om = $this->staff(User::ROLE_OM);
        $this->actingAs($om, 'staff')
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Audit update 0')
            ->assertSee('Audit update 1')
            ->assertSee('Audit update 2');

        $customerService = $this->staff(User::ROLE_CUSTOMER_SERVICE);
        $this->actingAs($customerService, 'staff')
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Audit update 0')
            ->assertSee('Audit update 1')
            ->assertSee('Audit update 2');
    }

    public function test_customer_dashboard_shows_only_published_updates_for_its_own_order(): void
    {
        $order = $this->confirmedOrder(1);
        $otherOrder = $this->confirmedOrder(2);
        $designer = $this->staff(User::ROLE_DESIGNER);
        $production = $this->staff(User::ROLE_PRODUCTION);
        $om = $this->staff(User::ROLE_OM);
        $customerService = $this->staff(User::ROLE_CUSTOMER_SERVICE);
        $designProof = $this->makeUpdate(
            $order,
            $designer,
            'Design customer-visible',
            true,
            'progress/design-customer-visible.jpg',
        );
        $productionProof = $this->makeUpdate(
            $order,
            $production,
            'Production customer-visible',
            true,
            'progress/production-customer-visible.jpg',
        );
        $omProof = $this->makeUpdate(
            $order,
            $om,
            'OM customer-visible',
            true,
            'progress/om-customer-visible.jpg',
        );
        $this->makeUpdate($order, $om, 'OM internal secret', false);
        $this->makeUpdate($order, $customerService, 'Unsupported publisher secret', true);
        $this->makeUpdate($otherOrder, $om, 'Other customer secret', true);

        $this->withSession($this->customerSession($order))
            ->get(route('orders.dashboard', $order->order_id))
            ->assertOk()
            ->assertSee('Progress &amp; Proofing Tempahan Anda', false)
            ->assertSee('Design customer-visible')
            ->assertSee('Production customer-visible')
            ->assertSee('OM customer-visible')
            ->assertSee('Lihat Proofing')
            ->assertSee(
                route('orders.progress-updates.attachment', [$order->order_id, $designProof]),
                false,
            )
            ->assertSee(
                route('orders.progress-updates.attachment', [$order->order_id, $productionProof]),
                false,
            )
            ->assertSee(
                route('orders.progress-updates.attachment', [$order->order_id, $omProof]),
                false,
            )
            ->assertDontSee('OM internal secret')
            ->assertDontSee('Unsupported publisher secret')
            ->assertDontSee('Other customer secret');
    }

    public function test_customer_dashboard_shows_an_empty_progress_and_proofing_space(): void
    {
        $order = $this->confirmedOrder(1);

        $this->withSession($this->customerSession($order))
            ->get(route('orders.dashboard', $order->order_id))
            ->assertOk()
            ->assertSee('Progress &amp; Proofing Tempahan Anda', false)
            ->assertSee('Belum ada progress atau proofing yang dikongsi untuk tempahan ini.')
            ->assertDontSee('Lihat Proofing');
    }

    public function test_staff_attachment_is_available_only_to_management_or_the_uploader(): void
    {
        Storage::fake('local');
        $order = $this->confirmedOrder(1);
        $uploader = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $update = $this->makeUpdate($order, $uploader, 'Private attachment', false, 'progress/private-staff.jpg');
        Storage::disk('local')->put($update->attachment_path, 'private');

        $this->actingAs($uploader, 'staff')
            ->get(route('staff.progress-updates.attachment', $update))
            ->assertOk();
        $this->actingAs($this->staff(User::ROLE_OM), 'staff')
            ->get(route('staff.progress-updates.attachment', $update))
            ->assertOk();
        $this->actingAs($this->staff(User::ROLE_ADMIN), 'admin')
            ->get(route('admin.progress-updates.attachment', $update))
            ->assertOk();
        $this->actingAs($otherDesigner, 'staff')
            ->get(route('staff.progress-updates.attachment', $update))
            ->assertNotFound();
        $this->actingAs($this->staff(User::ROLE_CUSTOMER_SERVICE), 'staff')
            ->get(route('staff.progress-updates.attachment', $update))
            ->assertOk();
    }

    public function test_customer_attachment_access_enforces_order_and_visibility_scope(): void
    {
        Storage::fake('local');
        $order = $this->confirmedOrder(1);
        $otherOrder = $this->confirmedOrder(1);
        $designer = $this->staff(User::ROLE_DESIGNER);
        $visible = $this->makeUpdate($order, $designer, 'Visible file', true, 'progress/visible.jpg');
        $private = $this->makeUpdate($order, $designer, 'Private file', false, 'progress/private.jpg');
        $other = $this->makeUpdate($otherOrder, $designer, 'Other file', true, 'progress/other.jpg');
        Storage::disk('local')->put($visible->attachment_path, 'visible');
        Storage::disk('local')->put($private->attachment_path, 'private');
        Storage::disk('local')->put($other->attachment_path, 'other');

        $session = $this->customerSession($order);
        $this->withSession($session)
            ->get(route('orders.progress-updates.attachment', [$order->order_id, $visible]))
            ->assertOk();
        $this->withSession($session)
            ->get(route('orders.progress-updates.attachment', [$order->order_id, $private]))
            ->assertNotFound();
        $this->withSession($session)
            ->get(route('orders.progress-updates.attachment', [$order->order_id, $other]))
            ->assertNotFound();
    }

    public function test_admin_om_and_customer_can_view_design_and_courier_proofs_for_the_order(): void
    {
        Storage::fake('local');
        $order = $this->orderWithDesignJobs(1);
        $designJob = $order->designJobs()->firstOrFail();
        $designJob->update(['status' => 'DESIGN_APPROVED']);
        $artwork = ArtworkVersion::create([
            'design_job_id' => $designJob->id,
            'version_number' => 1,
            'storage_path' => 'private/source.pdf',
            'preview_storage_path' => 'proofs/design-preview.jpg',
        ]);
        Storage::disk('local')->put($artwork->preview_storage_path, 'design-proof');

        $order->fulfilment()->update([
            'method' => 'COURIER',
            'recipient_name' => 'Customer Proof',
            'recipient_phone' => '0123456789',
            'shipping_address' => 'Alamat Customer',
        ]);
        $packingJob = PackingJob::create([
            'order_id' => $order->id,
            'status' => 'PACKED',
            'proof_storage_path' => 'proofs/courier-proof.jpg',
            'proof_original_name' => 'courier-proof.jpg',
            'proof_mime_type' => 'image/jpeg',
        ]);
        FulfilmentJob::create([
            'order_id' => $order->id,
            'order_fulfilment_id' => $order->fulfilment->id,
            'packing_job_id' => $packingJob->id,
            'method' => 'COURIER',
            'status' => 'COMPLETED',
            'courier_provider' => 'Pos Laju',
            'tracking_number' => 'PL123MY',
        ]);
        Storage::disk('local')->put($packingJob->proof_storage_path, 'courier-proof');

        $admin = $this->staff(User::ROLE_ADMIN);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Bukti Designer')
            ->assertSee('Bukti Courier');
        $this->actingAs($admin, 'admin')
            ->get(route('admin.design-jobs.proof.show', $designJob))
            ->assertOk();

        $om = $this->staff(User::ROLE_OM);
        $this->actingAs($om, 'staff')
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Bukti Designer')
            ->assertSee('Bukti Courier');
        $this->actingAs($om, 'staff')
            ->get(route('staff.design-jobs.proof.show', $designJob))
            ->assertOk();

        $customerService = $this->staff(User::ROLE_CUSTOMER_SERVICE);
        $this->actingAs($customerService, 'staff')
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertDontSee('Bukti Designer')
            ->assertDontSee('Bukti Courier');
        $this->actingAs($customerService, 'staff')
            ->get(route('staff.design-jobs.proof.show', $designJob))
            ->assertNotFound();

        $session = $this->customerSession($order);
        $this->withSession($session)
            ->get(route('orders.dashboard', $order->order_id))
            ->assertOk()
            ->assertSee('Lihat Bukti Designer')
            ->assertSee('Lihat Bukti Courier');
        $this->withSession($session)
            ->get(route('orders.courier-proof.show', $order->order_id))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_two_package_customer_sees_a_design_proof_button_for_each_side(): void
    {
        Storage::fake('local');
        $order = $this->orderWithDesignJobs(2);
        $designer = $this->staff(User::ROLE_DESIGNER);
        $progressProof = $this->makeUpdate(
            $order,
            $designer,
            'Proofing progress dua pakej',
            true,
            'progress/two-package-proofing.jpg',
        );
        foreach ($order->designJobs()->get() as $index => $designJob) {
            $designJob->update(['status' => 'DESIGN_APPROVED']);
            $path = 'proofs/design-'.$designJob->side.'.jpg';
            ArtworkVersion::create([
                'design_job_id' => $designJob->id,
                'version_number' => 1,
                'storage_path' => 'private/source-'.$index.'.pdf',
                'preview_storage_path' => $path,
            ]);
            Storage::disk('local')->put($path, 'design-proof-'.$index);
        }

        $response = $this->withSession($this->customerSession($order))
            ->get(route('orders.dashboard', $order->order_id))
            ->assertOk()
            ->assertSee('Lihat Bukti Designer · Lelaki')
            ->assertSee('Lihat Bukti Designer · Perempuan')
            ->assertSee('Proofing progress dua pakej')
            ->assertSee('Lihat Proofing')
            ->assertSee(
                route('orders.progress-updates.attachment', [$order->order_id, $progressProof]),
                false,
            );

        $this->assertSame(2, substr_count($response->getContent(), 'Lihat Bukti Designer'));
    }

    public function test_customer_cannot_view_courier_proof_before_courier_completion(): void
    {
        Storage::fake('local');
        $order = $this->confirmedOrder(1);
        $order->fulfilment()->update([
            'method' => 'COURIER',
            'recipient_name' => 'Customer Proof',
            'recipient_phone' => '0123456789',
            'shipping_address' => 'Alamat Customer',
        ]);
        $packingJob = PackingJob::create([
            'order_id' => $order->id,
            'status' => 'PACKED',
            'proof_storage_path' => 'proofs/not-yet-courier.jpg',
            'proof_original_name' => 'not-yet-courier.jpg',
            'proof_mime_type' => 'image/jpeg',
        ]);
        FulfilmentJob::create([
            'order_id' => $order->id,
            'order_fulfilment_id' => $order->fulfilment->id,
            'packing_job_id' => $packingJob->id,
            'method' => 'COURIER',
            'status' => 'READY',
        ]);
        Storage::disk('local')->put($packingJob->proof_storage_path, 'packing-proof');

        $this->withSession($this->customerSession($order))
            ->get(route('orders.courier-proof.show', $order->order_id))
            ->assertNotFound();
        $this->withSession($this->customerSession($order))
            ->get(route('orders.dashboard', $order->order_id))
            ->assertOk()
            ->assertDontSee('Lihat Bukti Courier');
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function orderWithDesignJobs(int $packageCount)
    {
        User::query()
            ->where('role', User::ROLE_DESIGNER)
            ->where('is_active', true)
            ->first()
            ?? User::factory()->create([
                'role' => User::ROLE_DESIGNER,
                'is_active' => true,
                'is_design_lead' => true,
            ]);

        $order = $this->confirmedOrder($packageCount);
        app(GenerateMergeJobsForOrderService::class)->generate($order);
        app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        return $order->fresh();
    }

    private function confirmedOrder(int $packageCount)
    {
        $this->seed(KkkPackagePriceCatalogSeeder::class);

        $data = [
            'package_count' => $packageCount,
            'package_type' => 'PUTERI_MANIS',
            'customer_name' => 'Progress Visibility Test',
        ];
        if ($packageCount === 1) {
            $data['side'] = 'LELAKI';
        }
        $order = app(CreateOrderService::class)->create($data);
        $sides = [];
        foreach ($order->packageSides as $side) {
            $sides[$side->side] = [
                'card_quantity' => 200,
                'design' => ['theme' => 'SONGKET', 'design_code' => 'T101'],
                'parents' => ['father_name' => 'Bapa', 'mother_name' => 'Ibu'],
                'event' => [
                    'event_date' => '2027-01-20',
                    'meal_time' => '12:00',
                    'venue_name' => 'Dewan Ujian',
                    'full_address' => 'Alamat Ujian',
                    'contacts' => [
                        1 => ['contact_name' => 'Satu', 'contact_phone' => '0111111111'],
                        2 => ['contact_name' => 'Dua', 'contact_phone' => '0122222222'],
                        3 => ['contact_name' => 'Tiga', 'contact_phone' => '0133333333'],
                    ],
                ],
            ];
        }
        $draft = [
            'card_quantity' => 200,
            'couple' => ['groom_name' => 'Ali', 'bride_name' => 'Aminah'],
            'sides' => $sides,
            'fulfilment' => ['method' => 'PICKUP'],
        ];

        if ($packageCount === 2) {
            $draft['jumlah_hantaran'] = '10 dinar emas';
        }

        app(SaveOrderDraftService::class)->save($order, $draft);

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }

    private function makeUpdate($order, User $uploader, string $message, bool $visible, ?string $path = null): OrderProgressUpdate
    {
        return OrderProgressUpdate::create([
            'order_id' => $order->id,
            'uploader_user_id' => $uploader->id,
            'uploader_role' => $uploader->role,
            'order_status' => $order->status,
            'message' => $message,
            'visibility' => $visible ? OrderProgressUpdate::VISIBILITY_CUSTOMER : OrderProgressUpdate::VISIBILITY_INTERNAL,
            'published_at' => $visible ? now() : null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_original_name' => $path ? basename($path) : null,
            'attachment_mime_type' => $path ? 'image/jpeg' : null,
            'attachment_size' => $path ? 7 : null,
            'attachment_sha256' => $path ? hash('sha256', $path) : null,
            'deduplication_key' => hash('sha256', $order->id.'|'.$uploader->id.'|'.$message),
        ]);
    }

    private function customerSession($order): array
    {
        return [
            'kkk.customer_orders.'.$order->order_id => [
                'order_fk' => $order->id,
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ];
    }
}
