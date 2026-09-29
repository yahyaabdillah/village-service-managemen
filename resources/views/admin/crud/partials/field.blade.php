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
        $catalog = \App\Support\PermissionCatalog::resources();
        $standard = \App\Support\PermissionCatalog::STANDARD_ACTIONS;
        $isSuper = $item->exists && $item->name === \App\Support\PermissionCatalog::SUPER_ROLE;
        $selectedPermissions = collect(old('permissions', $item->exists ? $item->permissions->pluck('name')->all() : []))
            ->map(fn ($p) => (string) $p)->all();
        $extras = collect($catalog)->filter(fn ($meta) => ! empty($meta['extra']));
        $total = count(\App\Support\PermissionCatalog::all());
    @endphp
    @if($isSuper)
        <div class="notice info" role="note"><i data-lucide="shield-check"></i><div><strong>Role ini memegang seluruh izin.</strong> Super Admin otomatis mendapat setiap izin, termasuk yang ditambahkan di kemudian hari, dan matriksnya tidak dapat diubah.</div></div>
    @endif
    <div class="permission-matrix" data-permission-matrix @if($isSuper) data-locked @endif>
        <div class="permission-matrix-head">
            <span class="badge plain" data-permission-count>{{ $isSuper ? $total : count($selectedPermissions) }} dari {{ $total }} izin</span>
            @unless($isSuper)
                <button type="button" class="btn secondary small" data-matrix-preset="view">Hanya lihat semua</button>
                <button type="button" class="btn secondary small" data-matrix-preset="all">Pilih semua</button>
                <button type="button" class="btn secondary small" data-matrix-preset="none">Kosongkan</button>
            @endunless
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Menu</th>
                        @foreach($standard as $action => $label)<th class="action-col">{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($catalog as $resource => $meta)
                        <tr data-matrix-row>
                            <td class="resource-col">
                                {{ $meta['label'] }}<small>{{ $meta['group'] }} · {{ $meta['description'] }}</small>
                            </td>
                            @foreach($standard as $action => $label)
                                @php($name = "$resource.$action")
                                @if(in_array($action, $meta['actions'], true))
                                    <td class="action-col">
                                        <input type="checkbox" name="permissions[]" value="{{ $name }}" aria-label="{{ $meta['label'] }}: {{ $label }}"
                                               @checked($isSuper || in_array($name, $selectedPermissions, true)) @disabled($isSuper)>
                                    </td>
                                @else
                                    <td class="action-col na" aria-hidden="true">—</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($extras->isNotEmpty())
            <div class="permission-matrix-head"><span class="badge plain">Tindakan khusus</span></div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach($extras as $resource => $meta)
                            <tr data-matrix-row>
                                <td class="resource-col">{{ $meta['label'] }}<small>Tindakan yang tidak termasuk lihat/tambah/ubah/hapus.</small></td>
                                <td>
                                    <div class="extra-actions">
                                        @foreach($meta['extra'] as $action => $label)
                                            @php($name = "$resource.$action")
                                            <label class="check-row"><input type="checkbox" name="permissions[]" value="{{ $name }}" @checked($isSuper || in_array($name, $selectedPermissions, true)) @disabled($isSuper)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@else
    <input id="{{ $fieldId }}" name="{{ $field }}" value="{{ $rawValue }}" @if(in_array($field, ['rt','rw','sort_order','max_file_size_kb','family_card_id','service_type_id'])) inputmode="numeric" @endif>
@endif
@error($field)<p style="color:#dc2626">{{ $message }}</p>@enderror
