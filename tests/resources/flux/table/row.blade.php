@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="table.row" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
