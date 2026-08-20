@php
    $rawValue = old($field, is_array($item->{$field}) ? implode(',', $item->{$field}) : $item->{$field});
    $localPhone = preg_replace('/^\+?62/', '', (string) $rawValue);
    $localPhone = ltrim(preg_replace('/\D+/', '', $localPhone), '0');
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
@else
    <input id="{{ $fieldId }}" name="{{ $field }}" value="{{ $rawValue }}" @if(in_array($field, ['rt','rw','sort_order','max_file_size_kb','family_card_id','service_type_id'])) inputmode="numeric" @endif>
@endif
@error($field)<p style="color:#dc2626">{{ $message }}</p>@enderror
