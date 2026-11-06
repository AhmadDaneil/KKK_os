<?php

namespace App\Services\DigitalDesigner;

use App\Models\Order;
use App\Models\OrderPackageSide;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildDigitalDesignerCsvRowService
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

    private const DAY_NAMES_MS = [
        'AHAD',
        'ISNIN',
        'SELASA',
        'RABU',
        'KHAMIS',
        'JUMAAT',
        'SABTU',
    ];

    /**
     * @return array<string, string>
     */
    public function build(Order $order, ?OrderPackageSide $packageSide): array
    {
        $couple = $order->couples->firstWhere('couple_number', 1);
        $design = $packageSide?->design;
        $parents = $packageSide?->parents;
        $event = $packageSide?->event;
        $contacts = $event?->contacts ?? collect();

        $groomName = $this->uppercaseText($couple?->groom_name);
        $brideName = $this->uppercaseText($couple?->bride_name);
        $cardTitle = $this->uppercaseText($design?->card_title);
        $pageTitle = trim(
            implode(' & ', array_values(array_filter([$groomName, $brideName])))
            .($cardTitle !== '' ? ' - '.$cardTitle : ''),
        );

        if ($pageTitle === '') {
            $pageTitle = $this->uppercaseText($order->displayCustomerReference() ?: $order->order_id);
        }

        $theme = $this->uppercaseText($design?->theme);
        $imagePath = trim((string) ($design?->card_image_path ?? ''));
        $eventDate = $this->parseDate($event?->event_date);
        $dayName = $this->uppercaseText($event?->day_name);

        if ($dayName === '' && $eventDate !== null) {
            $dayName = self::DAY_NAMES_MS[$eventDate->dayOfWeek];
        }

        $reference = $order->displayCustomerReference() ?: $order->order_id;
        $quantity = $order->card_quantity ?? $packageSide?->card_quantity;

        $row = [
            'noinvoice' => $this->uppercaseText($order->order_id),
            'qtykad' => $this->quantity($quantity),
            'tema' => $theme,
            'designcode' => $this->uppercaseText($design?->design_code),
            'gambar' => $theme === 'PORTRAIT' && $imagePath !== ''
                ? Storage::disk('local')->path($imagePath)
                : '',
            'majlis' => $this->uppercaseText($packageSide?->side ?: $order->first_event_side),
            'namapengantinlelaki' => $groomName,
            'namapengantinperempuan' => $brideName,
            'singkatanlelaki' => $this->abbreviation($couple?->groom_abbreviation),
            'singkatanperempuan' => $this->abbreviation($couple?->bride_abbreviation),
            'namaayah' => $this->uppercaseText($parents?->father_name),
            'namaibu' => $this->uppercaseText($parents?->mother_name),
            'hari' => $dayName,
            'tarikh' => $eventDate !== null ? $this->fullMalayDate($eventDate) : '',
            'tarikhhari' => $eventDate !== null ? (string) $eventDate->day : '',
            'bulan' => $eventDate !== null
                ? self::MONTH_SHORT_MS[$eventDate->month].' '.$eventDate->year
                : '',
            'bulanislam' => $this->uppercaseText($event?->hijri_date),
            'masabersanding' => $this->formatTime($event?->bersanding_time),
            'masajamuanmakan' => $this->formatTime($event?->meal_time),
            'alamat' => $this->uppercaseText($event?->full_address),
            'qrlink' => trim((string) ($event?->google_maps_url ?? '')),
            'flaggambar' => $imagePath !== '' ? 'ADA GAMBAR' : 'TIADA GAMBAR',
            'pagetitle' => $pageTitle,
            'slug' => Str::slug($reference) ?: Str::slug($order->order_id),
        ];

        foreach ([1, 2, 3] as $number) {
            $contact = $contacts->firstWhere('contact_number', $number);
            $phone = $this->phone($contact?->contact_phone);

            $row['nama'.$number] = $this->uppercaseText($contact?->contact_name);
            $row['notel'.$number] = $phone;
            $row['wa'.$number] = $this->whatsappLink($phone);
            $row['calllink'.$number] = $this->callLink($phone);
        }

        return $row;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)->startOfDay()
                : CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fullMalayDate(CarbonImmutable $date): string
    {
        return $date->day.' '.self::MONTH_FULL_MS[$date->month].' '.$date->year;
    }

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($value)->format('g:i A');
        } catch (\Throwable) {
            return '';
        }
    }

    private function quantity(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value));

        return is_string($digits) ? $digits : '';
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

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($value, 1, null, 'UTF-8');
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

    private function whatsappLink(string $phone): string
    {
        if ($phone === '') {
            return '';
        }

        $digits = str_starts_with($phone, '0')
            ? '60'.substr($phone, 1)
            : $phone;

        return 'https://wa.me/'.$digits;
    }

    private function callLink(string $phone): string
    {
        if ($phone === '') {
            return '';
        }

        $digits = str_starts_with($phone, '0')
            ? '60'.substr($phone, 1)
            : $phone;

        return 'tel:+'.$digits;
    }
}
