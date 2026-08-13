# Compatibility with Zenstruck Browser v2

This document evaluates the effort required to make `playwright-symfony` compatible with `zenstruck/browser:^2.0` (
currently in development).

## Executive Summary

Making this package compatible with Zenstruck Browser v2 is **relatively straightforward**. Because our
`PlaywrightKernelClient` is a standard Symfony `AbstractBrowser`, the core integration is already technically
functional.

To provide a first-class experience (with custom assertions and fluent helpers), we would need to implement a small "
bridge" class.

## Effort Analysis

### 1. Core Integration (Immediate)

**Effort: Zero**
Since `PlaywrightKernelClient` extends `Symfony\Component\BrowserKit\AbstractBrowser`, you can already wrap it in a
standard Zenstruck Browser:

```php
use Zenstruck\Browser;
// ...
$browser = new Browser(static::getPlaywrightClient());
```

### 2. Specialized Playwright Browser (Recommended)

**Effort: Low (1-2 days)**
To match the DX of `Zenstruck\Browser\KernelBrowser` or `PantherBrowser`, we should provide a
`Zenstruck\Browser\PlaywrightBrowser`.

**What needs to be done:**

- Create a class that extends `Zenstruck\Browser`.
- Add Playwright-specific traits (e.g., for screenshots, waiting for elements).
- Map Zenstruck's `check()`, `fill()`, `click()` to our client's implementation.

### 3. Automatic Integration / Autowiring

**Effort: Moderate**
Zenstruck Browser v2 likely uses a specialized factory or attribute-based discovery. We would need to:

- Register a `PlaywrightBrowser` service in the container.
- Provide a `Zenstruck\Browser\Test\HasPlaywright` trait for test cases.

## Comparison with Panther Integration

The integration would follow the same pattern as `PantherBrowser` in Zenstruck Browser:

- **Similarity**: Both drive a real browser.
- **Difference**: Playwright offers better network interception and faster startup (especially with our new shared
  browser architecture), which would make the Zenstruck + Playwright combination extremely fast for E2E tests.

## Roadmap for Compatibility

If we decide to officially support Zenstruck Browser v2:

1. **Draft a `PlaywrightBrowser` wrapper**: Implement the `Zenstruck\Browser` interface.
2. **Add specialized assertions**: Create a `PlaywrightAssertions` trait for Zenstruck's assertion engine.
3. **Bundle Integration**: Update `PlaywrightExtension` to automatically register the Zenstruck-compatible service if
   the package is installed.

## Conclusion

The architecture of `playwright-symfony` 1.0 was designed with modularity in mind. By decoupling the logic into
`FormInteractor` and `XPathHelper`, we have already done the hard work. Porting this to a Zenstruck Browser extension is
mostly a matter of wrapping these existing utilities in the Zenstruck fluent API.
