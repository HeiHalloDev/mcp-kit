@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="checkbox.group" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
