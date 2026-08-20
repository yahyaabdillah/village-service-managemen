<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PrivateDocumentResponse
{
    public function inline(string $path, string $originalName): Response
    {
        abort_unless(Storage::disk('private')->exists($path), 404);
        $content = Storage::disk('private')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };

        abort_unless($extension, 415);

        $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'berkas';
        $filename = trim(preg_replace('/[^A-Za-z0-9._-]/', '-', $baseName), '-').'.'.$extension;

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(string $path, string $baseName): Response
    {
        abort_unless(Storage::disk('private')->exists($path), 404);
        $content = Storage::disk('private')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'video/mp4' => 'mp4',
            default => strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin',
        };
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $baseName).'.'.$extension;

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
