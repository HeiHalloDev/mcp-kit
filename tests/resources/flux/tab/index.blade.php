@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="tab" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
