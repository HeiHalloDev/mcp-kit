@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="callout.heading" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
