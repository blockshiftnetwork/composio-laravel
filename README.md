# Composio Laravel

A Laravel package that connects your AI agents to **250+ external tools** (GitHub, Slack, Gmail, Notion, and more) via [Composio](https://composio.dev). Works with both **PrismPHP** and **Laravel AI**.

Composio handles the hard parts: OAuth flows, credential management, API normalization, and rate limiting. This package converts Composio's tool definitions into native tool objects for your preferred AI framework, so your agents can call external APIs with a single line of code.

## How It Works

```
Your Laravel App
    |
    v
Composio Laravel  -->  converts to  -->  PrismPHP Tool objects
    |                                     or Laravel AI Tool objects
    v
Composio API  -->  handles auth  -->  GitHub, Slack, Gmail, etc.
```

1. You ask for tools (e.g. "give me GitHub tools")
2. This package fetches tool schemas from Composio and converts them to PrismPHP or Laravel AI tool objects
3. You pass those tools to your AI model
4. When the AI calls a tool, Composio executes it against the real API using your user's connected account

---

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- A [Composio API key](https://app.composio.dev)

## Installation

```bash
composer require blockshiftnetwork/composio-laravel
```

Then install whichever AI framework you want to use (or both):

```bash
# For PrismPHP
composer require prism-php/prism

# For Laravel AI
composer require laravel/ai
```

## Configuration

Add your API key to `.env`:

```env
COMPOSIO_API_KEY=your-composio-api-key
```

The package reads from `config/services.php` under the `composio` key. Defaults are provided via environment variables, so no config publishing is needed. To override values explicitly, add to your `config/services.php`:

```php
'composio' => [
    'api_key'           => env('COMPOSIO_API_KEY'),
    'base_url'          => env('COMPOSIO_BASE_URL', 'https://backend.composio.dev'),
    'default_user_id'   => env('COMPOSIO_DEFAULT_USER_ID'),
    'default_entity_id' => env('COMPOSIO_DEFAULT_ENTITY_ID'),
],
```

---

## Quick Start

### PrismPHP

```php
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;
use Prism\Prism\Prism;

// Get GitHub tools as PrismPHP Tool objects
$tools = Composio::toolSet(userId: 'user_123')
    ->getTools(toolkitSlug: 'github');

// Pass them to any LLM via PrismPHP
$response = Prism::text()
    ->using('anthropic', 'claude-sonnet-4-20250514')
    ->withTools($tools)
    ->withPrompt('Star the repo composio-dev/composio on GitHub')
    ->generate();
```

### Laravel AI

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

class GitHubAssistant implements Agent, HasTools
{
    public function prompt(): string
    {
        return 'You are a GitHub assistant. Help users manage issues and PRs.';
    }

    public function tools(): iterable
    {
        return Composio::toolSet(userId: 'user_123')
            ->getLaravelAiTools(toolkitSlug: 'github');
    }
}
```

---

## Usage Guide

### Creating a ToolSet

Everything starts with a `ToolSet`. Create one via the facade:

```php
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

// Basic toolset
$toolSet = Composio::toolSet();

// Toolset scoped to a specific user
$toolSet = Composio::toolSet(userId: 'user_123');

// Toolset with both user and entity
$toolSet = Composio::toolSet(userId: 'user_123', entityId: 'entity_456');
```

Or via dependency injection:

```php
use BlockshiftNetwork\ComposioLaravel\ComposioManager;

class MyService
{
    public function __construct(private ComposioManager $composio) {}

    public function doSomething()
    {
        $toolSet = $this->composio->toolSet(userId: 'user_123');
    }
}
```

### Fetching Tools

#### Get all tools from a toolkit

```php
$tools = $toolSet->getTools(toolkitSlug: 'github');        // PrismPHP
$tools = $toolSet->getLaravelAiTools(toolkitSlug: 'slack'); // Laravel AI
```

#### Get specific tools by slug

```php
$tools = $toolSet->getTools(toolSlugs: [
    'GITHUB_CREATE_ISSUE',
    'GITHUB_LIST_ISSUES',
    'GITHUB_CLOSE_ISSUE',
]);
```

#### Filter by tags

```php
$tools = $toolSet->getTools(toolkitSlug: 'github', tags: ['issues']);
```

#### Search for tools

```php
$tools = $toolSet->getTools(search: 'send email');
```

#### Get a single tool

```php
$tool = $toolSet->getTool('GITHUB_CREATE_ISSUE');           // PrismPHP
$tool = $toolSet->getLaravelAiTool('SLACK_SEND_MESSAGE');   // Laravel AI
```

### User Scoping

Each user in your app can have their own connected accounts (e.g., their own GitHub OAuth token). Scope your toolset per-user so tool executions use the correct credentials.

```php
// At creation time
$toolSet = Composio::toolSet(userId: 'user_123');

// Or fluently (returns a new immutable instance)
$toolSet = Composio::toolSet()
    ->forUser('user_123')
    ->withConnectedAccount('conn_abc');

// Scoping is immutable -- the original is unchanged
$base = Composio::toolSet();
$alice = $base->forUser('alice');  // new instance
$bob   = $base->forUser('bob');    // new instance, $base unchanged
```

### Direct Tool Execution

Execute tools programmatically without going through an LLM:

```php
$result = Composio::toolSet(userId: 'user_123')
    ->execute('GITHUB_CREATE_ISSUE', [
        'owner'  => 'myorg',
        'repo'   => 'myrepo',
        'title'  => 'Bug: login page returns 500',
        'body'   => 'Steps to reproduce: ...',
        'labels' => ['bug', 'priority-high'],
    ]);

if ($result->isSuccessful()) {
    $data = $result->data();          // array of response data
    echo "Created issue #{$data['number']}";
} else {
    echo "Error: " . $result->error();  // error message string
    echo "Log ID: " . $result->logId(); // for debugging with Composio
}
```

`ExecutionResult` methods:

| Method           | Returns    | Description                           |
|------------------|------------|---------------------------------------|
| `isSuccessful()` | `bool`     | Whether the execution succeeded       |
| `data()`         | `array`    | The tool's response data              |
| `error()`        | `?string`  | Error message if execution failed     |
| `logId()`        | `string`   | Composio log ID for debugging         |
| `toToolOutput()` | `string`   | JSON string (used internally by converters) |
| `raw()`          | `Response` | The raw API response object           |

---

## Execution Hooks

Hooks let you intercept tool executions to modify arguments, log activity, enforce permissions, or transform results.

### Before-execution hooks

Modify arguments before a tool runs:

```php
$toolSet = Composio::toolSet(userId: 'user_123');

// Hook for a specific tool
$toolSet->hooks()->beforeExecute('GITHUB_CREATE_ISSUE', function (string $tool, array $args) {
    $args['labels'] = array_merge($args['labels'] ?? [], ['auto-created']);
    return $args; // must return the (modified) arguments array
});

// Hook for multiple tools
$toolSet->hooks()->beforeExecute(
    ['GITHUB_CREATE_ISSUE', 'GITHUB_UPDATE_ISSUE'],
    function (string $tool, array $args) {
        $args['_metadata'] = ['source' => 'ai-agent'];
        return $args;
    }
);
```

### After-execution hooks

Log or transform results after a tool runs:

```php
// Wildcard '*' matches all tools
$toolSet->hooks()->afterExecute('*', function (string $tool, ExecutionResult $result) {
    Log::info('Composio tool executed', [
        'tool'       => $tool,
        'successful' => $result->isSuccessful(),
        'log_id'     => $result->logId(),
    ]);
    return $result; // must return the (modified) result
});
```

### Hook interfaces

For reusable hooks, implement the interfaces:

```php
use BlockshiftNetwork\ComposioLaravel\Hooks\BeforeExecuteHook;
use BlockshiftNetwork\ComposioLaravel\Hooks\AfterExecuteHook;

class AuditHook implements AfterExecuteHook
{
    public function handle(string $toolSlug, ExecutionResult $result): ExecutionResult
    {
        AuditLog::create([
            'tool'    => $toolSlug,
            'success' => $result->isSuccessful(),
            'log_id'  => $result->logId(),
        ]);
        return $result;
    }
}

$toolSet->hooks()->afterExecute('*', new AuditHook);
```

---

## Connected Accounts

Manage OAuth connections for your users:

```php
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

// List all connected accounts
$accounts = Composio::connectedAccounts()->list();

// Filter by user
$accounts = Composio::connectedAccounts()->list(userIds: ['user_123']);

// Filter by toolkit
$accounts = Composio::connectedAccounts()->list(toolkitSlugs: ['github']);

// Get a specific connected account
$account = Composio::connectedAccounts()->get('connected_account_id');

// Refresh an account's credentials
Composio::connectedAccounts()->refresh('connected_account_id');

// Delete a connected account
Composio::connectedAccounts()->delete('connected_account_id');
```

### Auth Configs

Manage authentication configurations (OAuth apps, API keys):

```php
// List auth configs
$configs = Composio::authConfigs()->list();

// Filter by toolkit
$configs = Composio::authConfigs()->list(toolkitSlug: 'github');

// Get, update, delete
$config = Composio::authConfigs()->get('auth_config_id');
Composio::authConfigs()->delete('auth_config_id');
```

---

## Toolkits

Browse the catalog of integrations available on Composio:

```php
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

// List toolkits (paginated)
$toolkits = Composio::toolkits()->list(category: 'productivity', limit: 20);

// Get a single toolkit
$github = Composio::toolkits()->get('github');

// List categories
$categories = Composio::toolkits()->categories();

// Changelog
$changelog = Composio::toolkits()->changelog();
```

---

## Triggers

[Triggers](https://docs.composio.dev/docs/triggers) deliver events from external apps (new Slack message, new GitHub commit, incoming Gmail, etc.) to your application.

### Managing trigger types and instances

```php
use BlockshiftNetwork\Composio\Model\PostTriggerInstancesBySlugUpsertRequest;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

// Discover available trigger types
$types = Composio::triggers()->listTypes(toolkitSlugs: ['github']);
$type  = Composio::triggers()->getType('GITHUB_PUSH_EVENT');

// Activate a trigger for a connected account
$request = new PostTriggerInstancesBySlugUpsertRequest;
$request->setConnectedAccountId('conn_abc');
$request->setTriggerConfig(['repository' => 'myorg/myrepo']);

$instance = Composio::triggers()->upsert('GITHUB_PUSH_EVENT', $request);

// List active instances
$instances = Composio::triggers()->listInstances(connectedAccountIds: ['conn_abc']);

// Pause / resume / delete
Composio::triggers()->disable('trigger_instance_id');
Composio::triggers()->enable('trigger_instance_id');
Composio::triggers()->delete('trigger_instance_id');
```

### Receiving webhook events

This package does **not** ship a webhook controller. Composio webhooks are signed with HMAC-SHA256 in the `webhook-signature` header (format `v1,<base64_signature>`), and the cleanest way to handle them in Laravel is the [`spatie/laravel-webhook-client`](https://github.com/spatie/laravel-webhook-client) package, which gives you signature verification, queued processing, and replay protection out of the box.

Install Spatie's package:

```bash
composer require spatie/laravel-webhook-client
php artisan vendor:publish --provider="Spatie\WebhookClient\WebhookClientServiceProvider" --tag="webhook-client-config"
```

Implement a `SignatureValidator` that verifies Composio's HMAC signature:

```php
namespace App\Webhooks\Composio;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class ComposioSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $header = $request->header('webhook-signature');
        if (! $header || ! str_starts_with($header, 'v1,')) {
            return false;
        }

        $provided = base64_decode(substr($header, 3));
        $expected = hash_hmac('sha256', $request->getContent(), $config->signingSecret, true);

        return hash_equals($expected, $provided);
    }
}
```

Wire it up in `config/webhook-client.php`:

```php
'configs' => [
    [
        'name'                => 'composio',
        'signing_secret'      => env('COMPOSIO_WEBHOOK_SECRET'),
        'signature_header_name' => 'webhook-signature',
        'signature_validator' => \App\Webhooks\Composio\ComposioSignatureValidator::class,
        'webhook_profile'     => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
        'webhook_response'    => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
        'webhook_model'       => \Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => \App\Jobs\ProcessComposioWebhook::class,
    ],
],
```

Register the route and write your job:

```php
// routes/web.php
Route::webhooks('/webhooks/composio', 'composio');
```

```php
// app/Jobs/ProcessComposioWebhook.php
namespace App\Jobs;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessComposioWebhook extends ProcessWebhookJob
{
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        // dispatch domain logic based on $payload['type'], $payload['data'], etc.
    }
}
```

> Composio's webhook configuration UI lives at [app.composio.dev](https://app.composio.dev). The signing secret you put in `COMPOSIO_WEBHOOK_SECRET` must match the one shown there. See the [Using Triggers guide](https://docs.composio.dev/docs/using-triggers) for the full event payload format.

---

## MCP Servers

[Composio MCP](https://docs.composio.dev/docs/mcp-quickstart) generates a [Model Context Protocol](https://modelcontextprotocol.io/) server URL bundling whichever toolkits and auth configs you choose. Drop the URL into Claude Desktop, Cursor, ChatGPT, or any MCP-compatible client and your toolkits become first-class tools for the LLM — no custom server to write.

```php
use BlockshiftNetwork\Composio\Model\PostMcpServersCustomRequest;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

// Create a custom MCP server bundling multiple toolkits
$request = new PostMcpServersCustomRequest;
$request->setName('engineering-assistant');
$request->setToolkits(['github', 'slack', 'linear']);
// $request->setAuthConfigIds(['ac_github_xxx', 'ac_slack_yyy']);

$server = Composio::mcp()->createCustomServer($request);

// Browse / fetch / update / delete
$servers = Composio::mcp()->list(name: 'engineering-assistant');
$server  = Composio::mcp()->get('mcp_server_id');
Composio::mcp()->delete('mcp_server_id');

// Per-user instances of a server
$instances = Composio::mcp()->listInstances('mcp_server_id');
Composio::mcp()->deleteInstance('mcp_server_id', 'instance_id');
```

---

## Files

List file artifacts that have been uploaded as inputs/outputs of tool executions:

```php
$files = Composio::files()->list(toolkitSlug: 'github', limit: 50);
```

> Uploads themselves go through Composio's presigned URL flow at execution time and are not exposed as a separate SDK call. The `files()->list()` endpoint is the read-side view.

---

## Advanced Tool Execution

### Tool enums

Fetch the canonical list of all tool slugs available on Composio:

```php
$enums = Composio::toolSet()->enums();
```

### Generate tool inputs from natural language

Given a natural language description, ask Composio to produce the structured arguments for a specific tool:

```php
$inputs = Composio::toolSet(userId: 'user_123')
    ->generateInputs('GITHUB_CREATE_ISSUE', 'Open an issue about the login redirect bug, label it priority-high');
```

### Proxy execute (call any HTTP endpoint with a connected account's auth)

When Composio doesn't yet have a tool for the operation you need, you can proxy a raw HTTP request through any toolkit's authenticated connection:

```php
use BlockshiftNetwork\Composio\Model\PostToolsExecuteProxyRequest;

$request = new PostToolsExecuteProxyRequest;
$request->setEndpoint('/repos/myorg/myrepo/labels');
$request->setMethod('GET');
// $request->setBody([...]);
// $request->setParameters([...]);

$response = Composio::toolSet(userId: 'user_123')
    ->withConnectedAccount('conn_github_abc')
    ->proxyExecute($request);
```

### Custom (local) tools

Expose your own PHP closures as tools alongside the Composio catalog. They appear in `getTools()` / `getLaravelAiTools()` and run locally — no API call to Composio:

```php
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

Composio::customTools()->register(
    slug: 'INTERNAL_LOOKUP_USER',
    description: 'Looks up a user from our internal database by email',
    inputSchema: [
        'type'       => 'object',
        'properties' => [
            'email' => ['type' => 'string', 'description' => 'The email to search for'],
        ],
        'required' => ['email'],
    ],
    handler: function (array $args): array {
        $user = \App\Models\User::where('email', $args['email'])->firstOrFail();
        return ['id' => $user->id, 'name' => $user->name];
    },
);

// Now it shows up alongside Composio tools
$tools = Composio::toolSet()->getTools(toolkitSlug: 'github');
// $tools contains GitHub tools + the INTERNAL_LOOKUP_USER local tool
```

Custom tools fully participate in the hook system — `beforeExecute` and `afterExecute` hooks fire for them just like remote tools.

---

## Full Examples

### PrismPHP: AI assistant in a controller

```php
namespace App\Http\Controllers;

use BlockshiftNetwork\ComposioLaravel\Facades\Composio;
use Illuminate\Http\Request;
use Prism\Prism\Prism;

class AiController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate(['prompt' => 'required|string']);

        $tools = Composio::toolSet(userId: (string) $request->user()->id)
            ->getTools(toolkitSlug: 'github');

        $response = Prism::text()
            ->using('anthropic', 'claude-sonnet-4-20250514')
            ->withTools($tools)
            ->withMaxSteps(5)
            ->withPrompt($request->input('prompt'))
            ->generate();

        return response()->json(['response' => $response->text]);
    }
}
```

### PrismPHP: Combine tools from multiple services

```php
$toolSet = Composio::toolSet(userId: 'user_123');

$tools = array_merge(
    $toolSet->getTools(toolkitSlug: 'github', tags: ['issues']),
    $toolSet->getTools(toolkitSlug: 'slack'),
    $toolSet->getTools(toolkitSlug: 'gmail'),
);

$response = Prism::text()
    ->using('openai', 'gpt-4o')
    ->withTools($tools)
    ->withPrompt('Check my open GitHub issues, summarize them, and send a digest to #engineering on Slack')
    ->generate();
```

### Laravel AI: Multi-tool agent

```php
namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;

class DevOpsAgent implements Agent, HasTools
{
    public function __construct(private string $userId) {}

    public function prompt(): string
    {
        return 'You manage GitHub issues and send Slack notifications for the engineering team.';
    }

    public function tools(): iterable
    {
        $toolSet = Composio::toolSet(userId: $this->userId);

        return [
            ...$toolSet->getLaravelAiTools(toolkitSlug: 'github', tags: ['issues']),
            ...$toolSet->getLaravelAiTools(toolkitSlug: 'slack'),
        ];
    }
}
```

```php
// Usage
use Laravel\Ai\Facades\Ai;

$response = Ai::agent(new DevOpsAgent(auth()->id()))
    ->ask('Create an issue for the login bug and notify #backend on Slack');
```

### Direct execution: Automate without an LLM

```php
// Use in a scheduled command, queue job, or webhook handler
$toolSet = Composio::toolSet(userId: 'bot_user');

// Create a GitHub issue
$issue = $toolSet->execute('GITHUB_CREATE_ISSUE', [
    'owner' => 'myorg',
    'repo'  => 'myrepo',
    'title' => 'Automated: daily health check failed',
    'body'  => 'The health check endpoint returned 503 at ' . now(),
]);

// Send a Slack message
$toolSet->execute('SLACK_SEND_MESSAGE', [
    'channel' => '#alerts',
    'text'    => "Health check failed. Issue created: #{$issue->data()['number']}",
]);
```

### Hooks: Add logging and permissions

```php
$toolSet = Composio::toolSet(userId: 'user_123');

// Log every tool execution
$toolSet->hooks()->afterExecute('*', function (string $tool, $result) {
    Log::channel('composio')->info("Tool: {$tool}", [
        'success' => $result->isSuccessful(),
        'log_id'  => $result->logId(),
    ]);
    return $result;
});

// Prevent destructive actions in production
$toolSet->hooks()->beforeExecute(
    ['GITHUB_DELETE_REPO', 'SLACK_DELETE_CHANNEL'],
    function (string $tool, array $args) {
        if (app()->isProduction()) {
            throw new \RuntimeException("Tool {$tool} is blocked in production");
        }
        return $args;
    }
);

$tools = $toolSet->getTools(toolkitSlug: 'github');
// Hooks fire automatically when the AI calls any tool
```

---

## Architecture

```
src/
  ComposioServiceProvider.php    # Registers bindings, merges config into services.composio
  ComposioManager.php            # Main entry point (Facade target)
  ComposioToolSet.php            # Fetches tools, converts, executes, scopes
  Facades/Composio.php           # Laravel Facade

  ToolConverter/
    ToolConverterInterface.php   # Common interface for converters
    PrismToolConverter.php       # Composio Tool -> PrismPHP Tool
    LaravelAiToolConverter.php   # Composio Tool -> Laravel AI Tool
    SchemaMapper.php             # JSON Schema -> PrismPHP parameters
    LaravelAiSchemaMapper.php    # JSON Schema -> Laravel AI JsonSchema types

  LaravelAi/
    ComposioTool.php             # Implements Laravel\Ai\Contracts\Tool

  Execution/
    ToolExecutor.php             # Calls Composio API to execute tools
    ExecutionResult.php          # Typed wrapper around execution response

  Hooks/
    HookManager.php              # Before/after hook registry and runner
    BeforeExecuteHook.php        # Interface for before-execution hooks
    AfterExecuteHook.php         # Interface for after-execution hooks

  Auth/
    ConnectedAccountManager.php  # CRUD for connected accounts
    AuthConfigManager.php        # CRUD for auth configurations

  Toolkits/
    ToolkitManager.php           # List/get toolkits, categories, changelog

  Triggers/
    TriggerManager.php           # CRUD trigger types & instances, enable/disable

  Mcp/
    McpServerManager.php         # Create custom MCP servers, manage instances

  Files/
    FileManager.php              # List uploaded file artifacts

  Tools/
    CustomToolRegistry.php       # Register local PHP closures as tools
    CustomTool.php               # Value object for a registered custom tool

  Entities/
    Entity.php                   # User-scoped convenience wrapper

  Exceptions/
    ComposioException.php        # Base exception
    AuthenticationException.php  # Auth failures
    ToolExecutionException.php   # Tool execution failures
```

### Key Design Decisions

- **Dual framework support**: PrismPHP and Laravel AI are both optional. The package detects which is installed at runtime via `class_exists()`. You can use either, both, or neither (direct execution only).
- **Immutable scoping**: `forUser()`, `forEntity()`, and `withConnectedAccount()` return cloned instances, so the original toolset is never mutated.
- **Automatic pagination**: `getTools()` follows cursor-based pagination internally, returning all matching tools.
- **Hook wildcards**: Use `'*'` to match all tools, or target specific tool slugs.

---

## Development

### Testing

```bash
composer test
```

### Code Quality

```bash
# Run all quality checks at once
composer quality

# Or individually
composer pint:check    # Code style (Laravel Pint)
composer phpstan       # Static analysis (PHPStan level 5)
composer rector:dry-run # Code modernization (Rector)

# Auto-fix code style
composer pint

# Auto-apply Rector fixes
composer rector
```

### CI/CD

GitHub Actions runs automatically on push to `main` and on pull requests:

- **Tests** — PHP 8.3/8.4 with Laravel 12
- **Code Quality** — Pint, PHPStan, and Rector checks in parallel

---

## License

MIT
