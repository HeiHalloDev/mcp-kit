# What this is used for
@if ($shortfalls === [] && $done === [] && $unjudged === [])

Nothing recorded in the last {{ $days }} days. Either nobody has worked through the tools yet, or the assistant is not opening task frames — check that the ground rules reach the servers people actually use.
@else
@if ($shortfalls !== [])

## Fell short ({{ count($shortfalls) }})
Read these first. Each one is somebody who came for something and did not get it.
@foreach ($shortfalls as $task)
- **{{ $task->purpose }}** — {{ $task->outcome === 'failed' ? 'failed' : 'partly' }}: {{ $task->result ?? 'no reason given' }} _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
@endforeach
@endif
@if ($done !== [])

## Worked ({{ count($done) }})
@foreach ($done as $task)
- {{ $task->purpose }} _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
@endforeach
@endif
@if ($unjudged !== [])

## Never closed ({{ count($unjudged) }})
Opened and left. Nothing is claimed about how these went.
@foreach ($unjudged as $task)
- {{ $task->purpose }} _({{ $task->name }}, {{ $task->calls }} calls)_
@endforeach
@endif
@endif

## Reading this
- The purposes are the assistant's words for what a person wanted. Take them as evidence of intent, not as a transcript.
- A shortfall that keeps coming back and is not on the gap list is the strongest thing here: people hit it often enough to try, and often enough to give up without saying so.
- Many calls against one purpose usually means the work needed stitching together by hand. That is a candidate for one tool doing the whole job.
- A pile of never-closed frames means the ground rules are not landing, not that the work failed.
