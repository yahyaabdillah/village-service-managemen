<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Services\ResidentExcelService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentImportExportController extends Controller
{
    private array $columns = [
        'nik',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'hamlet',
        'rt',
        'rw',
        'religion',
        'marital_status',
        'occupation',
        'phone',
        'is_active',
    ];

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->columns);

            Resident::orderBy('name')->chunk(500, function ($residents) use ($handle): void {
                foreach ($residents as $resident) {
                    fputcsv($handle, array_map(fn ($column) => $resident->{$column}, $this->columns));
                }
            });

            fclose($handle);
        }, 'residents-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(Request $request, ResidentExcelService $excel)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $this->discardPendingImport($request);

        $preview = $excel->preview($data['csv']);
        $preview['file_name'] = $data['csv']->getClientOriginalName();

        if ($preview['can_import']) {
            $token = Str::random(40);
            $extension = strtolower($data['csv']->getClientOriginalExtension());
            $path = $data['csv']->storeAs('resident-imports', "{$token}.{$extension}", 'private');

            if ($path === false) {
                throw new \RuntimeException('File import sementara tidak dapat disimpan.');
            }

            $request->session()->put('resident_import', [
                'token' => $token,
                'path' => $path,
                'file_name' => $data['csv']->getClientOriginalName(),
            ]);
        }

        return back()->with('import_preview', $preview);
    }

    public function import(Request $request, ResidentExcelService $excel)
    {
        $data = $request->validate([
            'import_token' => ['required', 'string', 'size:40'],
        ]);

        $pending = $request->session()->get('resident_import');
        if (! is_array($pending)
            || ! hash_equals((string) ($pending['token'] ?? ''), $data['import_token'])
            || ! Storage::disk('private')->exists((string) ($pending['path'] ?? ''))) {
            throw ValidationException::withMessages([
                'import_token' => 'File belum divalidasi atau sesi import sudah berakhir. Silakan pilih dan validasi ulang file.',
            ]);
        }

        $file = new UploadedFile(
            Storage::disk('private')->path($pending['path']),
            $pending['file_name'],
            null,
            null,
            true,
        );
        $count = $excel->import($file);

        Storage::disk('private')->delete($pending['path']);
        $request->session()->forget('resident_import');

        return back()->with('status', "Import penduduk selesai: {$count} baris diproses.");
    }

    public function template(ResidentExcelService $excel)
    {
        $path = $excel->template();

        return response()->download($path, 'template-import-penduduk.xlsx')->deleteFileAfterSend(true);
    }

    private function discardPendingImport(Request $request): void
    {
        $pending = $request->session()->pull('resident_import');

        if (is_array($pending) && isset($pending['path'])) {
            Storage::disk('private')->delete($pending['path']);
        }
    }
}
