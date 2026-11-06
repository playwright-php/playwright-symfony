# Playwright Logging Guide

The bundle can emit structured logs for every intercepted Playwright request. Logging is disabled by default to keep CI
output quiet.

## Enabling Logs

1. **Bundle config** (e.g. `config/packages/test/playwright.yaml`):

```yaml
playwright:
  debug_logging: true
```

2. **Environment override**: `PLAYWRIGHT_VERBOSE=1 vendor/bin/phpunit …`

The environment variable always wins over configuration.

## Log Channels & Levels

- Logs are sent to the `monolog.logger.playwright` channel when available, otherwise the default `logger` service.
- When debug logging is enabled:
    - `info`: emitted after each intercepted request (includes method, URI, status, duration).
    - `debug`: emitted for routing decisions (asset hits/misses, external requests handed on with `fallback()`) and navigation calls.
- Errors (e.g. kernel exceptions) are logged regardless of the debug flag.

### Suggested Monolog config

```yaml
# config/packages/test/monolog.yaml
monolog:
  channels: ['playwright']
  handlers:
    playwright:
      type: stream
      path: '%kernel.logs_dir%/playwright.log'
      channels: ['playwright']
      level: debug
```

## Sample Output

```text
[info] Fulfilled intercepted request {"method":"GET","uri":"http://localhost/login","status_code":200,"duration_ms":12}
[debug] AssetServer fulfilled request {"url":"http://localhost/assets/app.css","method":"GET"}
```

Use these logs to trace failing browser interactions without re-running tests locally.
