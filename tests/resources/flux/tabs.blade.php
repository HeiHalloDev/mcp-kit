@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="tabs" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
