@props(['name', 'meta' => [], 'value' => null])

@php
    $type = $meta['type'] ?? 'text';
    $required = (bool) ($meta['required'] ?? false);
    $valueFingerprint = is_array($value) ? json_encode($value) : (string) $value;
    $inputId = 'config-'.str_replace(['[', ']'], ['-', ''], $name).'-'.substr(md5($valueFingerprint.$name), 0, 6);
@endphp

<div class="field">
    <label for="{{ $inputId }}" @class(['required' => $required])>{{ __('field.'.$name) }}</label>
    @if($type === 'textarea')
        <textarea id="{{ $inputId }}" name="{{ $name }}" @required($required)>{{ old($name, $value) }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $inputId }}" name="{{ $name }}" @required($required)>
            @foreach(($meta['options'] ?? []) as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif($type === 'multiselect')
        @php($selectedValues = array_map('strval', (array) old($name, $value ?? [])))
        <select id="{{ $inputId }}" name="{{ $name }}[]" multiple size="8">
            @foreach(($meta['options'] ?? []) as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $selectedValues, true))>{{ $label }}</option>
            @endforeach
        </select>
    @elseif($type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="0">
        <input id="{{ $inputId }}" name="{{ $name }}" type="checkbox" value="1" @checked((bool) old($name, $value ?? true))>
    @else
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) @if($type === 'number') step="any" @endif>
    @endif
</div>
