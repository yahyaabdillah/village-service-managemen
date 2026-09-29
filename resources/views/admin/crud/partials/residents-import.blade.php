@php($previewOpen = (bool) session('import_preview'))
<details class="card import-details" @if($previewOpen) open @endif>
    <summary><i data-lucide="upload"></i> Impor data penduduk dari CSV/Excel <span class="badge plain">unduh <a href="{{ route('admin.residents.template') }}">template</a></span></summary>
    <section class="import-panel" aria-labelledby="resident-import-title">
        <div class="import-panel-head">
            <div>
                <h2 id="resident-import-title" class="section-h2">Impor data penduduk</h2>
                <p class="muted">Pilih satu berkas, periksa isinya, lalu konfirmasi. Baris dengan NIK yang sudah ada akan diperbarui.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.residents.import-preview') }}" class="filters import-form">
            @csrf
            @include('components.dropzone-file', [
                'name' => 'csv',
                'id' => 'residents-import-file',
                'label' => 'Berkas data penduduk',
                'accept' => '.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'required' => true,
                'icon' => 'table',
                'help' => 'CSV atau Excel, maksimal 5 MB. Isi diperiksa dahulu sebelum disimpan.',
            ])
            <button class="btn secondary" type="submit">Periksa berkas</button>
        </form>

        @if(session('import_preview'))
            <div class="import-preview" aria-live="polite">
                <div>
                    <h3>Hasil pemeriksaan</h3>
                    <p><strong>{{ session('import_preview.file_name') }}</strong></p>
                    <p class="muted">{{ session('import_preview.valid_rows') }} dari {{ session('import_preview.total_rows') }} baris valid.</p>
                </div>

                @if(session('import_preview.can_import') && session('resident_import.token'))
                    <div class="notice alert"><i data-lucide="circle-check"></i><span>Berkas valid dan siap diimpor. Periksa nama berkas serta jumlah baris sebelum melanjutkan.</span></div>
                    <form method="POST" action="{{ route('admin.residents.import') }}">
                        @csrf
                        <input type="hidden" name="import_token" value="{{ session('resident_import.token') }}">
                        <button class="btn" type="submit"><i data-lucide="upload"></i> Impor {{ session('import_preview.valid_rows') }} baris</button>
                    </form>
                @else
                    <div class="notice errors" role="alert">
                        <i data-lucide="circle-alert"></i>
                        <div>
                            <strong>Berkas belum dapat diimpor.</strong>
                            <ul>@foreach(session('import_preview.errors', []) as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>
</details>
