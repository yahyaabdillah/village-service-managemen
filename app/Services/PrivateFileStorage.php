<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivateFileStorage
{
    public function disk(): FilesystemAdapter
    {
        return Storage::disk('private');
    }

    /**
     * Run a local-file-only operation against a private object.
     *
     * @template TResult
     * @param  callable(string): TResult  $callback
     * @return TResult
     */
    public function withLocalFile(string $path, callable $callback): mixed
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Path private storage tidak valid.');
        }

        $stream = $this->disk()->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('File tidak ditemukan atau tidak dapat dibaca dari private storage.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'vsm-private-');
        if ($temporaryPath === false) {
            fclose($stream);
            throw new RuntimeException('Tidak dapat membuat file sementara.');
        }

        $temporary = fopen($temporaryPath, 'wb');
        if (! is_resource($temporary)) {
            fclose($stream);
            @unlink($temporaryPath);
            throw new RuntimeException('Tidak dapat membuka file sementara.');
        }

        try {
            if (stream_copy_to_stream($stream, $temporary) === false) {
                throw new RuntimeException('File private storage gagal disalin ke penyimpanan sementara.');
            }

            fclose($temporary);
            $temporary = null;
            fclose($stream);
            $stream = null;

            return $callback($temporaryPath);
        } finally {
            if (is_resource($temporary)) {
                fclose($temporary);
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($temporaryPath);
        }
    }
}
