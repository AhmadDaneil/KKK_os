<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BuildPhotoshopAutoMergeRowService
{
    private const MONTH_FULL_MS = [
        1 => 'JANUARI',
        2 => 'FEBRUARI',
        3 => 'MAC',
        4 => 'APRIL',
        5 => 'MEI',
        6 => 'JUN',
        7 => 'JULAI',
        8 => 'OGOS',
        9 => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DISEMBER',
    ];

    private const MONTH_SHORT_MS = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAC',
        4 => 'APR',
        5 => 'MEI',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'OGO',
        9 => 'SEP',
        10 => 'OKT',
        11 => 'NOV',
        12 => 'DIS',
    ];

    /**
     * Build one exact 28-column Photoshop compatibility row.
     *
     * qtyKad is supplied explicitly by the export orchestration.
     * The approved V1 source is orders.card_quantity (Option A).
     */
    public function build(MergeJob $mergeJob, int|string $qtyKad): array
    {
        $payload = $mergeJob->canonical_payload;

        if (! is_array($payload)) {
            throw new RuntimeException(
                "Merge job {$mergeJob->job_id} has no valid canonical payload."
            );
        }

        $quantity = $this->normalizeQuantity($qtyKad);

        $source = $payload['source'] ?? [];
        $design = $payload['design'] ?? [];
        $couple = $payload['couple'] ?? [];
        $parents = $payload['parents'] ?? [];
        $event = $payload['event'] ?? [];

        $orderId = $this->uppercaseText($source['order_id'] ?? '');
        $theme = $this->uppercaseText($design['theme'] ?? '');
        $designCode = $this->uppercaseText($design['design_code'] ?? '');
        $majlis = $this->uppercaseText($source['side'] ?? '');
        $cardImagePath = trim((string) ($design['card_image_path'] ?? ''));

        if ($orderId === '' || $theme === '' || $designCode === '') {
            throw new RuntimeException(
                "Merge job {$mergeJob->job_id} is missing noinvoice, tema, or designcode."
            );
        }

        if (! in_array($majlis, ['LELAKI', 'PEREMPUAN'], true)) {
            throw new RuntimeException(
                "Merge job {$mergeJob->job_id} has invalid majlis/side: {$majlis}."
            );
        }

        $date = $this->parseDate($event['event_date'] ?? null);
        $contacts = $this->contactsByNumber($event['contacts'] ?? []);

        return [
            'noinvoice' => $orderId,
            'qtykad' => $quantity,
            'tema' => $theme,
            'designcode' => $designCode,

            // Customer photos are used exclusively with the PORTRAIT theme.
            // A non-portrait design must reach Photoshop without a customer photo.
            'gambar' => $theme === 'PORTRAIT'
                ? $this->cardImagePath($cardImagePath)
                : '',

            // `majlis` is now actively consumed by the approved side-aware JSX:
            // LELAKI = groom above bride; PEREMPUAN = bride above groom.
            // The semantic bride/groom CSV fields themselves remain unchanged.
            'majlis' => $majlis,

            'namapengantinlelaki' => $this->text($couple['groom_name'] ?? ''),
            'namapengantinperempuan' => $this->text($couple['bride_name'] ?? ''),
            'singkatanlelaki' => $this->abbreviation($couple['groom_abbreviation'] ?? ''),
            'singkatanperempuan' => $this->abbreviation($couple['bride_abbreviation'] ?? ''),
            'namaayah' => $this->text($parents['father_name'] ?? ''),
            'namaibu' => $this->text($parents['mother_name'] ?? ''),

            'hari' => $this->text($event['day_name'] ?? ''),
            'tarikh' => $date ? $this->fullMalayDate($date) : '',
            'tarikhhari' => $date ? (string) $date->day : '',
            'bulan' => $date ? self::MONTH_SHORT_MS[$date->month].' '.$date->year : '',
            'bulanislam' => $this->text($event['hijri_date'] ?? ''),

            // Current JSX writes these values exactly; no Photoshop-side formatting.
            'masabersanding' => $this->text($event['bersanding_time'] ?? ''),
            'masajamuanmakan' => $this->text($event['meal_time'] ?? ''),

            'alamat' => $this->text($event['full_address'] ?? ''),
            'qrlink' => trim((string) ($event['google_maps_url'] ?? '')),

            'nama1' => $this->text($contacts[1]['contact_name'] ?? ''),
            'notel1' => $this->phone($contacts[1]['contact_phone'] ?? ''),
            'nama2' => $this->text($contacts[2]['contact_name'] ?? ''),
            'notel2' => $this->phone($contacts[2]['contact_phone'] ?? ''),
            'nama3' => $this->text($contacts[3]['contact_name'] ?? ''),
            'notel3' => $this->phone($contacts[3]['contact_phone'] ?? ''),

            'flaggambar' => $cardImagePath !== '' ? 'ADA GAMBAR' : 'TIADA GAMBAR',
        ];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            throw new RuntimeException("Invalid canonical event_date: {$value}");
        }
    }

    private function cardImagePath(mixed $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return '';
        }

        return Storage::disk('local')->path($path);
    }

    private function fullMalayDate(CarbonImmutable $date): string
    {
        return $date->day.' '.self::MONTH_FULL_MS[$date->month].' '.$date->year;
    }

    private function contactsByNumber(array $contacts): array
    {
        $result = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $number = (int) ($contact['contact_number'] ?? 0);

            if ($number >= 1 && $number <= 3) {
                $result[$number] = $contact;
            }
        }

        return $result;
    }

    private function normalizeQuantity(int|string $value): string
    {
        $raw = trim((string) $value);

        // Accept legacy inputs such as "200 PCS" but export only 200,
        // because the current JSX itself appends " PCS" to output names.
        if (! preg_match('/^\s*(\d+)(?:\s*PCS)?\s*$/i', $raw, $match)) {
            throw new RuntimeException(
                'qtykad must be a positive whole-number card quantity.'
            );
        }

        $qty = (int) $match[1];

        if ($qty <= 0) {
            throw new RuntimeException('qtykad must be greater than zero.');
        }

        return (string) $qty;
    }

    private function text(mixed $value): string
    {
        return $this->uppercaseText($value);
    }

    private function uppercaseText(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    private function abbreviation(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        if ($value === '') {
            return '';
        }

        $firstCharacter = mb_substr($value, 0, 1, 'UTF-8');
        $remainingCharacters = mb_substr(
            $value,
            1,
            mb_strlen($value, 'UTF-8'),
            'UTF-8'
        );

        return mb_strtoupper($firstCharacter, 'UTF-8').$remainingCharacters;
    }

    private function phone(mixed $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', trim((string) $value));

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0060')) {
            return '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '60')) {
            return '0'.substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? $digits : '0'.$digits;
    }
}
