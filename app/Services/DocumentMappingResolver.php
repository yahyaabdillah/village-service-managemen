<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use RuntimeException;

class DocumentMappingResolver
{
    public const DATE_FORMATS = ['d F Y', 'd/m/Y', 'Y', 'F', 'H:i'];

    public function __construct(private DocumentVariableRegistry $registry) {}

    public function resolve(?array $mapping, string $legacyKey, array $variables): string
    {
        $mapping ??= ['version' => 1, 'mode' => 'source', 'key' => $legacyKey];

        return match ($mapping['mode'] ?? 'source') {
            'literal' => (string) ($mapping['value'] ?? ''),
            'segments' => collect($mapping['segments'] ?? [])->map(
                fn (array $segment) => $this->resolveSegment($segment, $variables)
            )->implode(''),
            'source' => (string) ($mapping['prefix'] ?? '').$this->source(
                (string) ($mapping['key'] ?? $legacyKey),
                $variables,
                $mapping['fallback'] ?? null,
                $mapping['date_format'] ?? null,
            ).(string) ($mapping['suffix'] ?? ''),
            default => throw new RuntimeException('Mode mapping dokumen tidak dikenali.'),
        };
    }

    private function resolveSegment(array $segment, array $variables): string
    {
        if (($segment['type'] ?? null) === 'literal') {
            return (string) ($segment['value'] ?? '');
        }

        if (($segment['type'] ?? null) !== 'source') {
            throw new RuntimeException('Segmen mapping dokumen tidak valid.');
        }

        return $this->source(
            (string) ($segment['key'] ?? ''),
            $variables,
            $segment['fallback'] ?? null,
            $segment['date_format'] ?? null,
        );
    }

    private function source(string $key, array $variables, mixed $fallback, ?string $dateFormat): string
    {
        $key = $this->registry->normalize($key);
        if (! array_key_exists($key, $variables)) {
            throw new RuntimeException("Variable dokumen '{$key}' tidak tersedia.");
        }

        $value = $variables[$key];
        if (($value === null || $value === '') && $fallback !== null) {
            return (string) $fallback;
        }

        if ($dateFormat) {
            if (! in_array($dateFormat, self::DATE_FORMATS, true)) {
                throw new RuntimeException('Format tanggal dokumen tidak diizinkan.');
            }
            $value = Carbon::parse($variables['__raw_'.$key] ?? $value)->locale('id')->translatedFormat($dateFormat);
        }

        return (string) ($value ?? '');
    }
}
