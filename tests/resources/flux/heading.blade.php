@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="heading" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
