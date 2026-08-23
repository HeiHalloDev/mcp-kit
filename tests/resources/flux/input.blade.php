@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="input" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
