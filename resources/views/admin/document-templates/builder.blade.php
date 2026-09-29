@extends('layouts.admin', ['title' => $template->name])
@section('content')
@php
    $builderFields = $template->fields->map(fn ($field) => [
        'id' => $field->id,
        'label' => $field->label,
        'variable_key' => $field->variable_key,
        'mapping_config' => $field->mapping_config ?: ['version' => 1, 'mode' => 'source', 'key' => $field->variable_key],
        'page_number' => (int) $field->page_number,
        'x_position' => (float) $field->x_position,
        'y_position' => (float) $field->y_position,
        'width' => (float) ($field->width ?: 25),
        'height' => (float) ($field->height ?: 5),
        'font_size' => (float) $field->font_size,
        'font_weight' => $field->font_weight ?: 'normal',
        'text_align' => $field->text_align,
        'text_color' => $field->text_color,
        'update_url' => route('admin.document-templates.fields.update', [$template, $field]),
        'delete_url' => route('admin.document-templates.fields.destroy', [$template, $field]),
    ])->values();
    $canUpdate = auth()->user()->can('document-templates.update');
    $settingsOpen = old('_dialog') === 'dialog-template-settings';
@endphp
<div class="builder-page {{ $canUpdate ? '' : 'read-only' }}" id="document-builder"
     data-preview-url="{{ route('admin.document-templates.preview', $template) }}"
     data-store-url="{{ route('admin.document-templates.fields.store', $template) }}"
     data-variable-url="{{ route('admin.document-templates.variables.store', $template) }}"
     data-date-keys='@json($dateKeys)'
     data-read-only="{{ $canUpdate ? '0' : '1' }}">
    <header class="builder-head">
        <div>
            <a class="back-link" href="{{ route('admin.service-types.edit', [$template->service_type_id, 'tab' => 'template']) }}"><i data-lucide="arrow-left"></i> {{ $template->serviceType?->name ?? 'Layanan' }}</a>
            <h1>{{ $template->name }} <span class="badge {{ $template->statusTone() }}">{{ $template->statusLabel() }}</span></h1>
            <p class="muted">Versi {{ $template->version }} · {{ $template->page_count }} halaman · {{ $template->fields->count() }} teks ditempatkan{{ $template->description ? ' · '.$template->description : '' }}</p>
        </div>
        <div class="actions builder-actions">
            @if($canUpdate)<span class="builder-status" data-builder-status><span class="system-dot"></span><span>Tersimpan otomatis</span></span>@endif
            @if($template->fields->isNotEmpty())
                <a class="btn secondary small" href="{{ route('admin.document-templates.sample', $template) }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> Lihat contoh hasil</a>
            @endif
            @if($canUpdate)
                <button class="btn ghost icon" type="button" data-dialog-open="#dialog-template-settings" aria-label="Pengaturan template" title="Pengaturan template"><i data-lucide="settings-2"></i></button>
                @if(! $template->isLive())
                    <form method="POST" action="{{ route('admin.document-templates.activate', $template) }}" data-confirm="Pakai template ini untuk {{ $template->serviceType?->name }}?" data-confirm-text="Mulai sekarang surat yang diterbitkan petugas dicetak dengan template ini. Template lain untuk layanan yang sama berhenti dipakai." data-confirm-label="Ya, pakai template ini" data-confirm-tone="">
                        @csrf @method('PATCH')
                        <button class="btn small" type="submit"><i data-lucide="badge-check"></i> Aktifkan &amp; pakai</button>
                    </form>
                @endif
            @endif
        </div>
    </header>

    <div class="builder-shell">
        <aside class="builder-panel left" aria-label="Data yang bisa dicetak">
            <div class="panel-title-row">
                <p class="panel-label">Data yang bisa dicetak</p>
                @if($canUpdate)<button class="btn secondary small" type="button" data-open-variable><i data-lucide="plus"></i> Pertanyaan baru untuk warga</button>@endif
            </div>
            <p class="panel-hint">Klik salah satu untuk menempatkannya di halaman yang sedang dibuka.</p>
            <label class="sr-only" for="variable-search">Cari data</label>
            <input id="variable-search" class="palette-search" type="search" placeholder="Cari, misalnya “nama”" data-variable-search>
            <div class="field-palette" data-variable-list>
                @foreach(collect($variables)->groupBy('group') as $group => $items)
                    <div class="palette-group" data-variable-group="{{ $group }}">
                        <strong class="palette-group-title">{{ $group }}</strong>
                        @foreach($items as $variable)
                            <button class="palette-item" type="button" data-add-field data-key="{{ $variable['key'] }}" data-label="{{ $variable['label'] }}" @disabled(! $canUpdate)>
                                <span class="palette-item-icon"><i data-lucide="{{ $variable['source'] === 'form' ? 'message-square-text' : 'type' }}"></i></span>
                                <span><strong>{{ $variable['label'] }}</strong><small>Contoh: {{ $variable['sample'] }}{{ $variable['is_active'] ? '' : ' · belum ditanyakan ke warga' }}</small></span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
                <p class="palette-empty" data-palette-empty hidden>Tidak ada data dengan nama itu. Tambahkan sebagai pertanyaan baru bila perlu.</p>
            </div>
            <div class="builder-steps">
                <strong>Cara kerja</strong>
                <ol>
                    <li>Klik data di daftar ini; teksnya muncul di halaman.</li>
                    <li>Geser ke tempat yang kosong pada surat, atur ukurannya.</li>
                    <li>Cek lewat <em>Lihat contoh hasil</em>, lalu aktifkan.</li>
                </ol>
            </div>
        </aside>

        <section class="builder-workspace" aria-label="Halaman surat">
            <div class="canvas-toolbar">
                <div class="toolbar-group">
                    <button class="tool-button" type="button" data-prev-page aria-label="Halaman sebelumnya"><i data-lucide="chevron-left"></i></button>
                    <span class="tool-button" aria-live="polite">Hal. <span data-page-current>1</span>&nbsp;/&nbsp;<span data-page-count>{{ $template->page_count }}</span></span>
                    <button class="tool-button" type="button" data-next-page aria-label="Halaman berikutnya"><i data-lucide="chevron-right"></i></button>
                </div>
                <div class="toolbar-group">
                    <button class="tool-button" type="button" data-zoom-out aria-label="Perkecil"><i data-lucide="zoom-out"></i></button>
                    <span class="tool-button"><span data-zoom-label>100%</span></span>
                    <button class="tool-button" type="button" data-zoom-in aria-label="Perbesar"><i data-lucide="zoom-in"></i></button>
                    <button class="tool-button" type="button" data-fit-page><i data-lucide="maximize"></i> Pas</button>
                </div>
            </div>
            <div class="canvas-stage">
                <div class="pdf-page" data-pdf-page>
                    <canvas data-pdf-canvas></canvas>
                    <div class="field-layer" data-field-layer></div>
                    <div class="canvas-empty" data-canvas-empty><div><i data-lucide="mouse-pointer-click"></i><strong>Halaman ini belum berisi data</strong><p>Pilih data dari daftar di kiri untuk menempatkannya di sini.</p></div></div>
                </div>
            </div>
        </section>

        <aside class="builder-panel right" aria-label="Pengaturan teks">
            <div class="panel-title-row"><p class="panel-label">Teks terpilih</p><button class="btn ghost icon inspector-close" type="button" data-inspector-close aria-label="Tutup"><i data-lucide="x"></i></button></div>
            <div class="property-empty" data-property-empty><div><i data-lucide="sliders-horizontal"></i><strong>Belum ada teks dipilih</strong><p>Klik salah satu kotak teks pada halaman untuk mengatur isinya.</p></div></div>
            <div class="property-form" data-property-form>
                <div class="inspector-section">
                    <label for="builder-label">Nama teks</label><input id="builder-label" data-property="label">
                    <p class="field-help">Hanya untuk Anda; tidak dicetak.</p>
                </div>
                <div class="inspector-section">
                    <h3>Isi yang dicetak</h3>
                    <div class="segmented" role="radiogroup" aria-label="Jenis isi">
                        <button type="button" class="segmented-item" data-mode="source">Satu data</button>
                        <button type="button" class="segmented-item" data-mode="literal">Teks tetap</button>
                        <button type="button" class="segmented-item" data-mode="segments">Gabungan</button>
                    </div>
                    <select class="sr-only" data-mapping-mode aria-hidden="true" tabindex="-1"><option value="source">Satu data</option><option value="literal">Teks tetap</option><option value="segments">Gabungan</option></select>
                    <div data-mapping-source>
                        <label for="builder-variable">Data</label>
                        <select id="builder-variable" data-mapping-key>
                            @foreach(collect($variables)->groupBy('group') as $group => $items)
                                <optgroup label="{{ $group }}">@foreach($items as $variable)<option value="{{ $variable['key'] }}">{{ $variable['label'] }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                        <div class="property-row"><div><label for="builder-prefix">Teks sebelum</label><input id="builder-prefix" data-mapping-option="prefix" placeholder="Nomor: "></div><div><label for="builder-suffix">Teks sesudah</label><input id="builder-suffix" data-mapping-option="suffix"></div></div>
                        <label for="builder-fallback">Bila datanya kosong, cetak</label><input id="builder-fallback" data-mapping-option="fallback" placeholder="-">
                        <div data-date-format-wrap hidden>
                            <label for="builder-date-format">Bentuk tanggal</label>
                            <select id="builder-date-format" data-mapping-option="date_format"><option value="">Bawaan</option>@foreach($dateFormats as $format => $example)<option value="{{ $format }}">{{ $example }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div data-mapping-literal hidden><label for="builder-literal">Teks</label><textarea id="builder-literal" rows="3" data-mapping-value placeholder="Teks yang selalu sama di setiap surat"></textarea></div>
                    <div data-mapping-segments hidden>
                        <p class="field-help">Beberapa bagian dicetak berurutan, misalnya “Ngringo, ” + tanggal surat.</p>
                        <div class="segment-list" data-segment-list></div>
                        <button class="btn secondary small full" type="button" data-add-segment><i data-lucide="plus"></i> Tambah bagian</button>
                    </div>
                </div>
                <div class="inspector-section">
                    <h3>Tampilan</h3>
                    <div class="property-row"><div><label for="builder-font-size">Ukuran huruf</label><input id="builder-font-size" type="number" min="6" max="72" step="1" data-property="font_size"></div><div><label for="builder-color">Warna</label><input id="builder-color" type="color" data-property="text_color"></div></div>
                    <div class="property-row">
                        <div><label>Perataan</label><div class="align-buttons"><button class="align-button" type="button" data-align="left" aria-label="Rata kiri"><i data-lucide="align-left"></i></button><button class="align-button" type="button" data-align="center" aria-label="Rata tengah"><i data-lucide="align-center"></i></button><button class="align-button" type="button" data-align="right" aria-label="Rata kanan"><i data-lucide="align-right"></i></button></div></div>
                        <div><label>Tebal</label><button class="align-button bold-toggle" type="button" data-bold aria-pressed="false"><i data-lucide="bold"></i> Tebal</button></div>
                    </div>
                </div>
                <div class="inspector-section">
                    <h3>Posisi &amp; ukuran</h3><p class="field-help">Dalam persen dari halaman. Bisa juga digeser dengan mouse atau tombol panah.</p>
                    <div class="property-row"><div><label for="builder-x">Dari kiri</label><input id="builder-x" type="number" min="0" max="100" step=".1" data-property="x_position"></div><div><label for="builder-y">Dari atas</label><input id="builder-y" type="number" min="0" max="100" step=".1" data-property="y_position"></div></div>
                    <div class="property-row"><div><label for="builder-width">Lebar</label><input id="builder-width" type="number" min="1" max="100" step=".1" data-property="width"></div><div><label for="builder-height">Tinggi</label><input id="builder-height" type="number" min="1" max="100" step=".1" data-property="height"></div></div>
                </div>
                <div class="danger-zone"><button class="btn danger small" type="button" data-delete-field><i data-lucide="trash-2"></i> Hapus teks ini</button></div>
            </div>
        </aside>
    </div>

    @if($canUpdate)
        <dialog class="form-dialog" data-variable-dialog aria-labelledby="variable-dialog-title">
            <form method="dialog" class="dialog-card" data-variable-form novalidate>
                <div class="dialog-head"><h2 id="variable-dialog-title">Pertanyaan baru untuk warga</h2><button class="btn ghost icon" type="button" value="cancel" data-dialog-close aria-label="Tutup"><i data-lucide="x"></i></button></div>
                <p class="muted">Pertanyaan langsung tampil di formulir {{ $template->serviceType?->name }} dan jawabannya bisa dicetak di surat.</p>
                <div class="field-grid dialog-grid">
                    <div class="field span-2"><label for="variable-label">Pertanyaan<span class="req">*</span></label><input id="variable-label" name="label" required maxlength="255" placeholder="Contoh: Nama usaha"><p class="field-error" data-error="label" hidden></p></div>
                    <div class="field"><label for="variable-type">Jenis jawaban</label><select id="variable-type" name="field_type"><option value="text">Teks singkat</option><option value="textarea">Teks panjang</option><option value="number">Angka</option><option value="date">Tanggal</option><option value="email">Email</option><option value="select">Pilihan</option></select></div>
                    <div class="field"><label class="check-row" for="variable-required"><input id="variable-required" type="checkbox" name="is_required" value="1"> Wajib diisi</label></div>
                    <div class="field span-2" data-variable-options hidden><label for="variable-options">Pilihan jawaban</label><textarea id="variable-options" name="options_text" rows="3" placeholder="Satu pilihan per baris"></textarea><p class="field-error" data-error="options" hidden></p></div>
                    <div class="field"><label for="variable-placeholder">Contoh jawaban</label><input id="variable-placeholder" name="placeholder" maxlength="255" placeholder="Tampil samar di kotak isian"></div>
                    <div class="field"><label for="variable-help">Petunjuk singkat</label><input id="variable-help" name="help_text" maxlength="1000" placeholder="Tampil di bawah kotak isian"></div>
                </div>
                <div class="dialog-actions"><button class="btn ghost" type="button" value="cancel" data-dialog-close>Batal</button><button class="btn" type="submit" value="default"><i data-lucide="plus"></i> Tambah pertanyaan</button></div>
            </form>
        </dialog>

        <dialog class="form-dialog" id="dialog-template-settings" aria-labelledby="template-settings-title" @if($settingsOpen) data-open @endif>
            <form method="POST" class="dialog-card" action="{{ route('admin.document-templates.update', $template) }}" novalidate>
                @csrf @method('PATCH')
                <input type="hidden" name="_dialog" value="dialog-template-settings">
                <div class="dialog-head"><h2 id="template-settings-title">Pengaturan template</h2><button class="btn ghost icon" type="button" data-dialog-close aria-label="Tutup"><i data-lucide="x"></i></button></div>
                <div class="field-grid dialog-grid">
                    <div class="field span-2"><label for="tpl-name">Nama template<span class="req">*</span></label><input id="tpl-name" name="name" value="{{ $settingsOpen ? old('name', $template->name) : $template->name }}" required>@if($settingsOpen && $errors->has('name'))<p class="field-error">{{ $errors->first('name') }}</p>@endif</div>
                    <div class="field span-2"><label for="tpl-desc">Catatan</label><textarea id="tpl-desc" name="description" rows="2">{{ $settingsOpen ? old('description', $template->description) : $template->description }}</textarea></div>
                    @if($template->is_active && $template->status === 'active' && ! $template->is_default)
                        <div class="field span-2"><label class="check-row" for="tpl-default"><input id="tpl-default" type="checkbox" name="make_default" value="1"> Jadikan template yang dipakai untuk {{ $template->serviceType?->name }}</label></div>
                    @endif
                    <div class="field span-2"><p class="field-help">File: {{ $template->original_file_name }} · diunggah {{ $template->created_at?->translatedFormat('d F Y') }}. Untuk mengganti PDF, unggah sebagai template baru.</p></div>
                </div>
                <div class="dialog-actions"><button class="btn ghost" type="button" data-dialog-close>Batal</button><button class="btn" type="submit"><i data-lucide="check"></i> Simpan</button></div>
            </form>
            @can('document-templates.delete')
                <form method="POST" class="dialog-footer-danger" action="{{ route('admin.document-templates.destroy', $template) }}" data-confirm="Hapus template “{{ $template->name }}”?" data-confirm-text="{{ $template->isLive() ? 'Template ini sedang dipakai. Setelah dihapus, petugas tidak bisa menerbitkan '.$template->serviceType?->name.' sampai template lain diaktifkan.' : 'Surat yang sudah diterbitkan dengan template ini tetap tersimpan.' }}" data-confirm-label="Ya, hapus">
                    @csrf @method('DELETE')
                    <button class="btn ghost small danger-text" type="submit"><i data-lucide="trash-2"></i> Hapus template ini</button>
                </form>
            @endcan
        </dialog>
    @endif

    <div class="builder-toast" role="status" aria-live="polite" data-builder-toast></div>
    <script type="application/json" data-builder-fields>@json($builderFields)</script>
    <script type="application/json" data-builder-variables>@json($variables)</script>
</div>
@endsection
