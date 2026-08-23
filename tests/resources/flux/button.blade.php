@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="button" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
