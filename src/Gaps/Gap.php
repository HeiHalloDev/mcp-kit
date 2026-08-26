<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Gaps;

use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Something a person needed and the app could not do. Not a refusal — a
 * token that lacks an ability is a permissions problem with a named fix.
 * This is the other kind: the tool does not exist.
 */
final class Gap
{
    public const OPEN = 'open';

    public const PLANNED = 'planned';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    public const STATUSES = [self::OPEN, self::PLANNED, self::DONE, self::DECLINED];

    /**
     * @param  list<array{name: string, at: string, note: string}>  $reporters
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $need,
        public readonly string $missing,
        public readonly ?string $server = null,
        public readonly ?string $tool = null,
        public readonly bool $blocking = false,
        public readonly string $status = self::OPEN,
        public readonly array $reporters = [],
        public readonly int $reports = 1,
        public readonly ?string $resolution = null,
        public readonly ?string $resolvedBy = null,
        public readonly ?DateTimeInterface $resolvedAt = null,
        public readonly ?DateTimeInterface $reportedAt = null,
        public readonly int|string|null $id = null,
    ) {}

    /**
     * What a repeat report is matched on: the title, reduced to its words.
     */
    public static function key(string $title): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Str::ascii($title))), '-');

        if ($key === '') {
            throw new InvalidArgumentException('A gap needs a title made of letters or numbers.');
        }

        return Str::limit($key, 80, '');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /**
     * Has this person already said they hit it?
     */
    public function reportedBy(string $name): bool
    {
        foreach ($this->reporters as $reporter) {
            if ($reporter['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decided: it is planned, built, or turned down. The resolution is what
     * the people who reported it will read, so it is never optional — a gap
     * that changes status without a reason tells them less than silence did.
     *
     * Everybody who reported it is marked unheard again: planned and done
     * are two separate pieces of news, and each is owed once.
     */
    public function settled(string $status, string $resolution, string $by): self
    {
        if (! in_array($status, [self::PLANNED, self::DONE, self::DECLINED], true)) {
            throw new InvalidArgumentException('A gap is settled as planned, done or declined.');
        }

        if (trim($resolution) === '') {
            throw new InvalidArgumentException('Say what was decided. It is what the people who reported this will read.');
        }

        return new self(
            key: $this->key,
            title: $this->title,
            need: $this->need,
            missing: $this->missing,
            server: $this->server,
            tool: $this->tool,
            blocking: $this->blocking,
            status: $status,
            reporters: array_map(static function (array $reporter): array {
                unset($reporter['heard']);

                return $reporter;
            }, $this->reporters),
            reports: $this->reports,
            resolution: trim($resolution),
            resolvedBy: $by,
            resolvedAt: now(),
            reportedAt: $this->reportedAt,
            id: $this->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'need' => $this->need,
            'missing' => $this->missing,
            'server' => $this->server,
            'tool' => $this->tool,
            'blocking' => $this->blocking,
            'status' => $this->status,
            'reports' => $this->reports,
            'resolution' => $this->resolution,
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }
}
