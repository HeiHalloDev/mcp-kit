# What this is used for
@if ($shortfalls === [] && $hard_won === [] && $done === [] && $unjudged === [] && $refused === [])

Nothing recorded in the last {{ $days }} days. Either nobody has worked through the tools yet, or the assistant is not opening task frames — check that the ground rules reach the servers people actually use.
@else
@if ($refused !== [])

## An assistant could not record its work ({{ count($refused) }})
Read this before anything else. `working_on` refused these, so the frame stayed blank — somebody tried to say what the work was and the tool would not take it. Everything below undercounts by this much.
@foreach ($refused as $task)
- **{{ $task->purpose ?: '(never named)' }}** — refused {{ $task->refusals }}× _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
@endforeach
@endif
@if ($shortfalls !== [])

## Fell short ({{ count($shortfalls) }})
Read these first. Each one is somebody who came for something and did not get it.
@foreach ($shortfalls as $task)
- **{{ $task->purpose ?: '(never named)' }}** — {{ $task->outcome === 'failed' ? 'failed' : 'partly' }}{{ $task->effort ? ', '.$task->effort : '' }}: {{ $task->result ?? 'no reason given' }} _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
@endforeach
@endif
@if ($hard_won !== [])

## Worked, but should not have been that hard ({{ count($hard_won) }})
The person got what they came for, so nothing else in this file flags these. Read them next.
@foreach ($hard_won as $task)
- **{{ $task->purpose ?: '(never named)' }}** — {{ $task->effort }}: {{ $task->result ?? 'no reason given' }} _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
@endforeach
@endif
@if ($done !== [])

## Worked ({{ count($done) }})
@foreach ($done as $task)
- {{ $task->purpose ?: '(never named)' }} _({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ $task->calls }} calls)_
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
- **A refused frame is not an uncooperative assistant.** It tried and the tool would not take what it sent. Fix the tool, not the prompt.
- **`done` with `fought_it` is the row to act on.** It succeeded, so no outcome flags it and no count catches it — reading before writing and previewing before confirming make a correct write three calls by design. Only the assistant that did the work can say it was harder than it should have been.
- A frame with no purpose was opened by the middleware and never named. The calls are real; nobody said what for.
- The purposes are the assistant's words for what a person wanted. Take them as evidence of intent, not as a transcript.
- A shortfall that keeps coming back and is not on the gap list is the strongest thing here: people hit it often enough to try, and often enough to give up without saying so.
- Many calls against one purpose usually means the work needed stitching together by hand. That is a candidate for one tool doing the whole job.
- A pile of never-closed frames means the ground rules are not landing, not that the work failed.
