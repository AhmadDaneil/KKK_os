<?php

namespace App\Services\Photoshop;

use RuntimeException;
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

        $automationLauncher = $this->createAutomationLauncher($script);

        // Photoshop treats a JSX command-line argument as a document, which can
        // trigger a locked-file alert. Windows COM automation runs it as a script.
        // `popen()` sends this raw command to cmd.exe, where `start` is a
        // built-in. This keeps the VBS path intact and detaches Script Host
        // from the HTTP request; Symfony's argument escaping previously
        // transformed it into an invalid network-style path.
        $wscript = 'C:\\Windows\\System32\\wscript.exe';
        $command = sprintf('start "" /b "%s" "%s"', $wscript, $automationLauncher);
        $handle = @popen($command, 'r');

        if ($handle === false) {
            throw new RuntimeException('Photoshop could not be opened. Please try again from the design workstation.');
        }

        pclose($handle);
    }

    private function createAutomationLauncher(string $script): string
    {
        $directory = storage_path('app/private/photoshop-launches');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The temporary Photoshop launch folder could not be created.');
        }

        $this->removeExpiredLaunchers($directory);

        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Throwable $exception) {
            throw new RuntimeException('A secure Photoshop launch filename could not be generated.', previous: $exception);
        }

        $launcher = $directory.DIRECTORY_SEPARATOR.'run-photoshop-'.$suffix.'.vbs';
        $escapedScript = str_replace('"', '""', $script);
        $contents = implode("\r\n", [
            'On Error Resume Next',
            'Set photoshop = CreateObject("Photoshop.Application")',
            'If Err.Number <> 0 Then',
            '  WScript.Quit 1',
            'End If',
            'photoshop.Visible = True',
            'Call photoshop.DoJavaScriptFile("'.$escapedScript.'", Array(), 1)',
            'WScript.Quit 0',
            '',
        ]);

        if (file_put_contents($launcher, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The Photoshop automation launcher could not be prepared.');
        }

        return $launcher;
    }

    private function removeExpiredLaunchers(string $directory): void
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'run-photoshop-*.vbs') ?: [];
        $expiry = time() - 86400;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }
}
