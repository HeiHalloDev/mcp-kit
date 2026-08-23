@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="badge" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
