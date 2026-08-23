@props(['value' => null, 'label' => null, 'description' => null, 'name' => null])
<div data-flux="tab.group" {{ $attributes }}>{{ $label }}{{ $description }}{{ $value }}{{ $slot }}</div>
