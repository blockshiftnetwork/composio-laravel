# TODO: Composio TypeScript Parity

## Current PHP SDK v1 Gap

This wrapper supports the main paths currently available in `blockshiftnetwork/composio-php` v1:

- create and resume Tool Router sessions
- execute tools through a session
- convert tools to PrismPHP
- convert tools to Laravel AI
- execute direct tools
- main managers for auth configs, connected accounts, toolkits, triggers, MCP, and files

The real E2E test with a Composio API key validated:

- direct PrismPHP conversion
- direct Laravel AI conversion
- real direct execution
- session creation and resume
- session tool conversion to PrismPHP
- session tool conversion to Laravel AI
- real session execution

## Pending Because composio-php Does Not Generate These Methods Yet

The current TypeScript SDK exposes a richer Tool Router layer. The installed PHP SDK v1 does not include generated equivalents for:

- `session.search(...)`
- `session.update(...)`
- `session.proxyExecute(...)`

There are also advanced TypeScript features that should be verified before promising full PHP parity:

- complete `workbench` payloads
- `multiAccount`
- `preload`
- `experimental.customTools`
- `experimental.customToolkits`
- `experimental.assistivePrompt`

## Implementation Criteria

Do not simulate these routes in the wrapper until the real endpoints and payloads are confirmed. While `composio-php` does not expose them, wrapper methods should throw an explicit `ComposioException`.

If full parity is needed before the PHP SDK generates these methods, implement a raw HTTP layer with Guzzle only after:

- confirming endpoint, HTTP method, and payload against the real API or current spec
- adding unit tests for request payloads
- adding a local non-versioned E2E test
- avoiding API keys or sensitive fixtures in the repository

## E2E Compatibility Notes

- The PHP SDK can return schemas as `stdClass`; mappers must accept `array|object`.
- Composio validates `arguments` as a JSON object; an empty PHP array serializes as `[]`, so `stdClass` must be used to represent `{}`.
- `sessionPreset: direct_tools` should follow the current TypeScript transformation: `search.enable=false` and `execute.enable_multi_execute=false`.
