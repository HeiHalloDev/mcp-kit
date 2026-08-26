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

    /** It went the way the tools expect. */
    public const SMOOTH = 'smooth';

    /** It worked, but it took stitching together. */
    public const FIDDLY = 'fiddly';

    /** It worked in the end and should not have been that hard. */
    public const FOUGHT_IT = 'fought_it';

    public const EFFORTS = [self::SMOOTH, self::FIDDLY, self::FOUGHT_IT];

    public function __construct(
        public readonly string $purpose,
        public readonly string $tokenId,
        public readonly int|string $userId,
        public readonly string $name,
        public readonly ?string $server = null,
        public readonly string $outcome = self::OPEN,
        public readonly ?string $result = null,
        /** Null on a frame nobody has judged yet. */
        public readonly ?string $effort = null,
        public readonly int $calls = 0,
        /** Times an assistant tried to name or close this and was refused. */
        public readonly int $refusals = 0,
        public readonly ?DateTimeInterface $startedAt = null,
        public readonly ?DateTimeInterface $closedAt = null,
        public readonly int|string|null $id = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->outcome === self::OPEN;
    }

    /**
     * Opened by the middleware and never named. The calls are grouped, but
     * nobody has said what for — the row counts, the purpose does not.
     */
    public function isUnnamed(): bool
    {
        return trim($this->purpose) === '';
    }

    /**
     * Succeeded, but should have been easier. The case call counts cannot
     * see and outcomes alone cannot express, and the reason effort exists.
     */
    public function wasHarderThanItShouldBe(): bool
    {
        return in_array($this->effort, [self::FIDDLY, self::FOUGHT_IT], true);
    }

    public function named(string $purpose): self
    {
        return new self(
            purpose: $purpose,
            tokenId: $this->tokenId,
            userId: $this->userId,
            name: $this->name,
            server: $this->server,
            outcome: $this->outcome,
            result: $this->result,
            effort: $this->effort,
            calls: $this->calls,
            refusals: $this->refusals,
            startedAt: $this->startedAt,
            closedAt: $this->closedAt,
            id: $this->id,
        );
    }

    /**
     * Did the person walk away without what they came for? These are the
     * rows worth reading first — and the ones that most often turn out to
     * be a gap nobody filed.
     */
    /**
     * An assistant tried to say what this was and the tool would not take
     * it. The frame looks abandoned and is not — that difference is
     * invisible everywhere else, so it is worth its own question.
     */
    public function namingWasRefused(): bool
    {
        return $this->refusals > 0;
    }

    public function fellShort(): bool
    {
        return in_array($this->outcome, [self::FAILED, self::PARTLY], true);
    }

    public function closedAs(string $outcome, string $result, int $calls, ?string $effort = null, ?string $purpose = null): self
    {
        return new self(
            purpose: $purpose !== null && trim($purpose) !== '' ? $purpose : $this->purpose,
            tokenId: $this->tokenId,
            userId: $this->userId,
            name: $this->name,
            server: $this->server,
            outcome: $outcome,
            result: $result === '' ? $this->result : $result,
            effort: $effort ?? $this->effort,
            calls: $calls,
            refusals: $this->refusals,
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
            'refusals' => $this->refusals ?: null,
            'outcome' => $this->outcome,
            'effort' => $this->effort,
            'result' => $this->result,
            'server' => $this->server,
            'calls' => $this->calls,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
