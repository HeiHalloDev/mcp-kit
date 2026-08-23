@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="table.column" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
