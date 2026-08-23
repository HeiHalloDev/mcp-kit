@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="label" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
