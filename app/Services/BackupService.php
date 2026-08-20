<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public function create(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension wajib tersedia untuk membuat backup.');
        }

        $backupDir = storage_path('app/private/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.zip';
        $absolutePath = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat archive backup.');
        }

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database_connection' => config('database.default'),
            'includes' => ['database_sqlite_if_present', 'mysql_dump_if_configured', 'private_storage_without_backups'],
        ];

        $sqlitePath = database_path('database.sqlite');
        if (is_file($sqlitePath)) {
            $zip->addFile($sqlitePath, 'database/database.sqlite');
        }

        if (config('database.default') === 'mysql') {
            $dump = $this->mysqlDump();
            if ($dump !== null) {
                $zip->addFromString('database/mysql.sql', $dump);
                $manifest['mysql_dump'] = true;
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $privateRoot = Storage::disk('private')->path('');
        $this->addDirectory($zip, $privateRoot, 'storage/private', ['backups']);

        $zip->close();

        return 'backups/'.$filename;
    }

    private function mysqlDump(): ?string
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        if (! $database || ! $username) {
            return null;
        }

        $command = sprintf(
            'MYSQL_PWD=%s mysqldump --single-transaction --quick --host=%s --port=%s --user=%s %s',
            escapeshellarg((string) $password),
            escapeshellarg((string) $host),
            escapeshellarg((string) $port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $database),
        );

        $result = Process::timeout((int) env('BACKUP_MYSQL_DUMP_TIMEOUT', 120))->run($command);

        if (! $result->successful()) {
            report(new RuntimeException('MySQL dump failed: '.$result->errorOutput()));

            return null;
        }

        return $result->output();
    }

    /** @param list<string> $excludedTopLevel */
    private function addDirectory(ZipArchive $zip, string $root, string $prefix, array $excludedTopLevel = []): void
    {
        if (! is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
            $topLevel = explode('/', $relative, 2)[0];
            if (in_array($topLevel, $excludedTopLevel, true)) {
                continue;
            }

            $zip->addFile($file->getPathname(), $prefix.'/'.$relative);
        }
    }
}
