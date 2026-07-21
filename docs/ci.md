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
          PLAYWRIGHT_E2E: 1
          PLAYWRIGHT_HEADLESS: true
```

## GitLab CI

For GitLab, you can use a Node-capable image or install Node manually in your PHP environment.

```yaml
# .gitlab-ci.yml
test:
  image: php:8.3
  variables:
    PLAYWRIGHT_E2E: "1"
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
