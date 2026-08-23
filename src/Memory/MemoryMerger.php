<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

use HeiHallo\McpKit\Principal;
use InvalidArgumentException;

/**
 * Applies a remember_about_me request to a memory. Forget first; scalars
 * replace; lists merge and dedupe unless replace=true; preferences merge
 * by key with null removing; a note appends. Over a limit the merger
 * refuses — it never truncates silently.
 */
final class MemoryMerger
{
    public function __construct(private MemoryLimits $limits) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function merge(AssistantMemory $current, array $changes, bool $replace, ?Principal $by, string $via = 'mcp'): AssistantMemory
    {
        $data = $current->toArray();
        $data['preferences'] = (array) $data['preferences'];
        $data['onboarding'] = (array) $data['onboarding'];

        foreach ((array) ($changes['forget'] ?? []) as $item) {
            $data = $this->forget($data, (string) $item);
        }

        foreach (['role', 'team'] as $scalar) {
            if (array_key_exists($scalar, $changes)) {
                $value = $changes[$scalar];

                if ($value !== null && ! is_string($value)) {
                    throw new InvalidArgumentException("{$scalar} must be a string.");
                }

                $this->assertLength($scalar, $value, $scalar === 'role' ? $this->limits->roleChars : $this->limits->itemChars);
                $data[$scalar] = $value === null || trim($value) === '' ? null : trim($value);
            }
        }

        foreach (['routines' => $this->limits->routines, 'handoffs' => $this->limits->handoffs] as $list => $max) {
            if (! array_key_exists($list, $changes)) {
                continue;
            }

            $incoming = array_values(array_filter(array_map(
                static fn ($item): string => is_string($item) ? trim($item) : '',
                (array) $changes[$list],
            ), static fn (string $item): bool => $item !== ''));

            foreach ($incoming as $item) {
                $this->assertLength($list, $item, $this->limits->itemChars);
            }

            $merged = $replace ? $incoming : array_values(array_unique([...$data[$list], ...$incoming]));

            if (count($merged) > $max) {
                throw new InvalidArgumentException("Too many {$list}: the limit is {$max}. Forget one first.");
            }

            $data[$list] = $merged;
        }

        if (array_key_exists('preferences', $changes)) {
            $incoming = (array) $changes['preferences'];
            $preferences = $replace ? [] : $data['preferences'];

            foreach ($incoming as $key => $value) {
                if ($value === null) {
                    unset($preferences[$key]);

                    continue;
                }

                if (! is_scalar($value)) {
                    throw new InvalidArgumentException("Preference {$key} must be a plain value.");
                }

                $this->assertLength("preferences.{$key}", (string) $value, $this->limits->itemChars);
                $preferences[(string) $key] = $value;
            }

            $data['preferences'] = $preferences;
        }

        if (isset($changes['note']) && is_string($changes['note']) && trim($changes['note']) !== '') {
            $this->assertLength('note', $changes['note'], $this->limits->itemChars);

            $data['notes'][] = [
                'text' => trim($changes['note']),
                'at' => now()->toIso8601String(),
                'by' => $by?->signature(),
            ];

            if (count($data['notes']) > $this->limits->notes) {
                throw new InvalidArgumentException("Too many notes: the limit is {$this->limits->notes}. Forget one first.");
            }
        }

        if (isset($changes['onboarding'])) {
            $data['onboarding'] = match ((string) $changes['onboarding']) {
                'completed' => [...$data['onboarding'], 'completed_at' => now()->toIso8601String()],
                'declined' => [...$data['onboarding'], 'declined_at' => now()->toIso8601String()],
                'reset' => [],
                default => throw new InvalidArgumentException('onboarding must be completed, declined or reset.'),
            };
        }

        $data['updated_at'] = now()->toIso8601String();
        $data['updated_by'] = $by === null ? null : ['id' => $by->id(), 'via' => $via, 'token' => $by->tokenName()];
        $data['version'] = AssistantMemory::VERSION;

        $memory = AssistantMemory::fromArray($data);

        if ($memory->sizeBytes() > $this->limits->maxBytes) {
            throw new InvalidArgumentException('Memory is full. Forget something first.');
        }

        return $memory;
    }

    /**
     * "routines" clears a list; "role" clears a scalar; "preferences.language"
     * drops one preference; "note:2" or any exact text drops one note or item.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function forget(array $data, string $item): array
    {
        $item = trim($item);

        if ($item === 'all' || $item === 'everything') {
            return [...AssistantMemory::empty()->toArray(), 'preferences' => [], 'onboarding' => $data['onboarding']];
        }

        if (in_array($item, ['role', 'team'], true)) {
            $data[$item] = null;

            return $data;
        }

        if (in_array($item, ['routines', 'handoffs', 'notes'], true)) {
            $data[$item] = [];

            return $data;
        }

        if ($item === 'preferences') {
            $data['preferences'] = [];

            return $data;
        }

        if (str_starts_with($item, 'preferences.')) {
            unset($data['preferences'][substr($item, strlen('preferences.'))]);

            return $data;
        }

        if (preg_match('/^note:(\d+)$/', $item, $m)) {
            $index = (int) $m[1] - 1;
            unset($data['notes'][$index]);
            $data['notes'] = array_values($data['notes']);

            return $data;
        }

        foreach (['routines', 'handoffs'] as $list) {
            $data[$list] = array_values(array_filter($data[$list], static fn (string $existing): bool => strcasecmp($existing, $item) !== 0));
        }

        $data['notes'] = array_values(array_filter($data['notes'], static fn (array $note): bool => strcasecmp($note['text'], $item) !== 0));

        return $data;
    }

    private function assertLength(string $field, ?string $value, int $max): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$field} is too long: the limit is {$max} characters.");
        }
    }
}
