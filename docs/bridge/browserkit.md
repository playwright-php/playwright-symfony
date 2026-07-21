# BrowserKit + DomCrawler bridge for Playwright

This bridge provides a Symfony BrowserKit client backed by a real Playwright browser/page. It returns Symfony DomCrawler
snapshots after each action.

## Why

- Keep the familiar BrowserKit/DomCrawler testing style.
- Get full JS/DOM/CSS behavior thanks to Playwright, similar to Symfony Panther.

## Install

- `composer require symfony/browser-kit symfony/dom-crawler`
- Requires: `playwright-php/playwright`

## Usage

```php
use Playwright\Symfony\BrowserKit\PlaywrightClient;
use Playwright\Playwright;

$pw = Playwright::chromium()->launch();
$context = $pw->newContext();

$client = PlaywrightClient::fromContext($context);

$crawler = $client->request('GET', 'https://example.com');
$link = $crawler->selectLink('More information')->link();
$crawler = $client->click($link);
$form = $crawler->selectButton('Submit')->form([
    'username' => 'alice',
    'password' => 'secret',
]);
$crawler = $client->submit($form);
```

## Semantics

- Real browser interactions: click/submit use Playwright, not synthetic HTTP.
- `request()` with method !== GET constructs and submits a synthetic in-page form to preserve browser semantics.
- Response mapping: uses last Playwright Response (status, headers) + page content for BrowserKit Response.
- Cookies: kept in sync between BrowserKit CookieJar and Playwright BrowserContext.

## Options

- `setFollowPopups(bool)`: disabled by default. When enabled, a click or submit that opens a new
  tab makes the client switch to it (Panther-like). Enabling it makes every click() and submit()
  wait for a potential popup before returning, so actions that do not open one pay the popup
  timeout: enable it only around interactions expected to open a new tab.

```php
$client->setFollowPopups(true);
$client->click($crawler->selectLink('Open preview')->link());
$client->setFollowPopups(false);
```

## Container integration

With the Symfony bundle enabled, you can autowire `Playwright\Symfony\BrowserKit\PlaywrightClient` (service id
`Playwright\Symfony\BrowserKit\PlaywrightClient`) directly from the container. The bundle registers it using the default
Playwright browser context, so tests can type-hint it in constructors or fetch it via `$this->getContainer()->get(...)`
without manual wiring.

## Limitations

- SPA navigation without full page reload may not produce a Playwright Response; the bridge returns 200 with available
  headers.
- Exact mapping of BrowserKit server params to headers/credentials is best-effort.
- Targeting elements relies on XPath from DomCrawler snapshot; if the DOM changes drastically before click, resolution
  may fail.

## Roadmap

- Track last main-frame navigation Response reliably via a small network tracker on Page.
- Add a PHPUnit trait for automatic artifacts (screenshot, HTML) on failure.
- Provide a Symfony Bundle for DI/autowiring in test env.
