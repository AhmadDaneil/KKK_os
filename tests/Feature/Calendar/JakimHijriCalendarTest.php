<?php

namespace Tests\Feature\Calendar;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JakimHijriCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_gregorian_date_uses_the_official_jakim_conversion(): void
    {
        Http::fake([
            'www.e-solat.gov.my/*' => Http::response([
                'takwim' => ['2026-10-06' => '1448-04-24'],
                'status' => 'OK!',
            ]),
        ]);

        $this->withHeader('X-KKK-Calendar-Request', '1')
            ->getJson(route('public.calendar.convert', [
                'calendar' => 'gregorian',
                'date' => '2026-10-06',
            ]))
            ->assertOk()
            ->assertJsonPath('output', '1448-04-24')
            ->assertJsonPath('source', 'JAKIM e-Solat (Imkanur Rukyah)');

        Http::assertSent(fn (Request $request): bool => $request['datetype'] === 'miladi'
            && $request['date'] === '2026-10-06'
        );
    }

    public function test_reverse_hijri_conversion_is_not_available(): void
    {
        $this->withHeader('X-KKK-Calendar-Request', '1')
            ->getJson(route('public.calendar.convert', [
                'calendar' => 'hijri',
                'date' => '1448-04-24',
            ]))
            ->assertUnprocessable();
    }

    public function test_normal_browser_navigation_never_displays_the_calendar_json(): void
    {
        $this->get(route('public.calendar.convert', [
            'calendar' => 'gregorian',
            'date' => '2026-10-02',
            'return_to' => '/tempah',
        ]))->assertRedirect('/tempah');
    }
}
