<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(fn () => Cache::put('healthcheck', now()->timestamp, 10)),
            'private_storage' => $this->check(fn () => $this->checkPrivateStorage()),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'ok' => $healthy,
            'service' => config('app.name'),
            'environment' => app()->environment(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkPrivateStorage(): void
    {
        $disk = Storage::disk('private');
        $path = 'healthcheck/'.Str::uuid().'.txt';
        $contents = bin2hex(random_bytes(16));

        try {
            if (! $disk->put($path, $contents) || $disk->get($path) !== $contents) {
                throw new RuntimeException('Private storage write/read verification failed.');
            }
        } finally {
            $disk->delete($path);
        }
    }

    private function check(callable $callback): array
    {
        try {
            $callback();

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
