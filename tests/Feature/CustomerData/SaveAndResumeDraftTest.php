<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaveAndResumeDraftTest extends TestCase
{
    use RefreshDatabase;

    public function save(Order $order, array $data): Order
{
    if ($order->status !== 'DETAILS_INCOMPLETE') {
        throw ValidationException::withMessages([
            'order' => 'Maklumat tempahan yang telah disahkan tidak boleh diubah.',
        ]);
    }

    $newUploadedPaths = [];
    $oldPathsToDelete = [];
}

    public function test_one_package_draft_saves_normalized_data_and_keeps_order_incomplete(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Draft Test',
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => '  Muhammad   Syafiq  ',
                'bride_name' => ' Nur   Awanis ',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => ' a101 ',
                    ],
                    'parents' => [
                        'father_name' => '  Abdullah   bin Ali ',
                        'mother_name' => ' Aminah ',
                    ],
                    'event' => [
                        'venue_name' => ' Dewan   Seri ',
                        'full_address' => " No. 1   Jalan A \n Kuala Lumpur ",
                        'contacts' => [
                            1 => ['contact_name' => ' Ahmad ', 'contact_phone' => '012-345 6789'],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'COURIER',
                'recipient_name' => ' Ali ',
                'recipient_phone' => '019-111 2222',
                'shipping_address' => " Jalan   Satu\n Kuala Lumpur ",
            ],
        ]);

        $this->assertSame('DETAILS_INCOMPLETE', $saved->status);
        $this->assertNull($saved->details_confirmed_at);
        $this->assertSame('Muhammad Syafiq', $saved->couples->first()->groom_name);
        $this->assertSame('Nur Awanis', $saved->couples->first()->bride_name);
        $this->assertSame('A101', $saved->packageSides->first()->design->design_code);
        $this->assertSame('0123456789', $saved->packageSides->first()->event->contacts->firstWhere('contact_number', 1)->contact_phone);
        $this->assertSame('0191112222', $saved->fulfilment->recipient_phone);
    }

    public function test_one_package_cannot_write_to_a_side_not_owned_by_the_order(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);

        $this->expectException(ValidationException::class);

        app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'PEREMPUAN' => [
                    'design' => ['design_code' => 'P100'],
                ],
            ],
        ]);
    }

    public function test_two_package_draft_keeps_lelaki_and_perempuan_data_separate(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'LELAKI' => ['design' => ['design_code' => 'l100']],
                'PEREMPUAN' => ['design' => ['design_code' => 'p200']],
            ],
        ]);

        $this->assertSame('L100', $saved->packageSides->firstWhere('side', 'LELAKI')->design->design_code);
        $this->assertSame('P200', $saved->packageSides->firstWhere('side', 'PEREMPUAN')->design->design_code);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_optional_second_couple_saves_normalized_data_and_can_be_resumed(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
    ]);

    $saved = app(SaveOrderDraftService::class)->save($order, [
        'second_couple' => [
            'groom_name' => '  Ahmad   Fauzi  ',
            'groom_abbreviation' => ' Fauzi ',
            'bride_name' => '  Nur   Syafiqah ',
            'bride_abbreviation' => ' Syafiqah ',
        ],
    ]);

    $secondCouple = $saved->couples->firstWhere('couple_number', 2);

    $this->assertNotNull($secondCouple);
    $this->assertSame('Ahmad Fauzi', $secondCouple->groom_name);
    $this->assertSame('Fauzi', $secondCouple->groom_abbreviation);
    $this->assertSame('Nur Syafiqah', $secondCouple->bride_name);
    $this->assertSame('Syafiqah', $secondCouple->bride_abbreviation);

    $resumedOrder = $order->fresh('couples');
    $resumedSecondCouple = $resumedOrder->couples->firstWhere('couple_number', 2);

    $this->assertNotNull($resumedSecondCouple);
    $this->assertSame('Ahmad Fauzi', $resumedSecondCouple->groom_name);
    $this->assertSame('Fauzi', $resumedSecondCouple->groom_abbreviation);
    $this->assertSame('Nur Syafiqah', $resumedSecondCouple->bride_name);
    $this->assertSame('Syafiqah', $resumedSecondCouple->bride_abbreviation);
}

    public function test_special_name_formatting_is_preserved_while_whitespace_is_normalized(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'T07 Special Names',
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => '  Muhammad   A/L   Abdullah  ',
                'bride_name' => "  Nur   A’Qila   Binti   O'Connor-Smith  ",
            ],
            'sides' => [
                'LELAKI' => [
                    'parents' => [
                        'father_name' => "  Ahmad   bin   O'Rahman  ",
                        'mother_name' => '  Siti   A/P   Abdullah-Samy  ',
                    ],
                ],
            ],
        ]);

        $couple = $saved->couples->firstWhere('couple_number', 1);
        $parents = $saved->packageSides
            ->firstWhere('side', 'LELAKI')
            ->parents;

        $this->assertSame('Muhammad A/L Abdullah', $couple->groom_name);
        $this->assertSame("Nur A’Qila Binti O'Connor-Smith", $couple->bride_name);
        $this->assertSame("Ahmad bin O'Rahman", $parents->father_name);
        $this->assertSame('Siti A/P Abdullah-Samy', $parents->mother_name);
    }

public function test_one_package_card_image_is_stored_privately_and_path_is_persisted(): void
{
    Storage::fake('local');

    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
    ]);

    $image = UploadedFile::fake()->image('pengantin.jpg');

    $saved = app(SaveOrderDraftService::class)->save($order, [
        'sides' => [
            'LELAKI' => [
                'design' => [
                    'card_image' => $image,
                ],
            ],
        ],
    ]);

    $design = $saved->packageSides
        ->firstWhere('side', 'LELAKI')
        ->design;

    $this->assertNotNull($design->card_image_path);
    $this->assertStringStartsWith(
        "orders/{$order->order_id}/LELAKI/",
        $design->card_image_path
    );

    Storage::disk('local')->assertExists($design->card_image_path);

    $this->assertDatabaseHas('order_designs', [
        'id' => $design->id,
        'card_image_path' => $design->card_image_path,
    ]);
}

public function test_reuploading_card_image_replaces_old_file_for_same_side(): void
{
    Storage::fake('local');

    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
    ]);

    $firstSaved = app(SaveOrderDraftService::class)->save($order, [
        'sides' => [
            'LELAKI' => [
                'design' => [
                    'card_image' => UploadedFile::fake()->image('first.jpg'),
                ],
            ],
        ],
    ]);

    $firstPath = $firstSaved->packageSides
        ->firstWhere('side', 'LELAKI')
        ->design
        ->card_image_path;

    Storage::disk('local')->assertExists($firstPath);

    $secondSaved = app(SaveOrderDraftService::class)->save($order->fresh(), [
        'sides' => [
            'LELAKI' => [
                'design' => [
                    'card_image' => UploadedFile::fake()->image('second.jpg'),
                ],
            ],
        ],
    ]);

    $secondPath = $secondSaved->packageSides
        ->firstWhere('side', 'LELAKI')
        ->design
        ->card_image_path;

    $this->assertNotSame($firstPath, $secondPath);

    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);

    $this->assertDatabaseHas('order_designs', [
        'id' => $secondSaved->packageSides
            ->firstWhere('side', 'LELAKI')
            ->design
            ->id,
        'card_image_path' => $secondPath,
    ]);
}

public function test_two_package_card_images_are_stored_independently_by_side(): void
{
    Storage::fake('local');

    $order = app(CreateOrderService::class)->create([
        'package_count' => 2,
    ]);

    $saved = app(SaveOrderDraftService::class)->save($order, [
        'sides' => [
            'LELAKI' => [
                'design' => [
                    'card_image' => UploadedFile::fake()->image('lelaki.jpg'),
                ],
            ],
            'PEREMPUAN' => [
                'design' => [
                    'card_image' => UploadedFile::fake()->image('perempuan.jpg'),
                ],
            ],
        ],
    ]);

    $lelakiPath = $saved->packageSides
        ->firstWhere('side', 'LELAKI')
        ->design
        ->card_image_path;

    $perempuanPath = $saved->packageSides
        ->firstWhere('side', 'PEREMPUAN')
        ->design
        ->card_image_path;

    $this->assertNotSame($lelakiPath, $perempuanPath);

    $this->assertStringStartsWith(
        "orders/{$order->order_id}/LELAKI/",
        $lelakiPath
    );

    $this->assertStringStartsWith(
        "orders/{$order->order_id}/PEREMPUAN/",
        $perempuanPath
    );

    Storage::disk('local')->assertExists($lelakiPath);
    Storage::disk('local')->assertExists($perempuanPath);
}

public function test_dashboard_rejects_non_image_card_upload(): void
{
    Storage::fake('local');

    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    $magicLinkRequest = $query === ''
        ? $path
        : $path.'?'.$query;

    $this->get($magicLinkRequest)
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

    $response = $this
        ->from(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->post(route('orders.draft.update', [
            'orderId' => $order->order_id,
        ]), [
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'card_image' => UploadedFile::fake()->create(
                            'not-an-image.pdf',
                            100,
                            'application/pdf'
                        ),
                    ],
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->assertSessionHasErrors([
            'sides.LELAKI.design.card_image',
        ]);

    $design = $order->fresh('packageSides.design')
        ->packageSides
        ->firstWhere('side', 'LELAKI')
        ->design;

    $this->assertNull($design->card_image_path);
}

public function test_dashboard_rejects_card_image_larger_than_ten_megabytes(): void
{
    Storage::fake('local');

    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'PEREMPUAN',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    $magicLinkRequest = $query === ''
        ? $path
        : $path.'?'.$query;

    $this->get($magicLinkRequest)
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

    $response = $this
        ->from(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->post(route('orders.draft.update', [
            'orderId' => $order->order_id,
        ]), [
            'sides' => [
                'PEREMPUAN' => [
                    'design' => [
                        'card_image' => UploadedFile::fake()->create(
                            'too-large.jpg',
                            10241,
                            'image/jpeg'
                        ),
                    ],
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->assertSessionHasErrors([
            'sides.PEREMPUAN.design.card_image',
        ]);

    $design = $order->fresh('packageSides.design')
        ->packageSides
        ->firstWhere('side', 'PEREMPUAN')
        ->design;

    $this->assertNull($design->card_image_path);
}

    public function test_dashboard_form_can_save_and_resume_existing_values(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'PEREMPUAN',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);
    $magicLinkRequest = $query === ''
        ? $path
        : $path.'?'.$query;

    $this->get($magicLinkRequest)
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

    $response = $this->post(route('orders.draft.update', [
        'orderId' => $order->order_id,
    ]), [
        'couple' => [
            'groom_name' => 'Hakim',
            'bride_name' => 'Sarah',
        ],
        'sides' => [
            'PEREMPUAN' => [
                'design' => ['design_code' => 'p500'],
            ],
        ],
    ]);

    $response->assertRedirect(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]));

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Hakim')
        ->assertSee('Sarah')
        ->assertSee('P500')
        ->assertDontSee('token=');
    }

    public function test_dashboard_can_save_and_resume_order_level_card_quantity(): void
    {
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'PEREMPUAN',
        'customer_name' => 'Card Quantity HTTP Test',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    $magicLinkRequest = $query === ''
        ? $path
        : $path.'?'.$query;

    $this->get($magicLinkRequest)
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

    $response = $this->post(route('orders.draft.update', [
        'orderId' => $order->order_id,
    ]), [
        'card_quantity' => 500,
    ]);

    $response->assertRedirect(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]));

    $order->refresh();

    $this->assertSame(500, $order->card_quantity);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'card_quantity' => 500,
    ]);

    $this->get(route('orders.dashboard', [
        'orderId' => $order->order_id,
    ]))
        ->assertOk()
        ->assertSee('Kuantiti Kad')
        ->assertSee('name="card_quantity"', false)
        ->assertSee('value="500"', false);
    }

    public function test_dashboard_rejects_invalid_card_quantity(): void
    {
        $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Invalid Card Quantity Test',
    ]);

    $url = app(GenerateOrderAccessLinkService::class)->generate($order);

    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    $magicLinkRequest = $query === ''
        ? $path
        : $path.'?'.$query;

    $this->get($magicLinkRequest)
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]));

    $response = $this
        ->from(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->post(route('orders.draft.update', [
            'orderId' => $order->order_id,
        ]), [
            'card_quantity' => 0,
        ]);

    $response
        ->assertRedirect(route('orders.dashboard', [
            'orderId' => $order->order_id,
        ]))
        ->assertSessionHasErrors([
            'card_quantity',
        ]);

    $this->assertNull(
        $order->fresh()->card_quantity
    );
    }

    public function test_confirmed_order_cannot_be_changed_by_save_draft(): void
{
    $order = app(CreateOrderService::class)->create([
        'package_count' => 1,
        'side' => 'LELAKI',
        'customer_name' => 'Confirmed Draft Guard',
    ]);

    app(SaveOrderDraftService::class)->save($order, [
        'couple' => [
            'groom_name' => 'Ahmad',
            'bride_name' => 'Aisyah',
        ],
        'sides' => [
            'LELAKI' => [
                'design' => [
                    'design_code' => 'L100',
                ],
            ],
        ],
    ]);

    $confirmedAt = now()->subMinute();

    $order->forceFill([
        'status' => 'DETAILS_CONFIRMED',
        'details_confirmed_at' => $confirmedAt,
    ])->save();

    try {
        app(SaveOrderDraftService::class)->save($order->fresh(), [
            'couple' => [
                'groom_name' => 'Nama Diubah',
                'bride_name' => 'Nama Diubah',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => 'CHANGED',
                    ],
                ],
            ],
        ]);

        $this->fail('Confirmed order was unexpectedly allowed to save draft changes.');
    } catch (ValidationException $exception) {
        $this->assertArrayHasKey('order', $exception->errors());
    }

    $freshOrder = $order->fresh([
        'couples',
        'packageSides.design',
    ]);

    $this->assertSame('DETAILS_CONFIRMED', $freshOrder->status);
    $this->assertNotNull($freshOrder->details_confirmed_at);

    $this->assertSame(
        $confirmedAt->timestamp,
        $freshOrder->details_confirmed_at->timestamp
    );

    $this->assertSame(
        'Ahmad',
        $freshOrder->couples
            ->firstWhere('couple_number', 1)
            ->groom_name
    );

    $this->assertSame(
        'Aisyah',
        $freshOrder->couples
            ->firstWhere('couple_number', 1)
            ->bride_name
    );

    $this->assertSame(
        'L100',
        $freshOrder->packageSides
            ->firstWhere('side', 'LELAKI')
            ->design
            ->design_code
    );
    }
}
