@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="table.rows" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
