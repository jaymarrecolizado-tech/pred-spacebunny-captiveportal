<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dumps the application database into the application's private storage.
 * Existing backups are pruned by age. This is intentionally server-local;
 * copy the backups to off-server storage once that storage is identified.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Directory for backups; defaults to storage/app/backups}
        {--retention=14 : Delete backups older than this many days}';

    protected $description = 'Dump the configured database and prune old backups';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('db:backup only supports the configured MySQL connection.');

            return self::FAILURE;
        }

        $directory = (string) $this->option('path') ?: storage_path('app/backups');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0750, true);
        }

        $this->chmodDirectory($directory, 0750);

        $target = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            .'db-'.now()->format('Ymd-His').'.sql.gz';

        $arguments = ['mysqldump', '--single-transaction', '--skip-lock-tables', '--hex-blob'];

        if (filled($config['socket'] ?? null)) {
            $arguments[] = '--socket='.escapeshellarg((string) $config['socket']);
        } else {
            $arguments[] = '--host='.escapeshellarg((string) ($config['host'] ?? '127.0.0.1'));
            $arguments[] = '--port='.escapeshellarg((string) ($config['port'] ?? '3306'));
        }

        $arguments[] = '--user='.escapeshellarg((string) ($config['username'] ?? ''));
        $arguments[] = escapeshellarg((string) ($config['database'] ?? ''));

        $command = implode(' ', $arguments).' | gzip -9 > '.escapeshellarg($target);

        $process = Process::fromShellCommandline($command, base_path(), [
            // MYSQL_PWD is intentionally used rather than passing the password
            // as an argument, where it would be visible in process listings.
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ], null, 300);

        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            File::delete($target);
            $this->error('mysqldump failed.');

            return self::FAILURE;
        }

        $this->chmodFile($target, 0640);
        $this->prune($directory, max(0, (int) $this->option('retention')));

        $this->info('Database backup created at '.$target);

        return self::SUCCESS;
    }

    private function prune(string $directory, int $retentionDays): void
    {
        if ($retentionDays === 0) {
            return;
        }

        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'db-*.sql.gz') ?: [] as $file) {
            if (is_file($file) && filemtime($file) !== false && filemtime($file) < now()->subDays($retentionDays)->timestamp) {
                unlink($file);
            }
        }
    }

    private function chmodDirectory(string $directory, int $mode): void
    {
        if (! @chmod($directory, $mode)) {
            $this->warn('Could not tighten permissions on '.$directory.'; check server access.');
        }
    }

    private function chmodFile(string $file, int $mode): void
    {
        if (! @chmod($file, $mode)) {
            $this->warn('Could not restrict read access to '.$file.'; check server access.');
        }
    }
}
