@props(['title' => null])
@php($layout = config('mcp-kit.oauth.layout'))
@if (is_string($layout) && $layout !== '')
    <x-dynamic-component :component="$layout" :title="$title">{{ $slot }}</x-dynamic-component>
@else
    @include('mcp-kit::oauth.layout', ['title' => $title, 'slot' => $slot])
@endif
