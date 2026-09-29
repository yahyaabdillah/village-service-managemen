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
                        <td class="resource-col">{{ $meta['label'] }}<small>{{ $meta['description'] }}</small></td>
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
        <div class="permission-matrix-head"><span class="badge plain">Tindakan khusus</span><span class="muted">Tindakan alur kerja yang tidak termasuk lihat/tambah/ubah/hapus.</span></div>
        <div class="table-wrap">
            <table>
                <tbody>
                    @foreach($extras as $resource => $meta)
                        <tr data-matrix-row>
                            <td class="resource-col">{{ $meta['label'] }}</td>
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
