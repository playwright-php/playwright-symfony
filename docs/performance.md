# Performance Optimization

Playwright Symfony is designed to be as fast as possible by minimizing the overhead of browser management.

## Shared Browser Architecture

One of the biggest performance bottlenecks in browser testing is starting the browser process (the "launch").

This bundle launches one browser process per PHP process and reuses it for every test, across test classes. This is
the model of Playwright Test, which keeps one browser per worker and gives each test its own context.

### How it works:

1. **Lazy Launch**: The browser process starts when the first test asks for a client. Tests that do not use a browser
   do not start it.
2. **Context Isolation**: Each client owns a Browser Context and Page. All contexts are closed after the test, and fresh
   ones are created on demand in the next test.
3. **Relaunch**: The browser is relaunched when a test requests another browser configuration (browser type, headless
   mode, channel), or when it fails to open the first context of a test, as after a crash or the loss of its
   Playwright process.
4. **Teardown**: The browser process is stopped on PHP shutdown.

### Result:

You get the isolation of fresh browser sessions, and pay the startup cost once per PHP process.

With `brianium/paratest`, each worker is its own PHP process and keeps its own browser.

### One browser per test class

Set `PLAYWRIGHT_REUSE_BROWSER=false` to stop the browser after each test class:

```bash
PLAYWRIGHT_REUSE_BROWSER=false vendor/bin/phpunit
```

Each class then pays the startup cost again. Use it to check whether a failure depends on a browser process shared
with earlier classes. The values `0`, `off` and `no` have the same effect.

## In-Process Kernel Interception

Because the browser routes requests directly into your `HttpKernel` (using Playwright's network interception), there is
**no network overhead**.

- No local web server (like `bin/console server:start`) is required.
- No real network sockets are used for application requests.
- Database transactions can be shared between your test and the application code.

## Asset Server Bridge

Normally, in-process tests struggle with CSS and JavaScript files because they are served by the web server.

This bundle includes an **Asset Server Bridge** that automatically intercepts requests for assets (using prefixes like
`/assets` or `/build`) and serves them directly from your project's filesystem or via `AssetMapper`.

This ensures your pages render correctly in Playwright without needing a production build of your assets.
