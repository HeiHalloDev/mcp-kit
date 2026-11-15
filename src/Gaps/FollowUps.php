<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Gaps;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Events\GapFollowedUp;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Messages\Recipients;
use HeiHallo\McpKit\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * What is said about a gap after it was filed, and who hears it.
 *
 * A gap is a conversation between the people who reported it and the people
 * who build the app: a retest, a new example, a question back, the answer.
 * Before this there was nowhere to put any of it. A reporter was told the
 * gap was already theirs, and a privileged one wrote into the resolution of
 * the open gap, where each note replaced the last and the list never showed
 * it. Three follow-ups sat unread that way for two days.
 *
 * Who hears: the builders hear everything they did not write themselves.
 * The reporters hear what a builder wrote, and what somebody who did not
 * report the gap wrote. Colleagues who reported the same gap do not read
 * each other's notes.
 */
class FollowUps
{
    /**
     * Reporters and privileged staff write on a gap and read it in full.
     */
    public function mayFollowUp(Gap $gap, Principal $principal): bool
    {
        return $principal->privileged || $gap->reportedBy($principal->name);
    }

    /**
     * Why this cannot be added, in words for the assistant. Null when it can.
     */
    public function problem(Gap $gap, Principal $principal, string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return 'A follow-up needs something in it: say what changed, what you found or what you are asking.';
        }

        if (! $this->mayFollowUp($gap, $principal)) {
            return sprintf(
                "Follow-ups on '%s' are for the people who reported it and the people who build this app. If you hit it too, report it with the same title and you join it.",
                $gap->title,
            );
        }

        if (mb_strlen($text) > Gap::FOLLOW_UP_MAX) {
            return sprintf(
                'That follow-up is %d characters, and the limit is %d. Say what changed and what you expect; the gap list is read whole, and a long one buries the rest.',
                mb_strlen($text),
                Gap::FOLLOW_UP_MAX,
            );
        }

        if (SecretDetector::looksSecret(['note' => $text])) {
            return 'That looks like a password or token. A follow-up says what happened, never a credential.';
        }

        $last = $gap->followUps === [] ? null : $gap->followUps[array_key_last($gap->followUps)];

        // A call that timed out is sent again; the note is already there.
        if ($last !== null && $last['name'] === $principal->name && $last['text'] === $text) {
            return sprintf("That follow-up is already on '%s', written %s. Nothing was added.", $gap->title, Gap::stamp($last['at']));
        }

        return null;
    }

    /**
     * Who a follow-up from this person reaches, in words for the preview.
     */
    public function reaches(Gap $gap, Principal $author): string
    {
        $reporters = implode(', ', array_diff(array_column($gap->reporters, 'name'), [$author->name]));

        return match (true) {
            $this->isBuilder($author) => 'the people who reported it, on their next tool call'.($reporters === '' ? '' : ': '.$reporters),
            $gap->reportedBy($author->name) => 'the people who build this app, on their next tool call',
            default => 'the people who build this app and the people who reported it, on their next tool call',
        };
    }

    public function add(Gap $gap, Principal $principal, string $text): Gap
    {
        $saved = app(GapStore::class)->put($gap->followedUp($principal->name, $text, $this->isBuilder($principal)));

        event(new GapFollowedUp($saved, $principal, trim($text)));

        return $saved;
    }

    /**
     * The follow-ups this person has not been told about: for a builder,
     * everything somebody else wrote; on gaps they reported, what a builder
     * or an outsider to the gap wrote back. Never their own.
     *
     * @return list<array{key: string, gap: Gap, from: string, body: string, sent_at: string}>
     */
    public function waitingFor(Principal $principal): array
    {
        if (! config('mcp-kit.gaps.enabled', true) || ! config('mcp-kit.messages.gap_follow_ups', true)) {
            return [];
        }

        // Older ones are history, not news, the same as gap answers.
        $since = now()->subDays((int) config('mcp-kit.messages.gap_answers_days', 30));
        $builder = null;
        $waiting = [];

        foreach (app(GapStore::class)->list(Gap::STATUSES, limit: 200) as $gap) {
            foreach ($gap->followUps as $index => $followUp) {
                if ($followUp['name'] === $principal->name || ! $this->recent($followUp['at'], $since)) {
                    continue;
                }

                $forReporters = ($followUp['builder'] ?? false) || ! $gap->reportedBy($followUp['name']);

                $hears = ($forReporters && $gap->reportedBy($principal->name))
                    || ($builder ??= $this->isBuilder($principal));

                if (! $hears) {
                    continue;
                }

                $waiting[] = [
                    'key' => 'followup:'.$gap->id.':'.$index,
                    'gap' => $gap,
                    'from' => $followUp['name'],
                    'body' => $followUp['text'],
                    'sent_at' => $followUp['at'],
                ];
            }
        }

        return $waiting;
    }

    protected function recent(string $at, Carbon $since): bool
    {
        try {
            return $at === '' || Carbon::parse($at) >= $since;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Whether this person builds the app: the ones "dev" reaches.
     */
    protected function isBuilder(Principal $principal): bool
    {
        $model = $principal->tokenable;

        if (! $model instanceof Model) {
            return false;
        }

        return app(Recipients::class)->builders($model)->contains(fn (Model $user): bool => (string) $user->getKey() === (string) $model->getKey());
    }
}
