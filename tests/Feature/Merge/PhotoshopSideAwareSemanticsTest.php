<?php

namespace Tests\Feature\Merge;

use App\Models\MergeJob;
use App\Services\Photoshop\BuildPhotoshopAutoMergeRowService;
use Tests\TestCase;

class PhotoshopSideAwareSemanticsTest extends TestCase
{
    public function test_perempuan_row_keeps_semantic_bride_and_groom_fields_unchanged(): void
    {
        $job = new MergeJob([
            'job_id' => 'KKK-260911-0001-P',
            'side' => 'PEREMPUAN',
            'canonical_payload' => [
                'source' => [
                    'order_id' => 'KKK-260911-0001',
                    'side' => 'PEREMPUAN',
                ],
                'design' => [
                    'theme' => 'Songket',
                    'design_code' => 'CKS-218',
                ],
                'couple' => [
                    'groom_name' => 'Adam Ahmad',
                    'groom_abbreviation' => 'Mad',
                    'bride_name' => 'Olivia Rodrigo',
                    'bride_abbreviation' => 'Olivia',
                ],
                'parents' => [],
                'event' => [],
            ],
        ]);

        $row = app(BuildPhotoshopAutoMergeRowService::class)->build($job, 500);

        $this->assertSame('PEREMPUAN', $row['majlis']);
        $this->assertSame('Adam Ahmad', $row['namapengantinlelaki']);
        $this->assertSame('Olivia Rodrigo', $row['namapengantinperempuan']);
        $this->assertSame('Mad', $row['singkatanlelaki']);
        $this->assertSame('Olivia', $row['singkatanperempuan']);
    }
}
