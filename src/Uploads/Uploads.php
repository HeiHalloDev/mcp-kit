<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Uploads;

use HeiHallo\McpKit\Models\Upload;
use HeiHallo\McpKit\Principal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The staging area: a loading dock, not a warehouse.
 *
 * Bytes arrive over HTTP with the same token that talks MCP and wait,
 * expiring, until a tool consumes the handle and copies the file into the
 * app's real home. Ownership is the person, not the token — tokens rotate,
 * and a cut-off session must be able to pick its files back up.
 */
class Uploads
{
    public function stage(Principal $principal, UploadedFile $file): Upload
    {
        $disk = (string) config('mcp-kit.uploads.disk', 'local');
        $handle = 'up_'.Str::random(32);

        $path = $file->storeAs(
            (string) config('mcp-kit.uploads.path', 'mcp-uploads'),
            $handle,
            $disk,
        );

        return Upload::query()->create([
            'handle' => $handle,
            'owner_type' => $principal->tokenable::class,
            'owner_id' => (string) $principal->id(),
            'name' => $file->getClientOriginalName() ?: $handle,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'disk' => $disk,
            'path' => $path,
            'expires_at' => now()->addDays($this->ttlDays()),
        ]);
    }

    /**
     * The staged file behind a handle — or a sentence saying why not.
     * Ownership is checked here, always: what one person staged is not
     * reachable from anybody else's token.
     */
    public function resolve(string $handle, Principal $principal): Upload|string
    {
        $upload = Upload::query()->where('handle', trim($handle))->first();

        if ($upload === null) {
            return "No staged file matches `{$handle}`. Upload it first, or call list_uploads to see what is there.";
        }

        if ($upload->owner_type !== $principal->tokenable::class || $upload->owner_id !== (string) $principal->id()) {
            // The same sentence as not-found, on purpose: a handle is not
            // a probe for whether somebody else uploaded something.
            return "No staged file matches `{$handle}`. Upload it first, or call list_uploads to see what is there.";
        }

        if ($upload->isExpired()) {
            return "`{$handle}` ({$upload->name}) expired ".$upload->expires_at->diffForHumans().' — staged files live '.$this->ttlDays().' days. Upload it again.';
        }

        if (! Storage::disk($upload->disk)->exists($upload->path)) {
            return "`{$handle}` ({$upload->name}) is gone from the staging disk. Upload it again.";
        }

        return $upload;
    }

    /**
     * Record that a tool took a copy. Copy, not move: one upload can feed
     * two tools, and the row keeps saying where the file ended up.
     */
    public function consumed(Upload $upload, string $tool, ?string $target = null): void
    {
        $upload->update(['consumed' => [
            ...($upload->consumed ?? []),
            array_filter(['tool' => $tool, 'at' => now()->toIso8601String(), 'target' => $target]),
        ]]);
    }

    /**
     * @return list<Upload>
     */
    public function for(Principal $principal): array
    {
        return Upload::query()
            ->where('owner_type', $principal->tokenable::class)
            ->where('owner_id', (string) $principal->id())
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->all();
    }

    /**
     * Expired rows go with their files.
     */
    public function prune(): int
    {
        $pruned = 0;

        Upload::query()->where('expires_at', '<', now())->orderBy('id')->chunkById(100, function ($expired) use (&$pruned): void {
            foreach ($expired as $upload) {
                Storage::disk($upload->disk)->delete($upload->path);
                $upload->delete();
                $pruned++;
            }
        });

        return $pruned;
    }

    public function ttlDays(): int
    {
        return max(1, (int) config('mcp-kit.uploads.ttl_days', 3));
    }
}
