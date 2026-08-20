@php
    use Illuminate\Support\Str;

    $inputName = $name ?? 'file';
    $inputId = $id ?? 'dropzone-'.Str::slug(str_replace(['[', ']'], '-', $inputName)).'-'.Str::random(6);
    $labelText = $label ?? 'Upload File';
    $helpText = $help ?? 'Tarik & lepas file ke kotak ini atau klik untuk memilih file.';
    $acceptValue = $accept ?? null;
    $isRequired = (bool) ($required ?? false);
    $allowMultiple = (bool) ($multiple ?? false);
    $iconText = $icon ?? '📎';
@endphp

<div class="dropzone-field" data-dropzone>
    <label class="dropzone-label" for="{{ $inputId }}">
        {{ $labelText }}
        @if($isRequired)<span class="badge">Wajib</span>@endif
    </label>
    <div class="dropzone-box" data-dropzone-box>
        <div class="dropzone-icon" aria-hidden="true">{{ $iconText }}</div>
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
