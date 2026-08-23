@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="radio" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
