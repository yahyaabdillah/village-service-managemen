@php
    use Illuminate\Support\Str;

    $inputName = $name ?? 'file';
    $inputId = $id ?? 'dropzone-'.Str::slug(str_replace(['[', ']'], '-', $inputName)).'-'.Str::random(6);
    $labelText = $label ?? 'Unggah berkas';
    $helpText = $help ?? 'Tarik berkas ke kotak ini atau klik untuk memilih.';
    $acceptValue = $accept ?? null;
    $isRequired = (bool) ($required ?? false);
    $allowMultiple = (bool) ($multiple ?? false);
    // A lucide icon name (e.g. "file-text") or, for older call sites, literal text.
    $iconName = preg_match('/^[a-z0-9-]+$/', (string) ($icon ?? '')) ? $icon : null;
    $iconText = $iconName ? null : ($icon ?? null);
@endphp

<div class="dropzone-field" data-dropzone>
    <label class="dropzone-label" for="{{ $inputId }}">
        {{ $labelText }}
        @if($isRequired)<span class="req" aria-hidden="true">*</span>@else<span class="muted"> (opsional)</span>@endif
    </label>
    <div class="dropzone-box" data-dropzone-box>
        <div class="dropzone-icon" aria-hidden="true">@if($iconName)<i data-lucide="{{ $iconName }}"></i>@else{{ $iconText ?? '' }}@if(! $iconText)<i data-lucide="paperclip"></i>@endif @endif</div>
        <div>
            <strong>Tarik file ke sini atau klik untuk upload</strong>
            <p class="muted">{{ $helpText }}</p>
            <p class="dropzone-selected" data-dropzone-selected>Belum ada file dipilih.</p>
        </div>
    </div>
    <input
        id="{{ $inputId }}"
        class="dropzone-input"
        type="file"
        name="{{ $inputName }}"
        @if($acceptValue) accept="{{ $acceptValue }}" @endif
        @if($isRequired) required @endif
        @if($allowMultiple) multiple @endif
    >
</div>
