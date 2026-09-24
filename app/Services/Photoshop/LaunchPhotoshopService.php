<?php

namespace App\Services\Photoshop;

use RuntimeException;
use Symfony\Component\Process\Process;

class LaunchPhotoshopService
{
    public function launch(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Photoshop can only be opened from the Windows workstation running KKK OS.');
        }

        $executable = (string) config('kingkadkahwin.photoshop.executable');
        $script = (string) config('kingkadkahwin.photoshop.script');
        $expectedHash = strtolower((string) config('kingkadkahwin.photoshop.script_sha256'));

        if ($executable === '' || ! is_file($executable)) {
            throw new RuntimeException('Photoshop was not found on this workstation. Check KKK_PHOTOSHOP_EXECUTABLE.');
        }

        if ($script === '' || ! is_file($script)) {
            throw new RuntimeException('The approved Photoshop V11 script was not found. Check KKK_PHOTOSHOP_SCRIPT.');
        }

        $actualHash = strtolower((string) hash_file('sha256', $script));

        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('The Photoshop script does not match the approved V11 script.');
        }

        // `start` detaches Photoshop from the web request. Passing the JSX file to
        // Photoshop is the command-line equivalent of File > Scripts > Browse.
        $process = new Process([
            'cmd.exe',
            '/d',
            '/s',
            '/c',
            'start',
            '',
            $executable,
            $script,
        ]);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Photoshop could not be opened. Please try again from the design workstation.');
        }
    }
}
