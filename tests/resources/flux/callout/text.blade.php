@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="callout.text" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
