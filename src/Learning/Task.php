<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Learning;

use DateTimeInterface;

/**
 * One piece of work, as the assistant described it before starting and
 * judged it afterwards. The call log knows which tools ran; this is the
 * only place that knows what for, and whether the person got what they
 * came for.
 */
final class Task
{
    public const OPEN = 'open';

    public const DONE = 'done';

    public const PARTLY = 'partly';

    public const FAILED = 'failed';

    /** Nobody closed it and it aged out. */
    public const UNKNOWN = 'unknown';

    public const OUTCOMES = [self::DONE, self::PARTLY, self::FAILED];

    public function __construct(
        public readonly string $purpose,
        public readonly string $tokenId,
        public readonly int|string $userId,
        public readonly string $name,
        public readonly ?string $server = null,
        public readonly string $outcome = self::OPEN,
        public readonly ?string $result = null,
        public readonly int $calls = 0,
        public readonly ?DateTimeInterface $startedAt = null,
        public readonly ?DateTimeInterface $closedAt = null,
        public readonly int|string|null $id = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->outcome === self::OPEN;
    }

    /**
     * Did the person walk away without what they came for? These are the
     * rows worth reading first — and the ones that most often turn out to
     * be a gap nobody filed.
     */
    public function fellShort(): bool
    {
        return in_array($this->outcome, [self::FAILED, self::PARTLY], true);
    }

    public function closedAs(string $outcome, string $result, int $calls): self
    {
        return new self(
            purpose: $this->purpose,
            tokenId: $this->tokenId,
            userId: $this->userId,
            name: $this->name,
            server: $this->server,
            outcome: $outcome,
            result: $result === '' ? $this->result : $result,
            calls: $calls,
            startedAt: $this->startedAt,
            closedAt: now(),
            id: $this->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'purpose' => $this->purpose,
            'outcome' => $this->outcome,
            'result' => $this->result,
            'server' => $this->server,
            'calls' => $this->calls,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
