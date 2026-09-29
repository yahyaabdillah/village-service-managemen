@php
    $roleOptions = $options['roles'] ?? [];
    $canCreateRoles = (bool) ($options['can_create_roles'] ?? false);
    $currentRoles = collect(old('roles', $item->exists && method_exists($item, 'getRoleNames') ? $item->getRoleNames()->all() : []))
        ->map(fn ($role) => trim((string) $role))->filter()->unique()->values()->all();
    // Names chosen but not yet saved as roles (inline creation replayed by old()).
    $pendingRoles = array_values(array_diff($currentRoles, $roleOptions));
@endphp
<div class="combobox combobox-multi" data-combobox @if($canCreateRoles) data-combobox-addable @endif>
    {{-- The select carries the value: the form still works if JS never runs. --}}
    <select id="{{ $fieldId }}" class="combobox-source" name="roles[]" multiple size="4" data-combobox-source>
        @foreach($roleOptions as $roleOption)
            <option value="{{ $roleOption }}" @selected(in_array($roleOption, $currentRoles, true))>{{ $roleOption }}</option>
        @endforeach
        @foreach($pendingRoles as $pending)
            <option value="{{ $pending }}" selected>{{ $pending }}</option>
        @endforeach
    </select>
    <div class="combobox-control" data-combobox-shell hidden>
        <ul class="combobox-chips" data-combobox-chips></ul>
        <input class="combobox-input" type="text" role="combobox" aria-expanded="false" aria-autocomplete="list"
               aria-controls="{{ $fieldId }}-list" aria-label="Cari role" autocomplete="off">
        <button type="button" class="combobox-toggle" data-combobox-toggle aria-label="Tampilkan daftar role" tabindex="-1">
            <i data-lucide="chevron-right"></i>
        </button>
    </div>
    <ul class="combobox-list" id="{{ $fieldId }}-list" role="listbox" hidden></ul>
</div>
<p class="field-help">
    Bisa memilih lebih dari satu role.
    {{ $canCreateRoles
        ? 'Nama yang belum terdaftar akan dibuat sebagai role baru saat disimpan.'
        : 'Membuat role baru memerlukan izin mengelola role.' }}
</p>
