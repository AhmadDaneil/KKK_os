<?php

namespace Tests\Feature\Merge;

use App\Support\Photoshop\PhotoshopAutoMergeContractV1;
use Tests\TestCase;

class PhotoshopSideAwareContractTest extends TestCase
{
    public function test_majlis_is_now_a_required_consumed_photoshop_header(): void
    {
        $this->assertContains('majlis', PhotoshopAutoMergeContractV1::HEADERS);
        $this->assertContains('majlis', PhotoshopAutoMergeContractV1::JSX_CONSUMED_HEADERS);
        $this->assertContains('majlis', PhotoshopAutoMergeContractV1::JSX_REQUIRED_HEADERS);
        $this->assertNotContains('majlis', PhotoshopAutoMergeContractV1::COMPATIBILITY_ONLY_HEADERS);
    }
}
