<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Livewire;

use Flux\Flux;
use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the tools were actually used for, and what people needed and could
 * not get. The same two records the assistant reads over MCP, for a person
 * with a browser.
 *
 * Privileged only, for the same reason the resources are: it is a record of
 * colleagues' work, not a leaderboard.
 */
class UsagePage extends Component
{
    /** How far back to read. */
    #[Url(as: 'days')]
    public int $days = 30;

    /** Empty means every server in this app. */
    #[Url(as: 'server')]
    public string $server = '';

    /** live = still open or planned; closed = decided; all = both. */
    #[Url(as: 'gaps')]
    public string $gapScope = 'live';

    /** The gap being settled, if the form is open on one. */
    public ?string $settling = null;

    public string $resolution = '';

    /**
     * How many frames the page will read. Well above what these apps
     * produce in a month; the tally says so when it is not.
     */
    protected const WINDOW = 1000;

    public function mount(): void
    {
        abort_unless((bool) $this->principal()?->privileged, 403);
    }

    protected function principal(): ?Principal
    {
        $user = auth()->user();

        return $user ? app(PrincipalResolver::class)->resolve($user) : null;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function dayOptions(): array
    {
        return [
            7 => __('Last 7 days'),
            30 => __('Last 30 days'),
            90 => __('Last 90 days'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function serverOptions(): array
    {
        $options = ['' => __('All servers')];

        foreach (app(ServerRegistry::class)->keys() as $key) {
            $options[$key] = $key;
        }

        return $options;
    }

    /**
     * @return list<Task>
     */
    #[Computed]
    public function tasks(): array
    {
        $tasks = app(TaskStore::class)->recent(limit: self::WINDOW, days: $this->days);

        return $this->server === ''
            ? $tasks
            : array_values(array_filter($tasks, fn (Task $t): bool => $t->server === $this->server));
    }

    /**
     * The frames in reading order: what fell short, then what worked but
     * fought back, then what worked, then what nobody judged.
     *
     * @return array<string, list<Task>>
     */
    #[Computed]
    public function frames(): array
    {
        $tasks = $this->tasks;

        $is = fn (callable $test): array => array_values(array_filter($tasks, $test));

        return [
            'short' => $is(fn (Task $t): bool => $t->fellShort()),
            'hard' => $is(fn (Task $t): bool => $t->outcome === Task::DONE && $t->wasHarderThanItShouldBe()),
            'worked' => $is(fn (Task $t): bool => $t->outcome === Task::DONE && ! $t->wasHarderThanItShouldBe()),
            'unjudged' => $is(fn (Task $t): bool => in_array($t->outcome, [Task::OPEN, Task::UNKNOWN], true)),
        ];
    }

    /**
     * The numbers worth watching: how much work came through, and how much
     * of it anybody said anything about. A frame opens by itself, so the
     * share that gets named is the only figure that measures cooperation.
     *
     * @return array{frames: int, named: int, share: ?int, calls: int, capped: bool}
     */
    #[Computed]
    public function tally(): array
    {
        $tasks = $this->tasks;
        $frames = count($tasks);
        $named = count(array_filter($tasks, fn (Task $t): bool => ! $t->isUnnamed()));

        return [
            'frames' => $frames,
            'named' => $named,
            'share' => $frames > 0 ? (int) round($named / $frames * 100) : null,
            'calls' => array_sum(array_map(fn (Task $t): int => $t->calls, $tasks)),
            'capped' => $frames >= self::WINDOW,
        ];
    }

    /**
     * @return list<Gap>
     */
    #[Computed]
    public function gaps(): array
    {
        $statuses = match ($this->gapScope) {
            'closed' => [Gap::DONE, Gap::DECLINED],
            'all' => Gap::STATUSES,
            default => [Gap::OPEN, Gap::PLANNED],
        };

        return app(GapStore::class)->list($statuses, $this->server === '' ? null : $this->server, limit: 100);
    }

    public function startSettling(string $id): void
    {
        $this->settling = $id;
        $this->resolution = '';
        $this->resetErrorBag();
    }

    public function cancelSettling(): void
    {
        $this->reset('settling', 'resolution');
        $this->resetErrorBag();
    }

    /**
     * Decide a gap. The resolution goes back to everybody who reported it,
     * the next time they read {scheme}://me.
     */
    public function settle(string $status): void
    {
        $principal = $this->principal();

        abort_unless((bool) $principal?->privileged, 403);

        $gap = $this->settling === null ? null : app(GapStore::class)->find($this->settling);

        if ($gap === null) {
            $this->cancelSettling();

            return;
        }

        if (trim($this->resolution) === '') {
            $this->addError('resolution', __('Say what was decided — the people who reported this will read it.'));

            return;
        }

        $settled = app(GapStore::class)->put(
            $gap->settled($status, $this->resolution, $principal->name)
        );

        event(new GapStatusChanged($settled, $gap->status, $principal));

        $this->cancelSettling();

        unset($this->gaps);

        $this->toast(__('Noted. Everybody who reported it will hear the next time they ask.'));
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            Gap::OPEN => 'amber',
            Gap::PLANNED => 'blue',
            Gap::DONE => 'green',
            default => 'zinc',
        };
    }

    public function effortColor(?string $effort): string
    {
        return match ($effort) {
            Task::FOUGHT_IT => 'red',
            Task::FIDDLY => 'amber',
            Task::SMOOTH => 'green',
            default => 'zinc',
        };
    }

    protected function toast(string $text): void
    {
        if (class_exists(Flux::class)) {
            Flux::toast($text);
        }
    }

    public function render(): View
    {
        $view = view('mcp-kit::livewire.usage-page');

        $layout = config('mcp-kit.ui.layout');

        return is_string($layout) && $layout !== '' ? $view->layout($layout) : $view;
    }
}
