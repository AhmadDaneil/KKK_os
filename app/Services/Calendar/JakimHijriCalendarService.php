<?php

namespace App\Services\Calendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class JakimHijriCalendarService
{
    public function gregorianToHijri(string $date): array
    {
        return $this->convert('miladi', 'date', $date);
    }

    private function convert(string $dateType, string $parameter, string $date): array
    {
        $cacheKey = "jakim-calendar:{$dateType}:{$date}";

        return Cache::remember($cacheKey, now()->addYear(), function () use ($dateType, $parameter, $date): array {
            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->retry(2, 200)
                    ->get(config('services.jakim_calendar.url'), [
                        'r' => 'esolatApi/tarikhtakwim',
                        'period' => 'today',
                        'datetype' => $dateType,
                        $parameter => $date,
                    ])
                    ->throw();
            } catch (Throwable $exception) {
                throw new RuntimeException('JAKIM calendar service is temporarily unavailable.', previous: $exception);
            }

            $output = data_get($response->json(), "takwim.{$date}");

            if (! is_string($output) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $output)) {
                throw new RuntimeException('JAKIM calendar service returned an invalid response.');
            }

            return [
                'input' => $date,
                'output' => $output,
                'source' => 'JAKIM e-Solat (Imkanur Rukyah)',
            ];
        });
    }
}
