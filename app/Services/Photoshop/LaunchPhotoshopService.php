<?php

namespace App\Services\Photoshop;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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

        $launchScript = $this->createUnlockedScriptCopy($script, $actualHash);

        // Photoshop can keep a launched JSX path open. A unique runtime copy avoids
        // collisions with the repository file and with another Photoshop session.
        $process = new Process([
            'cmd.exe',
            '/d',
            '/s',
            '/c',
            'start',
            '',
            $executable,
            $launchScript,
        ]);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Photoshop could not be opened. Please try again from the design workstation.');
        }
    }

    private function createUnlockedScriptCopy(string $source, string $expectedHash): string
    {
        $directory = storage_path('app/private/photoshop-launches');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The temporary Photoshop launch folder could not be created.');
        }

        $this->removeExpiredCopies($directory);

        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Throwable $exception) {
            throw new RuntimeException('A secure Photoshop launch filename could not be generated.', previous: $exception);
        }

        $copy = $directory.DIRECTORY_SEPARATOR.'kkk-auto-merge-'.$suffix.'.jsx';

        if (! copy($source, $copy)) {
            throw new RuntimeException('The Photoshop script could not be prepared for launch.');
        }

        clearstatcache(true, $copy);
        $copiedHash = strtolower((string) hash_file('sha256', $copy));

        if (! hash_equals($expectedHash, $copiedHash)) {
            @unlink($copy);

            throw new RuntimeException('The temporary Photoshop script failed its integrity check.');
        }

        return $copy;
    }

    private function removeExpiredCopies(string $directory): void
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'kkk-auto-merge-*.jsx') ?: [];
        $expiry = time() - 86400;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }
}
