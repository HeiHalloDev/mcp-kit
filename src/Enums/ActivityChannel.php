<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Enums;

/**
 * The surface an activity row was written from. `source` is the product;
 * `channel` is how the person reached it.
 */
enum ActivityChannel: string
{
    case Web = 'web';
    case Mcp = 'mcp';
    case Api = 'api';
    case Cli = 'cli';
    case Chat = 'chat';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Mcp => 'Assistant (MCP)',
            self::Api => 'API',
            self::Cli => 'Console',
            self::Chat => 'Chat agent',
            self::System => 'System',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function color(): string
    {
        return match ($this) {
            self::Web => 'zinc',
            self::Mcp => 'amber',
            self::Api => 'violet',
            self::Cli => 'sky',
            self::Chat => 'emerald',
            self::System => 'lime',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Web => 'computer-desktop',
            self::Mcp => 'sparkles',
            self::Api => 'code-bracket',
            self::Cli => 'command-line',
            self::Chat => 'chat-bubble-left-right',
            self::System => 'cog-6-tooth',
        };
    }
}
