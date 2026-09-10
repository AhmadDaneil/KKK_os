<?php

namespace Tests\Feature\Merge;

use App\Models\MergeJob;
use App\Services\Photoshop\BuildPhotoshopAutoMergeRowService;
use App\Support\Photoshop\PhotoshopAutoMergeContractV1;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoshopAutoMergeContractV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_verified_v2_header_contract_has_exactly_28_columns(): void
    {
        $this->assertCount(28, PhotoshopAutoMergeContractV1::HEADERS);

        $this->assertSame([
            'noinvoice',
            'qtykad',
            'tema',
            'designcode',
            'gambar',
            'majlis',
            'namapengantinlelaki',
            'namapengantinperempuan',
            'singkatanlelaki',
            'singkatanperempuan',
            'namaayah',
            'namaibu',
            'hari',
            'tarikh',
            'tarikhhari',
            'bulan',
            'bulanislam',
            'masabersanding',
            'masajamuanmakan',
            'alamat',
            'qrlink',
            'nama1',
            'notel1',
            'nama2',
            'notel2',
            'nama3',
            'notel3',
            'flaggambar',
        ], PhotoshopAutoMergeContractV1::HEADERS);
    }

    public function test_row_builder_maps_canonical_payload_to_verified_photoshop_fields(): void
    {
        $mergeJob = new MergeJob([
            'job_id' => 'KKK-260910-0001-P',
            'side' => 'PEREMPUAN',
            'canonical_payload' => [
                'source' => [
                    'order_id' => 'KKK-260910-0001',
                    'side' => 'PEREMPUAN',
                ],
                'design' => [
                    'theme' => 'ISLAMIC',
                    'design_code' => 'cki-820',
                ],
                'couple' => [
                    'groom_name' => 'Mohammad Naim Iskadar Bin Zainalabidin',
                    'groom_abbreviation' => 'Naim',
                    'bride_name' => 'Nur A’Qila Insyirah Binti Zamri',
                    'bride_abbreviation' => 'A’Qila',
                ],
                'parents' => [
                    'father_name' => 'Zamri Bin Sulong',
                    'mother_name' => 'Neszlipah Binti Mohamed',
                ],
                'event' => [
                    'day_name' => 'Sabtu',
                    'event_date' => '2026-12-26',
                    'hijri_date' => '16 REJAB 1448H',
                    'bersanding_time' => '12.00 PM',
                    'meal_time' => '12.00 PM - 4.00 PM',
                    'full_address' => 'BAIDURI HALL, RAIA HOTEL',
                    'google_maps_url' => 'https://maps.app.goo.gl/example',
                    'contacts' => [
                        [
                            'contact_number' => 1,
                            'contact_name' => 'Zamri',
                            'contact_phone' => '013-940 8109',
                        ],
                        [
                            'contact_number' => 2,
                            'contact_name' => 'Neszlipah',
                            'contact_phone' => '0139819661',
                        ],
                    ],
                ],
            ],
        ]);

        $row = app(BuildPhotoshopAutoMergeRowService::class)->build(
            $mergeJob,
            '200 PCS'
        );

        $this->assertSame('KKK-260910-0001', $row['noinvoice']);
        $this->assertSame('200', $row['qtykad']);
        $this->assertSame('ISLAMIC', $row['tema']);
        $this->assertSame('CKI-820', $row['designcode']);
        $this->assertSame('PEREMPUAN', $row['majlis']);
        $this->assertSame('Zamri Bin Sulong', $row['namaayah']);
        $this->assertSame('26 DISEMBER 2026', $row['tarikh']);
        $this->assertSame('26', $row['tarikhhari']);
        $this->assertSame('DIS 2026', $row['bulan']);
        $this->assertSame('0139408109', $row['notel1']);
        $this->assertSame('', $row['gambar']);
        $this->assertSame('', $row['flaggambar']);
    }
}
