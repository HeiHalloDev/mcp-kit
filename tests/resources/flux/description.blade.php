@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="description" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
