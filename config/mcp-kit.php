<?php

declare(strict_types=1);

use HeiHallo\McpKit\Abilities\ConfigAbilityCatalogue;
use HeiHallo\McpKit\Activity\DefaultChannelResolver;
use HeiHallo\McpKit\Activity\NullSourceResolver;
use HeiHallo\McpKit\Audit\ActivityLogAuditWriter;
use HeiHallo\McpKit\Describe\AutoDescriber;
use HeiHallo\McpKit\Docs\DefaultDocsRenderer;
use HeiHallo\McpKit\Gaps\DatabaseGapStore;
use HeiHallo\McpKit\GroundRules\SectionedGroundRules;
use HeiHallo\McpKit\Learning\DatabaseTaskStore;
use HeiHallo\McpKit\Links\NullLinks;
use HeiHallo\McpKit\Mcp\Prompts\GettingStartedPrompt;
use HeiHallo\McpKit\Mcp\Resources\GapsResource;
use HeiHallo\McpKit\Mcp\Resources\GroundRulesResource;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Mcp\Resources\PlaybooksResource;
use HeiHallo\McpKit\Mcp\Resources\UsageResource;
use HeiHallo\McpKit\Mcp\Tools\ListUploadsTool;
use HeiHallo\McpKit\Mcp\Tools\RememberAboutMeTool;
use HeiHallo\McpKit\Mcp\Tools\ReportGapTool;
use HeiHallo\McpKit\Mcp\Tools\RequestUploadTool;
use HeiHallo\McpKit\Mcp\Tools\SavePlaybookTool;
use HeiHallo\McpKit\Mcp\Tools\WorkingOnTool;
use HeiHallo\McpKit\Memory\ColumnMemoryStore;
use HeiHallo\McpKit\Memory\DefaultMemoryPolicy;
use HeiHallo\McpKit\Models\GapReport;
use HeiHallo\McpKit\Models\Playbook as PlaybookModel;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Models\Task as TaskModel;
use HeiHallo\McpKit\Onboarding\ConfigSuggestions;
use HeiHallo\McpKit\Onboarding\DefaultQuestions;
use HeiHallo\McpKit\Permissions\GatePermissionChecker;
use HeiHallo\McpKit\Playbooks\DatabasePlaybookStore;
use HeiHallo\McpKit\Playbooks\DefaultPlaybookPolicy;
use HeiHallo\McpKit\Presets\ConfigPresetResolver;
use HeiHallo\McpKit\Principals\DefaultPrincipalResolver;
use HeiHallo\McpKit\Tokens\DefaultTokenPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Read-only mode
    |--------------------------------------------------------------------------
    |
    | When on, every server hides the tools that are not annotated
    | #[IsReadOnly] and previewOrExecute() refuses confirm=true. Useful on
    | a staging copy, or while a migration is in flight.
    |
    */

    'read_only' => (bool) env('MCP_READ_ONLY', false),

    /*
    | Refuse a call carrying an argument the tool does not declare, naming
    | the closest real parameter. Without it the argument is dropped and
    | the tool answers confidently about something else. People only —
    | service clients send code we change deliberately.
    */

    'strict_parameters' => (bool) env('MCP_STRICT_PARAMETERS', true),

    // Never counted as unknown, whether a tool declares them or not.
    'always_allowed_parameters' => ['confirm'],

    /*
    |--------------------------------------------------------------------------
    | Resource URI scheme
    |--------------------------------------------------------------------------
    |
    | The shared resources live at {scheme}://ground-rules and {scheme}://me.
    | Pick something short that names the app (crm, flex, shop).
    |
    */

    'scheme' => env('MCP_KIT_SCHEME', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The service-client model: a tokenable with no person behind it (another
    | system calling in). null disables service clients entirely. The class
    | must implement HeiHallo\McpKit\Contracts\ServiceClient and use Sanctum's
    | HasApiTokens. The users model comes from auth.providers.users.model.
    |
    */

    'models' => [
        'service_client' => ServiceClient::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension points
    |--------------------------------------------------------------------------
    |
    | Every replaceable behaviour is a contract bound from one of these keys.
    | Point a key at your own class (or re-bind the contract in your provider)
    | to swap the behaviour; never edit a package file.
    |
    */

    'principal' => DefaultPrincipalResolver::class,
    'permissions' => GatePermissionChecker::class,
    'abilities' => ConfigAbilityCatalogue::class,
    'presets' => ConfigPresetResolver::class,
    'token_policy' => DefaultTokenPolicy::class,
    'links' => NullLinks::class,
    'describer' => AutoDescriber::class,
    'audit' => ActivityLogAuditWriter::class,

    'ground_rules' => [
        'class' => SectionedGroundRules::class,
        // First section of the ground rules. null renders "What this app is: {app.name}."
        'intro' => null,
        // Extra sections after the package ones: 'heading' => markdown | 'view:name' | Section::class
        'sections' => [],
        // Package sections to drop: intro, safety, tokens, names_and_links, replies, memory
        'remove' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission rules
    |--------------------------------------------------------------------------
    |
    | How the permission checker decides who is staff and who is privileged.
    | staff_permission: the permission everyone reaching a requires_staff
    | server must hold (null = any authenticated user is staff).
    | privileged_roles / privileged_permission: who may hold explicit-only
    | abilities and wildcards, and see other people's tokens and memory.
    | known / known_from: the permissions the catalogue may reference
    | (a list, or a config key whose array keys are the permissions).
    |
    */

    'permission_rules' => [
        'staff_permission' => null,
        'privileged_roles' => [],
        'privileged_permission' => null,
        'privileged_label' => 'a privileged role',
        'ask_label' => 'ask an administrator',
        'known' => null,
        'known_from' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | key => [class, path, label, wildcard, client_name, requires_staff,
    | service_clients, presets, shared, color, icon, description]. presets=false
    | keeps a server's abilities out of the token presets and shared=false keeps
    | the shared primitives (me, remember_about_me, getting_started) and the
    | footer off it — both for a customer-facing server. Every entry is registered
    | at boot through McpKit::server() with the full guard stack. Two servers
    | may share one wildcard.
    |
    */

    'servers' => [
        // 'app' => [
        //     'class' => YourApp\Mcp\Servers\AppServer::class,
        //     'path' => '/mcp/app',
        //     'label' => 'App',
        //     'wildcard' => 'app:*',
        //     'client_name' => 'app',
        //     'requires_staff' => true,
        //     'service_clients' => true,
        //     'color' => 'zinc',
        //     'icon' => 'wrench-screwdriver',
        //     'description' => 'Staff tools.',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ability catalogue
    |--------------------------------------------------------------------------
    |
    | abilities: 'prefix:area:read' => [description, permission|'a|b'|null, server]
    | explicit_only: never granted by a wildcard; only a privileged owner may hold them
    | service_client_writes: writes a service client may still perform
    | aliases: legacy ability => canonical ability (honoured, never minted)
    | super_wildcard: one ability that grants everything but explicit-only
    | read_abilities: abilities that do not end in :read but are reads
    |
    */

    'catalogue' => [
        'abilities' => [],
        'explicit_only' => [],
        'service_client_writes' => [],
        'aliases' => [],
        'super_wildcard' => null,
        'read_abilities' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token presets
    |--------------------------------------------------------------------------
    |
    | grant: 'reads' | 'grantable' | 'wildcards' | a list of abilities.
    | privileged: only privileged owners get the preset.
    |
    */

    'token_presets' => [
        'read' => [
            'label' => 'Read only',
            'description' => 'Look things up in every area you have access to. Nothing can be changed.',
            'grant' => 'reads',
        ],
        'work' => [
            'label' => 'Work',
            'description' => 'Read everywhere you have access, and change what you may change. Explicit-only abilities still have to be granted separately.',
            'grant' => 'grantable',
        ],
        'full' => [
            'label' => 'Full',
            'description' => 'Every ability on every server. Only privileged users can hold this. Explicit-only abilities still have to be granted separately.',
            'grant' => 'wildcards',
            'privileged' => true,
        ],
    ],

    'tokens' => [
        'name_prefix' => 'mcp: ',
        'default_days' => 90,
        'max_days' => 365,
        'default_preset' => 'work',
        // Presets and the tokens page are for staff only (people the permission
        // checker calls staff); customers with a login get no presets.
        'staff_only' => false,
        // Wildcard abilities need a privileged owner. Turn off when every
        // staff member may hold the server wildcard.
        'wildcards_require_privileged' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Extra middleware appended to every server route. throttle: requests per
    | minute per token (null disables). enforce: refuse to boot when an
    | Mcp::web route lacks the kit's guards; allow_unguarded lists route URIs
    | that may stay unguarded (public demo servers).
    |
    */

    'routes' => [
        'middleware' => [],
        'throttle' => 120,
        'enforce' => true,
        'allow_unguarded' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional UI (Livewire + Flux)
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'enabled' => (bool) env('MCP_KIT_UI', false),
        'layout' => null,
        'check_dependencies' => true,
        'tokens_page' => [
            'enabled' => (bool) env('MCP_KIT_TOKENS_PAGE', false),
            'path' => 'settings/tokens',
            'middleware' => ['web', 'auth'],
            'name' => 'mcp-kit.tokens',
        ],

        // What the tools were used for and what people could not get.
        // Privileged only — the component refuses anybody else.
        'usage_page' => [
            'enabled' => (bool) env('MCP_KIT_USAGE_PAGE', false),
            'path' => 'settings/mcp-usage',
            'middleware' => ['web', 'auth'],
            'name' => 'mcp-kit.usage',
        ],
        // Where the assistant-memory section lives, for the link in {scheme}://me
        'memory_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Docs and inventory
    |--------------------------------------------------------------------------
    */

    'docs' => [
        'path' => 'docs/mcp/tools/index.md',
        'inventory' => 'tests/Feature/Mcp/tool-inventory.json',
        // Renders the generated blocks; swap for a different layout.
        'renderer' => DefaultDocsRenderer::class,
    ],

    'instructions' => [
        'append_footer' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared primitives
    |--------------------------------------------------------------------------
    |
    | Appended to every StaffServer. Remove one to drop it everywhere.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | File uploads
    |--------------------------------------------------------------------------
    |
    | MCP carries JSON, not bytes. With this on, the kit registers one HTTP
    | endpoint behind the same token and access gate as the servers; a tool
    | then consumes the returned handle. Staged files expire — a loading
    | dock, not a warehouse — and mcp-kit:prune deletes them.
    |
    */

    'uploads' => [
        'enabled' => (bool) env('MCP_UPLOADS_ENABLED', false),
        'route' => '/mcp/uploads',
        // request_upload hands out a signed link to this path: the file goes
        // up without the bearer token, which an assistant behind a connector
        // never sees. Short on purpose — the link is the credential.
        'link_route' => '/mcp/uploads/link',
        'link_minutes' => (int) env('MCP_UPLOAD_LINK_MINUTES', 30),
        'disk' => 'local',
        'path' => 'mcp-uploads',
        'ttl_days' => (int) env('MCP_UPLOAD_TTL_DAYS', 3),
        'max_kb' => 51200,
        // Empty accepts anything; list client mime types to narrow.
        'mimes' => [],
    ],

    'shared' => [
        'resources' => [GroundRulesResource::class, MeResource::class, PlaybooksResource::class, GapsResource::class, UsageResource::class],
        'tools' => [RememberAboutMeTool::class, SavePlaybookTool::class, ReportGapTool::class, WorkingOnTool::class, ListUploadsTool::class, RequestUploadTool::class],
        'prompts' => [GettingStartedPrompt::class],
    ],

    'me' => [
        'expose_as_tool' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant memory
    |--------------------------------------------------------------------------
    */

    'memory' => [
        'table' => null, // defaults to the users model's table
        'column' => 'assistant_memory',
        'store' => ColumnMemoryStore::class,
        'policy' => DefaultMemoryPolicy::class,
        'max_bytes' => 8192,
        'limits' => [
            'routines' => 12,
            'handoffs' => 12,
            'notes' => 20,
            'item_chars' => 240,
            'role_chars' => 280,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Playbooks
    |--------------------------------------------------------------------------
    |
    | A saved way of working, kept per person and offered as a prompt so a
    | client lists it the way it lists any other. prefix goes in front of
    | every prompt name when the app wants them grouped (e.g. 'pb_').
    | Sharing one with the whole team is privileged by default; point
    | policy at your own class to change who may.
    |
    */

    'playbooks' => [
        'enabled' => true,
        'table' => 'mcp_playbooks',
        'model' => PlaybookModel::class,
        'store' => DatabasePlaybookStore::class,
        'policy' => DefaultPlaybookPolicy::class,
        'prefix' => '',
        'limits' => [
            'per_person' => 30,
            'body_chars' => 4000,
            'title_chars' => 80,
            'description_chars' => 200,
            'arguments' => 8,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gap reports
    |--------------------------------------------------------------------------
    |
    | What people needed and the app could not do. The kit stores them and
    | fires GapReported; routing is yours — listen for it and open a task,
    | an issue, a message, whatever your team actually reads.
    |
    */

    'gaps' => [
        'enabled' => true,
        'table' => 'mcp_gap_reports',
        'model' => GapReport::class,
        'store' => DatabaseGapStore::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Learning: what the tools are used for
    |--------------------------------------------------------------------------
    |
    | The call log knows which tools ran, never what for. With this on, the
    | frame opens itself on the first call and every call in between is
    | stamped with it; the assistant names it and judges it afterwards. Off by default: it records what your colleagues do all day, so
    | turning it on is a decision, and `{scheme}://me` tells them plainly
    | that it is on. Reading the result is privileged, like the gap list.
    | Env-driven so it can be run for a fortnight and turned off again
    | without a deploy.
    |
    */

    'learning' => [
        'enabled' => (bool) env('MCP_LEARNING_ENABLED', false),
        'table' => 'mcp_tasks',
        'model' => TaskModel::class,
        'store' => DatabaseTaskStore::class,

        // An open frame this old is closed as unknown rather than counted
        // as a success nobody vouched for.
        'lifetime_hours' => 4,

        // The call at which an unnamed frame asks to be named, in the
        // result of that call. Low enough to catch real work, high enough
        // that a single lookup is never nagged.
        'nudge_after' => 4,

        // Asked again every this many calls while the frame stays unnamed.
        // A long session loses the first ask to context compaction well
        // before the work is over. 0 asks once.
        'nudge_every' => 25,

        'recent_days' => 30,
        'recent_limit' => 100,

        // null follows activity.retain_days.
        'retain_days' => null,
    ],

    'describer_options' => [
        'privileged_roles' => [],
        'permission_labels' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity (spatie/laravel-activitylog)
    |--------------------------------------------------------------------------
    |
    | log_name: the log every tool call lands in. default_source: the product
    | stamped on rows when no source resolver answers (null leaves the column
    | alone). retain_days: mcp-kit:prune deletes older mcp rows.
    |
    */

    'activity' => [
        'log_name' => 'mcp',
        'log_methods' => ['tools/call'],
        'retain_days' => 90,
        'recent_days' => 14,
        'recent_cache_seconds' => 300,
        'default_source' => null,
        'channel_resolver' => DefaultChannelResolver::class,
        'source_resolver' => NullSourceResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding (the getting_started prompt)
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        'questions' => DefaultQuestions::class,
        'max_questions' => 3,
        // Abilities whose holders write to customers: triggers the tone question
        'customer_facing_abilities' => [],
        'ui' => false,
    ],

    'suggestions_class' => ConfigSuggestions::class,

    // ability => list of things to try, shown to people whose token holds it
    'suggestions' => [],

    /*
    |--------------------------------------------------------------------------
    | The connections next door
    |--------------------------------------------------------------------------
    |
    | What this app does NOT hold, and which connection does. The kit puts it
    | in every server's instructions and matches a gap report against it
    | before anything is filed, so an assistant that has run out of road is
    | told where the road continues instead of working around it — or
    | reaching for a browser, which is what actually happened.
    |
    | **This is your own product family and nothing else.** The list names
    | connections belonging to the same organisation as this app, because it
    | is read by that organisation's staff and their assistants. One client's
    | app must never mention another's: the kit therefore ships this empty,
    | and no default will ever fill it.
    |
    |   'neighbours' => [
    |       'crm' => [
    |           'label' => 'Acme CRM',
    |           'owns' => 'People and everything around them: customers, signups, messages',
    |           'tools' => ['search_contacts', 'list_signups'],
    |           'match' => ['customer', 'kunde', 'signup', 'invoice'],
    |           'ask' => 'You may already have it; if not, ask <name> for a token.',
    |       ],
    |   ],
    |
    */

    'neighbours' => [],

    /*
    |--------------------------------------------------------------------------
    | Hints: the shorter road
    |--------------------------------------------------------------------------
    |
    | One tool called over and over, when one call would have done it. A
    | curriculum sweep spent 434 calls reading a study a page at a time, and
    | a follow-up round spent ninety looking people up one by one; in both
    | cases the tool that answers in one call existed and was listed. Nothing
    | said so at the moment it would have helped.
    |
    | Past `after` calls to the same tool in one stretch of work, the kit
    | appends a sentence to that tool's reply. The call is answered as
    | normal — this is a nudge, never a refusal.
    |
    |   'instead_of' => [
    |       'get_thing' => ['use' => 'get_things', 'say' => 'takes a whole list at once', 'after' => 5],
    |   ],
    |
    */

    'hints' => [
        'enabled' => true,
        'after' => 5,
        'repeat_every' => 15,
        'instead_of' => [],
    ],

];
