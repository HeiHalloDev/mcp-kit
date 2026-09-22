@props(['href' => null, 'external' => false])
<a href="{{ $href }}" data-flux="link" {{ $attributes }}>{{ $slot }}</a>
