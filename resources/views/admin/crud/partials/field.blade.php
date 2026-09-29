@php
    // $name, $def (from CrudSchema), $item, $options
    $type = $def['type'] ?? 'text';
    $fieldId = 'field-'.\Illuminate\Support\Str::slug($name);
    $required = ! empty($def['required']);
    $stored = $item->{$name};
    if ($type === 'tags' && is_array($stored)) { $stored = implode(', ', $stored); }
    if ($type === 'date' && $stored instanceof \DateTimeInterface) { $stored = $stored->format('Y-m-d'); }
    if ($type === 'date' && is_string($stored) && strlen($stored) > 10) { $stored = substr($stored, 0, 10); }
    $value = old($name, $item->exists ? $stored : ($def['default'] ?? null));
    $hasError = $errors->has($name) || $errors->has($name.'.*');
    $errorMessage = $errors->first($name) ?: $errors->first($name.'.*');
    $describedBy = trim(($hasError ? $fieldId.'-error ' : '').(! empty($def['help']) ? $fieldId.'-help' : ''));
    $attrs = collect($def['attrs'] ?? [])->map(fn ($v, $k) => $k.'="'.e($v).'"')->implode(' ');
    $isWide = in_array($type, ['textarea', 'permissions', 'roles'], true);
    // Phone controls split the stored "+62812…" into a country select and a local number.
    $rawPhone = (string) ($value ?? '');
    $localPhone = ltrim(preg_replace('/\D+/', '', preg_replace('/^\+?62/', '', $rawPhone)), '0');
@endphp
<div class="field {{ $isWide ? 'span-2' : '' }}">
    @if($type !== 'boolean')
        <label for="{{ $fieldId }}">{{ $def['label'] }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif

    @if($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" {!! $attrs !!} @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif placeholder="{{ $def['placeholder'] ?? '' }}">{{ $value }}</textarea>

    @elseif($type === 'select')
        @php($choices = is_callable($def['options'] ?? null) ? ($def['options'])() : ($def['options'] ?? []))
        <select id="{{ $fieldId }}" name="{{ $name }}" @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif>
            <option value="">{{ $def['placeholder'] ?? 'Pilih…' }}</option>
            @foreach($choices as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>

    @elseif($type === 'boolean')
        <input type="hidden" name="{{ $name }}" value="0">
        <label class="check-row" for="{{ $fieldId }}">
            <input id="{{ $fieldId }}" type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif>
            {{ $def['label'] }}
        </label>

    @elseif($type === 'password')
        <input id="{{ $fieldId }}" type="password" name="{{ $name }}" autocomplete="new-password" @required(! $item->exists) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif placeholder="{{ $item->exists ? 'Kosongkan bila tidak diganti' : '' }}">

    @elseif($type === 'phone')
        <div class="phone-input">
            <select class="phone-country" aria-label="Kode negara">
                <option value="+62" @selected(str_starts_with($rawPhone, '+62') || blank($rawPhone))>Indonesia (+62)</option>
                <option value="+60" @selected(str_starts_with($rawPhone, '+60'))>Malaysia (+60)</option>
                <option value="+65" @selected(str_starts_with($rawPhone, '+65'))>Singapura (+65)</option>
            </select>
            <input id="{{ $fieldId }}" class="phone-local" name="phone_number_display" value="{{ $localPhone }}" inputmode="numeric" autocomplete="tel-national" pattern="[1-9][0-9]{6,14}" placeholder="81234567890" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif>
            <input class="phone-combined" type="hidden" name="{{ $name }}" value="{{ $rawPhone }}">
        </div>

    @elseif($type === 'roles')
        @include('admin.crud.partials.roles-field', ['fieldId' => $fieldId, 'item' => $item, 'options' => $options])

    @elseif($type === 'permissions')
        @include('admin.crud.partials.permissions-field', ['item' => $item])

    @else
        @php($inputType = in_array($type, ['number', 'date', 'email', 'url'], true) ? $type : 'text')
        <input id="{{ $fieldId }}" type="{{ $inputType }}" name="{{ $name }}" value="{{ $value }}" {!! $attrs !!} @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif placeholder="{{ $def['placeholder'] ?? '' }}">
    @endif

    @if($hasError)
        <p class="field-error" id="{{ $fieldId }}-error">{{ $errorMessage }}</p>
    @elseif(! empty($def['help']))
        <p class="field-help" id="{{ $fieldId }}-help">{{ $def['help'] }}</p>
    @endif
</div>
