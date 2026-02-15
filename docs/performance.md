# Performance Optimization

Playwright Symfony is designed to be as fast as possible by minimizing the overhead of browser management.

## Shared Browser Architecture

One of the biggest performance bottlenecks in browser testing is starting the browser process (the "launch").

By default, this bundle reuses a single browser process across all tests in a test class.

### How it works:
1.  **Launch**: The browser process is started once before the first test method.
2.  **Context Isolation**: Between each test, the bundle calls `restartContext()`. This:
    - Closes all open pages.
    - Clears all cookies and storage.
    - Creates a fresh Browser Context and Page.
3.  **Teardown**: The browser process is stopped only after all tests in the class have finished.

### Result:
You get the **perfect isolation** of a fresh browser session for every test, but only pay the **startup cost once** per test suite.

## In-Process Kernel Interception

Because the browser routes requests directly into your `HttpKernel` (using Playwright's network interception), there is **no network overhead**.

- No local web server (like `bin/console server:start`) is required.
- No real network sockets are used for application requests.
- Database transactions can be shared between your test and the application code.

## Asset Server Bridge

Normally, in-process tests struggle with CSS and JavaScript files because they are served by the web server.

This bundle includes an **Asset Server Bridge** that automatically intercepts requests for assets (using prefixes like `/assets` or `/build`) and serves them directly from your project's filesystem or via `AssetMapper`.

This ensures your pages render correctly in Playwright without needing a production build of your assets.
