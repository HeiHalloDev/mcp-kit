<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What the assistant remembers about a person. Shape v1:
 * role, team, routines[], handoffs[], preferences{}, notes[{text, at, by}],
 * onboarding{offered_at, completed_at, declined_at}, updated_at,
 * updated_by{id, via, token}, version.
 *
 * @implements Arrayable<string, mixed>
 */
final class AssistantMemory implements Arrayable, JsonSerializable
{
    public const VERSION = 1;

    /**
     * @param  list<string>  $routines
     * @param  list<string>  $handoffs
     * @param  array<string, scalar>  $preferences
     * @param  list<array{text: string, at: string, by: ?string}>  $notes
     * @param  array{offered_at?: ?string, completed_at?: ?string, declined_at?: ?string}  $onboarding
     * @param  array{id?: mixed, via?: string, token?: ?string}|null  $updatedBy
     */
    public function __construct(
        public readonly ?string $role = null,
        public readonly ?string $team = null,
        public readonly array $routines = [],
        public readonly array $handoffs = [],
        public readonly array $preferences = [],
        public readonly array $notes = [],
        public readonly array $onboarding = [],
        public readonly ?string $updatedAt = null,
        public readonly ?array $updatedBy = null,
        public readonly int $version = self::VERSION,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            role: self::nullableString($data['role'] ?? null),
            team: self::nullableString($data['team'] ?? null),
            routines: self::stringList($data['routines'] ?? []),
            handoffs: self::stringList($data['handoffs'] ?? []),
            preferences: array_filter((array) ($data['preferences'] ?? []), 'is_scalar'),
            notes: self::notes($data['notes'] ?? []),
            onboarding: array_filter((array) ($data['onboarding'] ?? []), fn ($v, $k) => in_array($k, ['offered_at', 'completed_at', 'declined_at'], true), ARRAY_FILTER_USE_BOTH),
            updatedAt: self::nullableString($data['updated_at'] ?? null),
            updatedBy: isset($data['updated_by']) && is_array($data['updated_by']) ? $data['updated_by'] : null,
            version: (int) ($data['version'] ?? self::VERSION),
        );
    }

    public function isEmpty(): bool
    {
        return $this->role === null
            && $this->team === null
            && $this->routines === []
            && $this->handoffs === []
            && $this->preferences === []
            && $this->notes === [];
    }

    /**
     * Empty content and no onboarding marks either — never stored.
     */
    public function isBlank(): bool
    {
        return $this->isEmpty() && $this->onboarding === [];
    }

    public function onboardingOffered(): bool
    {
        return ($this->onboarding['offered_at'] ?? null) !== null;
    }

    public function onboardingCompleted(): bool
    {
        return ($this->onboarding['completed_at'] ?? null) !== null;
    }

    public function onboardingDeclined(): bool
    {
        return ($this->onboarding['declined_at'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    public function sizeBytes(): int
    {
        return strlen((string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'team' => $this->team,
            'routines' => $this->routines,
            'handoffs' => $this->handoffs,
            'preferences' => $this->preferences === [] ? (object) [] : $this->preferences,
            'notes' => $this->notes,
            'onboarding' => $this->onboarding === [] ? (object) [] : $this->onboarding,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'version' => $this->version,
        ];
    }

    /**
     * The fields a person cares about, without bookkeeping — for diffs.
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            'role' => $this->role,
            'team' => $this->team,
            'routines' => $this->routines,
            'handoffs' => $this->handoffs,
            'preferences' => $this->preferences,
            'notes' => array_map(static fn (array $note): string => $note['text'], $this->notes),
            'onboarding' => $this->onboarding,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($item): string => is_string($item) ? trim($item) : '',
            $value,
        ), static fn (string $item): bool => $item !== '')));
    }

    /**
     * @return list<array{text: string, at: string, by: ?string}>
     */
    private static function notes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $notes = [];

        foreach ($value as $note) {
            if (is_string($note) && trim($note) !== '') {
                $notes[] = ['text' => trim($note), 'at' => now()->toIso8601String(), 'by' => null];

                continue;
            }

            if (is_array($note) && is_string($note['text'] ?? null) && trim($note['text']) !== '') {
                $notes[] = [
                    'text' => trim($note['text']),
                    'at' => (string) ($note['at'] ?? now()->toIso8601String()),
                    'by' => isset($note['by']) ? (string) $note['by'] : null,
                ];
            }
        }

        return $notes;
    }
}
