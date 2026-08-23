@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="radio.group" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
