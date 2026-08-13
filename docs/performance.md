# Performance Optimization

Playwright Symfony is designed to be as fast as possible by minimizing the overhead of browser management.

## Shared Browser Architecture

One of the biggest performance bottlenecks in browser testing is starting the browser process (the "launch").

By default, this bundle reuses a single browser process across all tests in a test class.

### How it works:

1. **Lazy Launch**: The browser process starts when the first test asks for a client. Tests that do not use a browser
   do not start it.
2. **Context Isolation**: Each client owns a Browser Context and Page. All contexts are closed after the test, and fresh
   ones are created on demand in the next test.
3. **Teardown**: The browser process is stopped only after all tests in the class have finished.

### Result:

You get the **perfect isolation** of fresh browser sessions, but only pay the **startup cost once** per test class.

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
