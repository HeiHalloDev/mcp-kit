@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="callout" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
