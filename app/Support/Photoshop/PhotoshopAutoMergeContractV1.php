<?php

namespace App\Support\Photoshop;

final class PhotoshopAutoMergeContractV1
{
    public const VERSION = 'photoshop_auto_merge_v1';

    /**
     * Exact compatibility order verified against the working V2 CSV.
     */
    public const HEADERS = [
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
    ];

    /**
     * Headers that the current JSX actively reads.
     */
    public const JSX_CONSUMED_HEADERS = [
        'noinvoice',
        'qtykad',
        'tema',
        'designcode',
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
    ];

    /**
     * Current JSX hard requirements.
     */
    public const JSX_REQUIRED_HEADERS = [
        'noinvoice',
        'tema',
        'designcode',
        'majlis',
    ];

    /**
     * Present in V2 for compatibility but not consumed by the current side-aware JSX.
     */
    public const COMPATIBILITY_ONLY_HEADERS = [
        'gambar',
        'flaggambar',
    ];

    /**
     * V1 AliveCard-only columns, not part of the Photoshop V1 export.
     */
    public const ALIVECARD_EXTRA_HEADERS = [
        'pagetitle',
        'slug',
        'wa1',
        'wa2',
        'wa3',
        'calllink1',
        'calllink2',
        'calllink3',
    ];
}
