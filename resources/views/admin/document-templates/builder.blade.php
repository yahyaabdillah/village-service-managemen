@extends('layouts.admin')
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
        'text_align' => $field->text_align,
        'text_color' => $field->text_color,
        'update_url' => route('admin.document-templates.fields.update', [$template, $field]),
        'delete_url' => route('admin.document-templates.fields.destroy', [$template, $field]),
    ])->values();
@endphp
<div class="builder-page" id="document-builder"
     data-preview-url="{{ route('admin.document-templates.preview', $template) }}"
     data-store-url="{{ route('admin.document-templates.fields.store', $template) }}"
     data-variable-url="{{ route('admin.document-templates.variables.store', $template) }}">
    <header class="builder-head">
        <div>
            <a class="back-link" href="{{ route('admin.document-templates.index') }}"><i data-lucide="arrow-left"></i> Template</a>
            <h1>{{ $template->name }} <span class="badge {{ $template->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($template->status) }} · v{{ $template->version }}</span></h1>
            <p class="muted">{{ $template->serviceType?->name }} · {{ $template->original_file_name }}</p>
        </div>
        <div class="actions">
            <span class="builder-status" data-builder-status><span class="system-dot"></span><span>Semua perubahan tersimpan</span></span>
            <form method="POST" action="{{ route('admin.document-templates.activate', $template) }}">
                @csrf @method('PATCH')
                <button class="btn small" type="submit"><i data-lucide="badge-check"></i> Validasi & Aktifkan</button>
            </form>
        </div>
    </header>

    @if($errors->any())
        <div class="alert error" role="alert"><strong>Template belum siap.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="builder-shell">
        <aside class="builder-panel left" aria-label="Variable dokumen">
            <div class="panel-title-row"><p class="panel-label">Variable dokumen</p><button class="btn light small" type="button" data-open-variable><i data-lucide="plus"></i> Buat</button></div>
            <label class="sr-only" for="variable-search">Cari variable</label>
            <input id="variable-search" class="palette-search" type="search" placeholder="Cari variable…" data-variable-search>
            <div class="field-palette" data-variable-list>
                @foreach(collect($variables)->groupBy('group') as $group => $items)
                    <div class="palette-group" data-variable-group>
                        <strong class="palette-group-title">{{ $group }}</strong>
                        @foreach($items as $variable)
                            <button class="palette-item" type="button" data-add-field data-key="{{ $variable['key'] }}" data-label="{{ $variable['label'] }}">
                                <span class="palette-item-icon"><i data-lucide="braces"></i></span>
                                <span><strong>{{ $variable['label'] }}</strong><small>{{ $variable['key'] }}{{ $variable['is_active'] ? '' : ' · draft' }}</small></span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="builder-tip"><strong>Alur</strong><br>Buat/pilih variable → tempatkan → atur mapping → aktifkan template.</div>
        </aside>

        <section class="builder-workspace" aria-label="Canvas dokumen">
            <div class="canvas-toolbar">
                <div class="toolbar-group">
                    <button class="tool-button" type="button" data-prev-page aria-label="Halaman sebelumnya"><i data-lucide="chevron-left"></i></button>
                    <span class="tool-button" aria-live="polite"><span data-page-current>1</span>&nbsp;/&nbsp;<span data-page-count>{{ $template->page_count }}</span></span>
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
                    <div class="canvas-empty" data-canvas-empty><div><i data-lucide="mouse-pointer-2"></i><strong>Pilih variable dari panel kiri</strong><p>Variable akan ditempatkan di halaman aktif.</p></div></div>
                </div>
            </div>
        </section>

        <aside class="builder-panel right" aria-label="Inspector field">
            <p class="panel-label">Inspector</p>
            <div class="property-empty" data-property-empty><div><i data-lucide="sliders-horizontal"></i><strong>Belum ada field dipilih</strong><p>Pilih field pada dokumen.</p></div></div>
            <div class="property-form" data-property-form>
                <div class="inspector-section">
                    <h3>Data & Mapping</h3>
                    <label for="builder-label">Label elemen</label><input id="builder-label" data-property="label">
                    <label for="builder-mapping-mode">Mode</label>
                    <select id="builder-mapping-mode" data-mapping-mode>
                        <option value="source">Sumber tunggal</option>
                        <option value="literal">Teks tetap</option>
                        <option value="segments">Gabungan segmen</option>
                    </select>
                    <div data-mapping-source>
                        <label for="builder-variable">Sumber variable</label>
                        <select id="builder-variable" data-mapping-key>
                            @foreach($variables as $variable)<option value="{{ $variable['key'] }}">{{ $variable['label'] }} — {{ $variable['key'] }}</option>@endforeach
                        </select>
                        <div class="property-row"><div><label for="builder-prefix">Prefix</label><input id="builder-prefix" data-mapping-option="prefix" placeholder="Nomor: "></div><div><label for="builder-suffix">Suffix</label><input id="builder-suffix" data-mapping-option="suffix"></div></div>
                        <label for="builder-fallback">Fallback bila kosong</label><input id="builder-fallback" data-mapping-option="fallback" placeholder="-">
                        <label for="builder-date-format">Format tanggal (opsional)</label><select id="builder-date-format" data-mapping-option="date_format"><option value="">Tanpa format</option>@foreach($dateFormats as $format)<option value="{{ $format }}">{{ $format }}</option>@endforeach</select>
                    </div>
                    <div data-mapping-literal hidden><label for="builder-literal">Teks tetap</label><textarea id="builder-literal" rows="3" data-mapping-value></textarea></div>
                    <div data-mapping-segments hidden>
                        <div class="segment-list" data-segment-list></div>
                        <button class="btn light small full" type="button" data-add-segment><i data-lucide="plus"></i> Tambah segmen</button>
                    </div>
                </div>
                <div class="inspector-section">
                    <h3>Tampilan</h3>
                    <div class="property-row"><div><label for="builder-font-size">Ukuran huruf</label><input id="builder-font-size" type="number" min="6" max="72" step="1" data-property="font_size"></div><div><label for="builder-color">Warna</label><input id="builder-color" type="color" data-property="text_color"></div></div>
                    <label>Perataan</label><div class="align-buttons"><button class="align-button" type="button" data-align="left" aria-label="Rata kiri"><i data-lucide="align-left"></i></button><button class="align-button" type="button" data-align="center" aria-label="Rata tengah"><i data-lucide="align-center"></i></button><button class="align-button" type="button" data-align="right" aria-label="Rata kanan"><i data-lucide="align-right"></i></button></div>
                </div>
                <div class="inspector-section">
                    <h3>Posisi</h3><p class="field-help">Dapat diubah dengan drag atau keyboard panah saat field fokus.</p>
                    <div class="property-row"><div><label for="builder-x">X (%)</label><input id="builder-x" type="number" min="0" max="100" step=".1" data-property="x_position"></div><div><label for="builder-y">Y (%)</label><input id="builder-y" type="number" min="0" max="100" step=".1" data-property="y_position"></div></div>
                    <div class="property-row"><div><label for="builder-width">Lebar (%)</label><input id="builder-width" type="number" min="1" max="100" step=".1" data-property="width"></div><div><label for="builder-height">Tinggi (%)</label><input id="builder-height" type="number" min="1" max="100" step=".1" data-property="height"></div></div>
                </div>
                <div class="danger-zone"><button class="btn danger small" type="button" data-delete-field><i data-lucide="trash-2"></i> Hapus field</button></div>
            </div>
        </aside>
    </div>

    <dialog class="builder-dialog" data-variable-dialog aria-labelledby="variable-dialog-title">
        <form method="dialog" class="dialog-card" data-variable-form>
            <div class="section-compact-head"><div><small>Form warga</small><h2 id="variable-dialog-title">Buat Variable Form</h2></div><button class="tool-button" value="cancel" formnovalidate aria-label="Tutup"><i data-lucide="x"></i></button></div>
            <p class="muted">Variable dibuat sebagai draft. Variable aktif saat template diaktifkan.</p>
            <label for="variable-label">Label</label><input id="variable-label" name="label" required maxlength="255" placeholder="Contoh: Keperluan">
            <label for="variable-key">Key</label><input id="variable-key" name="field_key" required pattern="[a-z][a-z0-9_]*" maxlength="100" placeholder="keperluan"><small class="field-help">Huruf kecil, angka, dan underscore.</small>
            <label for="variable-type">Tipe input</label><select id="variable-type" name="field_type" required><option value="text">Teks singkat</option><option value="textarea">Teks panjang</option><option value="number">Angka</option><option value="date">Tanggal</option><option value="email">Email</option><option value="select">Pilihan</option></select>
            <div data-variable-options hidden><label for="variable-options">Pilihan</label><textarea id="variable-options" name="options_text" rows="4" placeholder="Satu pilihan per baris"></textarea></div>
            <label for="variable-placeholder">Placeholder</label><input id="variable-placeholder" name="placeholder" maxlength="255">
            <label for="variable-help">Petunjuk</label><textarea id="variable-help" name="help_text" rows="2" maxlength="1000"></textarea>
            <label class="check-row"><input type="checkbox" name="is_required" value="1"> Wajib diisi warga</label>
            <div class="dialog-actions"><button class="btn light" value="cancel" formnovalidate>Batal</button><button class="btn" type="submit" value="default"><i data-lucide="plus"></i> Buat Variable</button></div>
        </form>
    </dialog>

    <div class="builder-toast" role="status" aria-live="polite" data-builder-toast></div>
    <script type="application/json" data-builder-fields>@json($builderFields)</script>
    <script type="application/json" data-builder-variables>@json($variables)</script>
</div>
@endsection
