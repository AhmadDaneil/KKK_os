<?php

namespace App\Support\Photoshop;

final class PhotoshopCekHantaranContractV2
{
    public const HEADERS = [
        ...PhotoshopAutoMergeContractV1::HEADERS,
        'output_id',
        'jumlah_hantaran',
    ];

    public const MOCKUP_THEME = 'GENERIC';

    public const MOCKUP_DESIGN_CODE = 'CEK_HANTARAN';

    public const MOCKUP_SIDE = 'CEK_HANTARAN';
}
