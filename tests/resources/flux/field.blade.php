@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="field" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
