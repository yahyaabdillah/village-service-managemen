@php
    $isNew = $requirement === null;
    $id = $isNew ? 'dialog-req-new' : 'dialog-req-'.$requirement->id;
    $reopen = old('_dialog') === $id;
    $v = fn (string $key, $default = null) => old('_dialog') === $id ? old($key, $default) : $default;
    $selectedTypes = (array) $v('allowed_file_types', array_diff($requirement?->allowed_file_types ?? ['pdf', 'jpg', 'png'], ['jpeg']));
@endphp
<dialog class="form-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if($reopen) data-open @endif>
    <form method="POST" class="dialog-card" action="{{ $isNew ? route('admin.service-types.requirements.store', $service) : route('admin.service-types.requirements.update', [$service, $requirement]) }}" novalidate>
        @csrf @if(! $isNew) @method('PATCH') @endif
        <input type="hidden" name="_dialog" value="{{ $id }}">
        <div class="dialog-head">
            <h2 id="{{ $id }}-title">{{ $isNew ? 'Tambah syarat berkas' : 'Ubah syarat berkas' }}</h2>
            <button class="btn ghost icon" type="button" data-dialog-close aria-label="Tutup"><i data-lucide="x"></i></button>
        </div>
        <div class="field-grid dialog-grid">
            <div class="field span-2">
                <label for="{{ $id }}-name">Nama berkas<span class="req">*</span></label>
                <input id="{{ $id }}-name" name="name" value="{{ $v('name', $requirement?->name) }}" required placeholder="Contoh: KTP pemohon">
                @if($reopen && $errors->has('name'))<p class="field-error">{{ $errors->first('name') }}</p>@endif
            </div>
            <div class="field span-2">
                <label for="{{ $id }}-desc">Petunjuk untuk warga</label>
                <textarea id="{{ $id }}-desc" name="description" rows="2" placeholder="Contoh: foto atau pindaian KTP yang masih berlaku">{{ $v('description', $requirement?->description) }}</textarea>
            </div>
            <div class="field span-2">
                <span class="field-label">Jenis berkas yang diterima</span>
                <div class="check-group">
                    @foreach($fileTypes as $value => $label)
                        <label class="check-row"><input type="checkbox" name="allowed_file_types[]" value="{{ $value }}" @checked(in_array($value, $selectedTypes, true))> {{ $label }}</label>
                    @endforeach
                </div>
                @if($reopen && $errors->has('allowed_file_types'))<p class="field-error">{{ $errors->first('allowed_file_types') }}</p>@endif
            </div>
            <div class="field">
                <label for="{{ $id }}-size">Ukuran maksimal (KB)</label>
                <input id="{{ $id }}-size" name="max_file_size_kb" type="number" min="100" max="6144" step="128" value="{{ $v('max_file_size_kb', $requirement?->max_file_size_kb ?? 5120) }}">
                <p class="field-help">1024 KB = 1 MB. Batas sistem 6 MB.</p>
            </div>
            <div class="field">
                <label for="{{ $id }}-order">Urutan</label>
                <input id="{{ $id }}-order" name="sort_order" type="number" min="0" value="{{ $v('sort_order', $requirement?->sort_order) }}" placeholder="otomatis">
            </div>
            <div class="field">
                <input type="hidden" name="is_required" value="0">
                <label class="check-row" for="{{ $id }}-required"><input id="{{ $id }}-required" type="checkbox" name="is_required" value="1" @checked($v('is_required', $requirement?->is_required ?? true))> Wajib dilampirkan</label>
            </div>
        </div>
        <div class="dialog-actions">
            <button class="btn secondary" type="button" data-dialog-close>Batal</button>
            <button class="btn" type="submit"><i data-lucide="check"></i> {{ $isNew ? 'Tambahkan' : 'Simpan perubahan' }}</button>
        </div>
    </form>
</dialog>
