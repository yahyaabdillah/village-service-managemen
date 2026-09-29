@php
    $isNew = $field === null;
    $id = $isNew ? 'dialog-field-new' : 'dialog-field-'.$field->id;
    $formId = $id.'-form';
    // Re-open the dialog that failed validation so the message lands where the user was.
    $reopen = session('dialog') === $id || ($isNew && $errors->any() && old('_dialog') === $id) || (! $isNew && old('_dialog') === $id);
    $v = fn (string $key, $default = null) => old('_dialog') === $id ? old($key, $default) : $default;
@endphp
<dialog class="form-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if($reopen) data-open @endif>
    <form method="POST" class="dialog-card" id="{{ $formId }}" action="{{ $isNew ? route('admin.service-types.fields.store', $service) : route('admin.service-types.fields.update', [$service, $field]) }}" novalidate>
        @csrf @if(! $isNew) @method('PATCH') @endif
        <input type="hidden" name="_dialog" value="{{ $id }}">
        <div class="dialog-head">
            <h2 id="{{ $id }}-title">{{ $isNew ? 'Tambah isian formulir' : 'Ubah isian' }}</h2>
            <button class="btn ghost icon" type="button" data-dialog-close aria-label="Tutup"><i data-lucide="x"></i></button>
        </div>
        <div class="field-grid dialog-grid">
            <div class="field span-2">
                <label for="{{ $id }}-label">Pertanyaan / label<span class="req">*</span></label>
                <input id="{{ $id }}-label" name="label" value="{{ $v('label', $field?->label) }}" required placeholder="Contoh: Nama usaha" data-slug-source="#{{ $id }}-key">
                @if($reopen && $errors->has('label'))<p class="field-error">{{ $errors->first('label') }}</p>@endif
            </div>
            <div class="field">
                <label for="{{ $id }}-type">Tipe isian<span class="req">*</span></label>
                <select id="{{ $id }}-type" name="field_type" required data-field-type>
                    @foreach($fieldTypes as $value => $label)<option value="{{ $value }}" @selected($v('field_type', $field?->field_type ?? 'text') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="{{ $id }}-key">Nama variabel</label>
                <input id="{{ $id }}-key" name="field_key" value="{{ $v('field_key', $field?->field_key) }}" pattern="[a-z][a-z0-9_]*" placeholder="dibuat otomatis dari label" @if(! $isNew) data-slug-locked @endif>
                @if($reopen && $errors->has('field_key'))<p class="field-error">{{ $errors->first('field_key') }}</p>@else<p class="field-help">Dipakai di template surat, misalnya <code>{{ '{'.'{ nama_usaha }'.'}' }}</code>. Huruf kecil dan garis bawah.</p>@endif
            </div>
            <div class="field span-2" data-options-wrap @if($v('field_type', $field?->field_type ?? 'text') !== 'select') hidden @endif>
                <label for="{{ $id }}-options">Daftar pilihan<span class="req">*</span></label>
                <textarea id="{{ $id }}-options" name="options_text" rows="4" placeholder="Satu pilihan per baris">{{ $v('options_text', implode("\n", $field?->options ?? [])) }}</textarea>
                @if($reopen && $errors->has('options_text'))<p class="field-error">{{ $errors->first('options_text') }}</p>@else<p class="field-help">Warga memilih salah satu. Tulis satu pilihan per baris.</p>@endif
            </div>
            <div class="field">
                <label for="{{ $id }}-placeholder">Contoh isian</label>
                <input id="{{ $id }}-placeholder" name="placeholder" value="{{ $v('placeholder', $field?->placeholder) }}" placeholder="Tampil samar di dalam kotak isian">
            </div>
            <div class="field">
                <label for="{{ $id }}-order">Urutan</label>
                <input id="{{ $id }}-order" name="sort_order" type="number" min="0" value="{{ $v('sort_order', $field?->sort_order) }}" placeholder="otomatis">
            </div>
            <div class="field span-2">
                <label for="{{ $id }}-help">Petunjuk untuk warga</label>
                <textarea id="{{ $id }}-help" name="help_text" rows="2" placeholder="Keterangan singkat di bawah isian">{{ $v('help_text', $field?->help_text) }}</textarea>
            </div>
            <div class="field">
                <input type="hidden" name="is_required" value="0">
                <label class="check-row" for="{{ $id }}-required"><input id="{{ $id }}-required" type="checkbox" name="is_required" value="1" @checked($v('is_required', $field?->is_required ?? true))> Wajib diisi</label>
            </div>
            <div class="field">
                <input type="hidden" name="is_active" value="0">
                <label class="check-row" for="{{ $id }}-active"><input id="{{ $id }}-active" type="checkbox" name="is_active" value="1" @checked($v('is_active', $field?->is_active ?? true))> Tampil di formulir warga</label>
            </div>
        </div>
        <div class="dialog-actions">
            <button class="btn secondary" type="button" data-dialog-close>Batal</button>
            <button class="btn" type="submit"><i data-lucide="check"></i> {{ $isNew ? 'Tambahkan' : 'Simpan perubahan' }}</button>
        </div>
    </form>
</dialog>
