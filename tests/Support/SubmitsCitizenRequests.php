<?php

namespace Tests\Support;

use App\Models\ServiceType;
use Illuminate\Http\UploadedFile;

/**
 * Builds a complete public submission for a seeded service: every required custom
 * field answered and every required attachment present, so a test can focus on the
 * behaviour it is actually about instead of restating the service's form.
 */
trait SubmitsCitizenRequests
{
    protected function validSubmission(ServiceType $service, array $overrides = []): array
    {
        $service->loadMissing(['fields' => fn ($q) => $q->where('is_active', true), 'requirements']);

        $fields = [];
        foreach ($service->fields as $field) {
            if (! $field->is_required && ! array_key_exists($field->field_key, $overrides['fields'] ?? [])) {
                continue;
            }
            $fields[$field->field_key] = match ($field->field_type) {
                'date' => '1990-01-01',
                'number' => '2015',
                'email' => 'warga@contoh.id',
                'select' => $field->options[0] ?? '',
                default => 'Keperluan pengujian',
            };
        }

        $requirements = [];
        foreach ($service->requirements as $requirement) {
            if ($requirement->is_required) {
                $requirements[$requirement->id] = UploadedFile::fake()->create('berkas-'.$requirement->id.'.jpg', 100, 'image/jpeg');
            }
        }

        $payload = [
            'service_type_id' => $service->id,
            'nik' => '3313100101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'hamlet' => 'Dusun Ngringo Lor',
            'rt' => '001',
            'rw' => '002',
            'fields' => $fields,
            'requirements' => $requirements,
        ];

        foreach ($overrides as $key => $value) {
            $payload[$key] = is_array($value) && is_array($payload[$key] ?? null) ? array_replace($payload[$key], $value) : $value;
        }

        return $payload;
    }
}
