@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="text" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
