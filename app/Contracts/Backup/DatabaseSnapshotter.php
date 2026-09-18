<?php

namespace App\Contracts\Backup;

use App\Services\Backup\DatabaseSnapshot;

interface DatabaseSnapshotter
{
    public function create(string $connectionName, string $workingDirectory): DatabaseSnapshot;
}
