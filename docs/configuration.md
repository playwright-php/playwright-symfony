# Configuration Reference

The bundle is highly configurable via `config/packages/test/playwright.yaml`.

## Default Configuration

```yaml
# config/packages/test/playwright.yaml
playwright:
  enabled: true
  debug: '%kernel.debug%'
  default_browser: 'default'
  base_url: 'http://localhost'
  intercepted_hosts:
    - 'localhost'
    - '127.0.0.1'
    - 'testapp.local'

  browsers:
    default:
      type: 'chromium'
      headless: true
      timeout_ms: 30000
      slowmo_ms: 0
```

## Options

### `enabled`

**Type**: `bool` | **Default**: `true`

When set to `false`, the bundle registers no service and no parameter. Use this to keep the bundle
in `config/bundles.php` while deactivating it for a specific environment.

### `intercepted_hosts`

**Type**: `string[]` | **Default**: `['localhost', '127.0.0.1', 'testapp.local']`

List of hostnames that the browser should intercept and route into the Symfony Kernel. Any request to a host *not* in
this list will be handled as a standard external request by the browser.

A hostname such as `localhost` matches every port. Use an entry such as `localhost:82` to intercept only that port.

### `base_url`

**Type**: `string` | **Default**: `%env(default::PLAYWRIGHT_BASE_URL)%`

The base URL used when calling `$this->visit()`. By default the value comes from the
`PLAYWRIGHT_BASE_URL` environment variable when it is defined. When neither the option nor the
environment variable is set, `PlaywrightTestCase` falls back to `http://localhost`.

### `node_path`

**Type**: `string|null` | **Default**: `null`

Path to the Node.js executable running the Playwright server. When `null`, the executable is
auto-detected. Can be overridden per browser.

### `browsers`

**Type**: `array`

Defines named browser configurations.

| Key              | Description                                                     | Default    |
|------------------|-----------------------------------------------------------------|------------|
| `type`           | `chromium`, `firefox`, or `webkit`                              | `chromium` |
| `headless`       | Run browser without UI                                          | `true`     |
| `timeout_ms`     | Timeout in milliseconds for Playwright operations               | `30000`    |
| `slowmo_ms`      | Slow down operations by X ms                                    | `0`        |
| `args`           | Array of browser launch arguments                               | `[]`       |
| `env`            | Environment variables for the Playwright Node.js server process | `[]`       |
| `node_path`      | Override the global `node_path` for this browser                | `null`     |
| `min_node_version` | Minimum Node.js version required to launch the server         | `20.0.0`   |
| `channel`        | Browser distribution channel, e.g. `chrome-beta` or `msedge`    | `null`     |
| `ws_endpoint`    | WebSocket endpoint for an existing remote browser               | `null`     |
| `screenshot_dir` | Default directory where page screenshots are saved              | `null`     |
| `downloads_dir`  | Directory where downloads are stored                            | `null`     |
| `videos_dir`     | Directory where context videos are recorded                     | `null`     |
| `tracing`        | Trace recording, see below                                      | disabled   |
| `proxy`          | Proxy applied to the browser, see below                         | `null`     |

`tracing` keys: `enabled` (`false`), `dir` (`null`), `screenshots` (`false`),
`snapshots` (`false`). `proxy` keys: `server` (required), `username`, `password`,
`bypass`.

These options require `playwright-php/playwright:^1.3`.

#### Remote browser and tracing example

```yaml
playwright:
  browsers:
    default:
      type: chromium
      ws_endpoint: 'ws://browser:3000'
      tracing:
        enabled: true
        dir: '%kernel.project_dir%/var/playwright/traces'
        screenshots: true
        snapshots: true
```

These settings apply to browser services configured by the bundle. `PlaywrightTestCase` creates its
shared browser through `BrowserRegistry::fromEnvironment()` instead. Configure its remote endpoint
and per-test tracing using the environment variables below; YAML `tracing.enabled` does not enable
the test case's failure-artifact lifecycle.

### `assets`

**Type**: `array`

Configures how static assets are served during in-process tests.

```yaml
playwright:
  assets:
    public_roots:
      - '%kernel.project_dir%/public'
    prefixes:
      - '/assets'
      - '/build'
    disable_cache: true
```

| Key             | Description                                     |
|-----------------|-------------------------------------------------|
| `public_roots`  | Directories to search for static files.         |
| `prefixes`      | URL paths that should be treated as assets.     |
| `disable_cache` | If true, adds headers to prevent asset caching. |

## Environment Variables

Configuration can be overridden at runtime using environment variables:

- `PLAYWRIGHT_BROWSER`: `chromium`, `firefox`, or `webkit`.
- `PLAYWRIGHT_HEADLESS`: `true` or `false`.
- `PLAYWRIGHT_CHANNEL`: Chromium channel, such as `chrome` or `msedge`, for `PlaywrightTestCase` and `BrowserRegistry::fromEnvironment()`. Falls back to `PW_CHANNEL` when unset. The selected browser must be installed separately.
- `PLAYWRIGHT_BASE_URL`: Override the default base URL.
- `PLAYWRIGHT_WS_ENDPOINT`: Connect the test case's shared browser to an existing remote browser.
- `PW_WS_ENDPOINT`: Fallback endpoint when `PLAYWRIGHT_WS_ENDPOINT` is absent or empty.
- `PW_TRACE`: Enable per-test tracing in `PlaywrightTestCase` (disabled by default).
- `PW_TRACE_DIR`: Directory for per-test artifacts; defaults to `traces` under the current working directory.
- `PW_TRACE_KEEP_SUCCESS`: Also retain traces for successful tests (disabled by default).

For `PW_TRACE` and `PW_TRACE_KEEP_SUCCESS`, an empty value or `0` disables the option; any other
non-empty string enables it. Use `1` and `0` rather than `true` and `false`.

For example, to use the same remote endpoint and trace directory as the YAML configuration above:

```dotenv
PLAYWRIGHT_BROWSER=chromium
PLAYWRIGHT_WS_ENDPOINT=ws://browser:3000
PW_TRACE=1
PW_TRACE_DIR=/app/var/playwright/traces
PW_TRACE_KEEP_SUCCESS=0
```

Replace `/app` with the project directory visible to the PHP process. Environment values do not
expand Symfony parameters such as `%kernel.project_dir%`.

See [test tracing](usage.md#test-tracing-and-failure-artifacts) for artifact behavior and
[custom browser launch options](usage.md#custom-browser-launch-options) for per-class configuration.
