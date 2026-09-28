@php
    // `permissions` resolves to an Eloquent relation, so keep the string-only work
    // (phone splitting) inside its own branch rather than running it for every field.
    $rawValue = old($field, is_array($item->{$field}) ? implode(',', $item->{$field}) : $item->{$field});
    $fieldId = 'field-'.Str::slug($field);
@endphp
<label for="{{ $fieldId }}">{{ Str::of($field)->replace('_', ' ')->title() }}</label>
@if(in_array($field, ['description','address','content','excerpt','help_text']))
    <textarea id="{{ $fieldId }}" name="{{ $field }}">{{ $rawValue }}</textarea>
@elseif(in_array($field, ['is_active','is_required','is_published']))
    <select id="{{ $fieldId }}" name="{{ $field }}">
        <option value="1" @selected($rawValue == 1)>Ya</option>
        <option value="0" @selected($rawValue == 0)>Tidak</option>
    </select>
@elseif($field === 'password')
    <input id="{{ $fieldId }}" type="password" name="password" autocomplete="new-password" placeholder="{{ $item->exists ? 'Kosongkan jika tidak diganti' : '' }}">
@elseif($field === 'phone')
    @php
        $localPhone = ltrim(preg_replace('/\D+/', '', preg_replace('/^\+?62/', '', (string) $rawValue)), '0');
    @endphp
    <div class="phone-input">
        <select class="phone-country" aria-label="Kode negara">
            <option value="+62" @selected(str_starts_with((string) $rawValue, '+62') || blank($rawValue))>🇮🇩 +62</option>
            <option value="+60" @selected(str_starts_with((string) $rawValue, '+60'))>🇲🇾 +60</option>
            <option value="+65" @selected(str_starts_with((string) $rawValue, '+65'))>🇸🇬 +65</option>
            <option value="+673" @selected(str_starts_with((string) $rawValue, '+673'))>🇧🇳 +673</option>
        </select>
        <input id="{{ $fieldId }}" class="phone-local" name="phone_number_display" value="{{ $localPhone }}" inputmode="numeric" autocomplete="tel-national" pattern="[1-9][0-9]{6,14}" placeholder="81234567890">
        <input class="phone-combined" type="hidden" name="phone" value="{{ $rawValue }}">
    </div>
    <p class="muted">Kode negara dipilih terpisah; angka/huruf tidak valid dan 0 di awal otomatis dihapus.</p>
@elseif($field === 'roles')
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
    <p class="muted">
        Bisa memilih lebih dari satu role.
        {{ $canCreateRoles
            ? 'Nama yang belum terdaftar akan dibuat sebagai role baru saat disimpan.'
            : 'Membuat role baru butuh izin "manage roles".' }}
    </p>
@elseif($field === 'permissions')
    @php
        $allPermissions = $options['permissions'] ?? [];
        $selectedPermissions = collect(old('permissions', $item->exists ? $item->permissions->pluck('name')->all() : []))
            ->map(fn ($p) => (string) $p)->all();
    @endphp
    <div class="permission-picker" data-permission-picker>
        <div class="permission-picker-head">
            <input type="search" class="permission-search" data-permission-search placeholder="Cari izin..." aria-label="Cari izin" autocomplete="off">
            <span class="badge" data-permission-count>0 dari {{ count($allPermissions) }} izin</span>
            <button type="button" class="btn secondary" data-permission-all>Pilih semua</button>
            <button type="button" class="btn secondary" data-permission-none>Kosongkan</button>
        </div>
        <ul class="permission-list">
            @forelse($allPermissions as $permission)
                <li class="permission-item" data-permission-item>
                    <label>
                        <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                        <span>{{ $permission }}</span>
                    </label>
                </li>
            @empty
                <li class="muted">Belum ada permission terdaftar.</li>
            @endforelse
            <li class="permission-empty" data-permission-empty hidden>Tidak ada izin yang cocok.</li>
        </ul>
    </div>
@else
    <input id="{{ $fieldId }}" name="{{ $field }}" value="{{ $rawValue }}" @if(in_array($field, ['rt','rw','sort_order','max_file_size_kb','family_card_id','service_type_id'])) inputmode="numeric" @endif>
@endif
@error($field)<p style="color:#dc2626">{{ $message }}</p>@enderror
