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
| `screenshot_dir` | Default directory where page screenshots are saved              | `null`     |

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

- `PLAYWRIGHT_E2E`: Set to `1` to enable browser tests.
- `PLAYWRIGHT_BROWSER`: `chromium`, `firefox`, or `webkit`.
- `PLAYWRIGHT_HEADLESS`: `true` or `false`.
- `PLAYWRIGHT_BASE_URL`: Override the default base URL.
