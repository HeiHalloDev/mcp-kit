@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="table" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
