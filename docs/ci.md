# Continuous Integration

Playwright Symfony is designed to run efficiently in CI environments. For GitHub Actions, we recommend using our
official action to set up the environment.

## GitHub Actions

The easiest way to set up Playwright in your GitHub workflows is by using the `playwright-php/setup-playwright`
action.

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --prefer-dist

      - name: Setup Playwright PHP
        uses: playwright-php/setup-playwright@v1
        with:
          browsers: chromium # Optional: chromium, firefox, webkit (default: all)

      - name: Run Tests
        run: vendor/bin/phpunit
        env:
          PLAYWRIGHT_HEADLESS: true
```

## Retaining Failure Artifacts

Enable tracing on the test step and upload the output even when tests fail:

```yaml
      - name: Run Tests
        run: vendor/bin/phpunit
        env:
          PLAYWRIGHT_HEADLESS: 'true'
          PW_TRACE: '1'
          PW_TRACE_DIR: '${{ github.workspace }}/var/playwright/traces'
          PW_TRACE_KEEP_SUCCESS: '0'

      - name: Upload Playwright artifacts
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-traces
          path: var/playwright/traces/
          if-no-files-found: ignore
```

Failed tests save trace archives and page screenshots. Set `PW_TRACE_KEEP_SUCCESS` to `'1'` to
retain successful test traces as well. When tests run in a container, use a trace directory visible
to the PHP process and mount or copy it into the runner workspace before uploading.

Trace files can contain page content, screenshots, source code, and request data. Treat them as
potentially sensitive and restrict artifact access and retention accordingly.

## GitLab CI

For GitLab, you can use a Node-capable image or install Node manually in your PHP environment.

```yaml
# .gitlab-ci.yml
test:
  image: php:8.3
  variables:
    PLAYWRIGHT_HEADLESS: "true"
  before_script:
    # Install Node.js
    - curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    - apt-get install -y nodejs
    # Install PHP dependencies
    - composer install
    # Install Playwright Server and Browsers
    - vendor/bin/playwright-install --browsers
  script:
    - vendor/bin/phpunit
```

## Performance Tip: Parallelization

If you run tests in parallel, ensure your `PLAYWRIGHT_BASE_URL` or database configuration accounts for concurrent
access. Since Playwright Symfony reuses the browser process within a single class, standard PHPUnit parallelization (
using `brianium/paratest`) works out of the box.
