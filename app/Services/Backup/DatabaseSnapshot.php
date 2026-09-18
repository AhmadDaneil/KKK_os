<?php

namespace App\Services\Backup;

final readonly class DatabaseSnapshot
{
    public function __construct(
        public string $path,
        public string $format,
        public string $extension,
    ) {}
}
