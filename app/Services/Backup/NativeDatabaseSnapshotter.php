<?php

namespace App\Services\Backup;

use App\Contracts\Backup\DatabaseSnapshotter;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class NativeDatabaseSnapshotter implements DatabaseSnapshotter
{
    public function __construct(private DatabaseManager $databases) {}

    public function create(string $connectionName, string $workingDirectory): DatabaseSnapshot
    {
        $this->ensurePrivateDirectory($workingDirectory);

        $connection = $this->databases->connection($connectionName);
        $configuration = $connection->getConfig();
        $driver = (string) ($configuration['driver'] ?? '');
        $identifier = bin2hex(random_bytes(16));
        $rawPath = $workingDirectory.DIRECTORY_SEPARATOR.$identifier;

        try {
            $extension = match ($driver) {
                'mysql', 'mariadb' => $this->createMySqlDump($configuration, $rawPath.'.sql'),
                'pgsql' => $this->createPostgresDump($configuration, $rawPath.'.sql'),
                'sqlite' => $this->createSqliteSnapshot($connection->getPdo(), $rawPath.'.sqlite'),
                default => throw new RuntimeException("Database backup does not support driver [{$driver}]."),
            };

            $uncompressedPath = $rawPath.'.'.$extension;
            $compressedPath = $uncompressedPath.'.gz';
            $this->compress($uncompressedPath, $compressedPath);

            return new DatabaseSnapshot(
                path: $compressedPath,
                format: $driver === 'sqlite' ? 'sqlite-gzip' : "{$driver}-sql-gzip",
                extension: $extension.'.gz',
            );
        } catch (Throwable $exception) {
            $this->deleteIfPresent($rawPath.'.sql');
            $this->deleteIfPresent($rawPath.'.sqlite');
            $this->deleteIfPresent($rawPath.'.sql.gz');
            $this->deleteIfPresent($rawPath.'.sqlite.gz');

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function createMySqlDump(array $configuration, string $outputPath): string
    {
        $database = $this->requiredConfigurationValue($configuration, 'database');
        $credentialsPath = $outputPath.'.cnf';

        try {
            $credentialLines = [
                '[client]',
                'user='.$this->quoteOptionValue((string) ($configuration['username'] ?? '')),
                'password='.$this->quoteOptionValue((string) ($configuration['password'] ?? '')),
                'host='.$this->quoteOptionValue((string) ($configuration['host'] ?? '127.0.0.1')),
                'port='.(int) ($configuration['port'] ?? 3306),
            ];

            if (! empty($configuration['unix_socket'])) {
                $credentialLines[] = 'socket='.$this->quoteOptionValue((string) $configuration['unix_socket']);
            }

            $this->writeCredentialFile($credentialsPath, implode(PHP_EOL, $credentialLines).PHP_EOL);

            $arguments = [
                (string) config('backup.database.binaries.mysql', 'mysqldump'),
                '--defaults-extra-file='.$credentialsPath,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--result-file='.$outputPath,
            ];

            if (! empty($configuration['charset'])) {
                $arguments[] = '--default-character-set='.(string) $configuration['charset'];
            }

            $arguments[] = $database;

            $this->runProcess($arguments);
            $this->assertNonEmptyFile($outputPath);

            return 'sql';
        } finally {
            $this->deleteIfPresent($credentialsPath);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function createPostgresDump(array $configuration, string $outputPath): string
    {
        $database = $this->requiredConfigurationValue($configuration, 'database');
        $host = (string) ($configuration['host'] ?? '127.0.0.1');
        $port = (string) ($configuration['port'] ?? '5432');
        $username = (string) ($configuration['username'] ?? '');
        $password = (string) ($configuration['password'] ?? '');
        $credentialsPath = $outputPath.'.pgpass';

        try {
            $credentialLine = implode(':', array_map(
                fn (string $value): string => $this->escapePgPassValue($value),
                [$host, $port, $database, $username, $password],
            )).PHP_EOL;
            $this->writeCredentialFile($credentialsPath, $credentialLine);

            $this->runProcess([
                (string) config('backup.database.binaries.pgsql', 'pg_dump'),
                '--format=plain',
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '--no-password',
                '--host='.$host,
                '--port='.$port,
                '--username='.$username,
                '--file='.$outputPath,
                $database,
            ], ['PGPASSFILE' => $credentialsPath]);
            $this->assertNonEmptyFile($outputPath);

            return 'sql';
        } finally {
            $this->deleteIfPresent($credentialsPath);
        }
    }

    private function createSqliteSnapshot(\PDO $pdo, string $outputPath): string
    {
        $quotedPath = $pdo->quote($outputPath);

        if ($quotedPath === false || $pdo->exec("VACUUM INTO {$quotedPath}") === false) {
            throw new RuntimeException('SQLite could not create a consistent VACUUM INTO snapshot.');
        }

        $this->assertNonEmptyFile($outputPath);

        return 'sqlite';
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function runProcess(array $arguments, array $environment = []): void
    {
        $process = new Process($arguments, null, $environment);
        $process->setTimeout((float) config('backup.database.process_timeout_seconds', 3600));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $safeError = mb_substr($error, 0, 1000);

            throw new RuntimeException(
                'Database snapshot process failed with exit code '.($process->getExitCode() ?? 'unknown').
                ($safeError !== '' ? ": {$safeError}" : '.'),
            );
        }
    }

    private function compress(string $sourcePath, string $destinationPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $destination = gzopen($destinationPath, 'wb9');

        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                gzclose($destination);
            }

            throw new RuntimeException('Could not open database snapshot streams for compression.');
        }

        try {
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Could not read the uncompressed database snapshot.');
                }

                if ($chunk !== '' && gzwrite($destination, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Could not write the compressed database snapshot.');
                }
            }
        } finally {
            fclose($source);
            gzclose($destination);
        }

        $this->deleteIfPresent($sourcePath);
        $this->assertNonEmptyFile($destinationPath);
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create backup temporary directory [{$directory}].");
        }

        @chmod($directory, 0700);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function requiredConfigurationValue(array $configuration, string $key): string
    {
        $value = (string) ($configuration[$key] ?? '');

        if ($value === '') {
            throw new RuntimeException("Database backup requires a non-empty [{$key}] configuration value.");
        }

        return $value;
    }

    private function writeCredentialFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not create the temporary database client credential file.');
        }

        @chmod($path, 0600);
    }

    private function quoteOptionValue(string $value): string
    {
        $this->assertSingleLine($value);

        return '"'.addcslashes($value, '\\"').'"';
    }

    private function escapePgPassValue(string $value): string
    {
        $this->assertSingleLine($value);

        return str_replace(['\\', ':'], ['\\\\', '\\:'], $value);
    }

    private function assertSingleLine(string $value): void
    {
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new RuntimeException('Database client configuration values must not contain line breaks.');
        }
    }

    private function assertNonEmptyFile(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Database snapshot process produced no restorable output.');
        }
    }

    private function deleteIfPresent(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
