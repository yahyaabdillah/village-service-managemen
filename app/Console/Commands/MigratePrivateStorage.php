<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigratePrivateStorage extends Command
{
    protected $signature = 'storage:migrate-private
        {--from=private-local : Source filesystem disk}
        {--to=object : Destination filesystem disk}
        {--dry-run : List files without copying them}
        {--force : Overwrite files that already exist at the destination}';

    protected $description = 'Copy private files between filesystem disks using streams';

    public function handle(): int
    {
        $sourceName = (string) $this->option('from');
        $targetName = (string) $this->option('to');

        if ($sourceName === $targetName) {
            $this->error('Disk sumber dan tujuan harus berbeda.');

            return self::FAILURE;
        }

        try {
            $source = Storage::disk($sourceName);
            $target = Storage::disk($targetName);
            $files = $source->allFiles();
        } catch (Throwable $exception) {
            $this->error('Storage tidak dapat diakses: '.$exception->getMessage());

            return self::FAILURE;
        }

        $copied = 0;
        $skipped = 0;

        foreach ($files as $path) {
            if (! $this->option('force') && $target->exists($path)) {
                $skipped++;
                $this->line("SKIP {$path}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("COPY {$path}");
                $copied++;

                continue;
            }

            $stream = $source->readStream($path);
            if (! is_resource($stream)) {
                $this->error("Gagal membaca {$path}");

                return self::FAILURE;
            }

            try {
                if (! $target->put($path, $stream)) {
                    $this->error("Gagal menulis {$path}");

                    return self::FAILURE;
                }
            } finally {
                fclose($stream);
            }

            $copied++;
            $this->line("OK   {$path}");
        }

        $mode = $this->option('dry-run') ? 'Dry-run' : 'Migrasi';
        $this->info("{$mode} selesai: {$copied} file diproses, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
