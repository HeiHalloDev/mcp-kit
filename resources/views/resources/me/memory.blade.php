
## Memory
@if ($memory->isEmpty())
Nothing remembered yet.
@if ($invite)
Offer the `getting_started` prompt once: a short conversation about how {{ $principal->firstName() }} works, saved only if they say so. If they decline, do not bring it up again.
@elseif (! $memory->onboardingDeclined())
(getting_started prompt available)
@endif
@else
Remembered {{ $memory->updatedAt ? 'on '.\Illuminate\Support\Str::of($memory->updatedAt)->substr(0, 10) : '' }}{{ $memory->updatedBy ? ' via '.$memory->updatedBy['via'] : '' }}. Update with `remember_about_me`; forget with its `forget` argument.
@endif
@if ($memoryUrl)
The person can review or clear this at {{ $memoryUrl }}.
@endif
