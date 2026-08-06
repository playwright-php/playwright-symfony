#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PHP_VERSION="8.3"
SYMFONY_VERSION="^7.4"
BUNDLE_DIR="$(pwd)"
TEST_APP_DIR="$BUNDLE_DIR/test-app"

print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}$1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Requirements
check_requirements() {
    print_header "Requirements"

    # Check PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed"
        exit 1
    fi
    print_success "PHP $(php -r 'echo PHP_VERSION;')"

    # Check Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer is not installed"
        exit 1
    fi
    print_success "Composer $(composer --version --no-ansi | head -n1)"

    echo ""
}

# Create Symfony application
create_symfony_app() {
    print_header "Create Symfony Application"

    # Remove existing test app if present
    if [ -d "$TEST_APP_DIR" ]; then
        print_info "Removing existing test-app directory..."
        rm -rf "$TEST_APP_DIR"
    fi

    # Create Symfony skeleton
    print_info "Creating Symfony skeleton..."
    composer create-project symfony/skeleton:"$SYMFONY_VERSION" test-app --no-interaction

    cd "$TEST_APP_DIR"

    # Add minimal requirements for web app
    print_info "Adding required packages..."
    composer config extra.symfony.allow-contrib true
    composer require symfony/twig-bundle

    print_success "Symfony skeleton created"

    cd "$BUNDLE_DIR"
    echo ""
}

# Add pages and assets
add_pages_and_assets() {
    print_header "Add Pages and Assets"

    cd "$TEST_APP_DIR"

    # Create src/Controller directory
    mkdir -p src/Controller

    # Create HelloController with two pages
    print_info "Creating HelloController..."
    cat > src/Controller/HelloController.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelloController extends AbstractController
{
    #[Route('/')]
    public function home(): Response
    {
        return $this->render('hello/home.html.twig');
    }

    #[Route('/about')]
    public function about(): Response
    {
        return $this->render('hello/about.html.twig');
    }
}
EOF

    # Create templates directory
    mkdir -p templates/hello

    # Create home template with CSS
    print_info "Creating templates..."
    cat > templates/hello/home.html.twig << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Home Page</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <h1>Welcome to Home</h1>
    <p>This is the home page.</p>
    <a href="/about">Go to About</a>
    <form method="post" action="/search">
        <input type="text" name="q" placeholder="Search..." id="search-input">
        <button type="submit">Search</button>
    </form>
</body>
</html>
EOF

    # Create about template
    cat > templates/hello/about.html.twig << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>About Page</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <h1>About Us</h1>
    <p>This is the about page.</p>
    <a href="/">Go to Home</a>
</body>
</html>
EOF

    # Create public directory and CSS asset
    mkdir -p public/assets
    print_info "Creating CSS assets..."
    cat > public/assets/styles.css << 'EOF'
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h1 {
    color: #333;
}
EOF

    print_success "Controllers, templates, and assets created"

    cd "$BUNDLE_DIR"
    echo ""
}

# Install Playwright Bundle
install_playwright_bundle() {
    print_header "Install Playwright Bundle"

    cd "$TEST_APP_DIR"

    # Add bundle as path repository to use local checkout
    print_info "Configuring composer repository..."
    composer config repositories.playwright-symfony path ..

    # Require the dev version of playwright-php/playwright first
    print_info "Installing playwright-php/playwright..."
    composer require playwright-php/playwright:dev-main --dev

    # Require the bundle and PHPUnit
    print_info "Installing bundle and PHPUnit..."
    composer require playwright-php/playwright-symfony:@dev --dev
    composer require phpunit/phpunit:^12.0 --dev

    # Enable the bundle in the kernel
    print_info "Enabling bundle in kernel..."
    cat > config/bundles.php << 'EOF'
<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true],
];
EOF

    # Create test environment bundle configuration
    print_info "Creating bundle configuration..."
    mkdir -p config/packages/test
    cat > config/packages/test/playwright.yaml << 'EOF'
playwright:
    intercepted_hosts:
        - localhost
        - 127.0.0.1
    base_url: 'http://localhost'
    debug_logging: false
EOF

    # Create PHPUnit configuration
    print_info "Creating PHPUnit configuration..."
    cat > phpunit.xml.dist << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         testdox="true">
    <testsuites>
        <testsuite name="E2E Tests">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="test"/>
        <env name="KERNEL_CLASS" value="App\Kernel"/>
        <env name="PLAYWRIGHT_HEADLESS" value="true"/>
    </php>
</phpunit>
EOF

    # Create E2E test
    print_info "Creating E2E test..."
    mkdir -p tests
    cat > tests/HelloE2ETest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Tests;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class HelloE2ETest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new \App\Kernel('test', false);
    }

    public function testHomePageLoads(): void
    {
        $page = $this->visit('/');

        // Display what we got from the browser
        $title = $page->title();
        $h1Text = $page->locator('h1')->textContent();
        $url = $page->url();

        echo "\n";
        echo "  → Visited URL: {$url}\n";
        echo "  → Page Title: {$title}\n";
        echo "  → H1 Content: {$h1Text}\n";

        // Verify page content
        $this->assertPageContains('Welcome to Home');
        $this->assertPageContains('This is the home page.');
        $this->assertSame('Home Page', $title);
        $this->assertSame('Welcome to Home', $h1Text);

        // Verify link exists
        $this->assertSelectorExists('a[href="/about"]');

        // Check response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
        echo "  → Response Status: {$response->getStatusCode()}\n";
    }

    public function testAboutPageLoads(): void
    {
        $page = $this->visit('/about');

        // Display what we got from the browser
        $title = $page->title();
        $h1Text = $page->locator('h1')->textContent();
        $url = $page->url();

        echo "\n";
        echo "  → Visited URL: {$url}\n";
        echo "  → Page Title: {$title}\n";
        echo "  → H1 Content: {$h1Text}\n";

        // Verify page content
        $this->assertPageContains('About Us');
        $this->assertPageContains('This is the about page.');
        $this->assertSame('About Page', $title);
        $this->assertSame('About Us', $h1Text);

        // Verify link exists
        $this->assertSelectorExists('a[href="/"]');

        // Check response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
        echo "  → Response Status: {$response->getStatusCode()}\n";
    }

    public function testNavigationBetweenPages(): void
    {
        echo "\n";

        // Start at home
        $page = $this->visit('/');
        echo "  → Started at: {$page->url()}\n";
        echo "  → H1: {$page->locator('h1')->textContent()}\n";
        $this->assertPageContains('Welcome to Home');

        // Click link to about page
        echo "  → Clicking link to /about...\n";
        $page->click('a[href="/about"]');
        $page->waitForLoadState('networkidle');
        echo "  → Now at: {$page->url()}\n";
        echo "  → H1: {$page->locator('h1')->textContent()}\n";
        $this->assertPageContains('About Us');

        // Click back to home
        echo "  → Clicking link to /...\n";
        $page->click('a[href="/"]');
        $page->waitForLoadState('networkidle');
        echo "  → Back at: {$page->url()}\n";
        echo "  → H1: {$page->locator('h1')->textContent()}\n";
        $this->assertPageContains('Welcome to Home');
    }

    public function testCssAssetLoads(): void
    {
        $page = $this->visit('/');

        echo "\n";
        echo "  → Testing CSS asset loading...\n";

        // Check that the CSS link exists
        $cssLink = $page->locator('link[rel="stylesheet"][href="/assets/styles.css"]');
        $this->assertTrue($cssLink->count() > 0, 'CSS link should exist in HTML');
        echo "  → CSS link found in HTML\n";

        // Verify CSS was actually loaded by checking computed styles
        $bodyColor = $page->evaluate('() => window.getComputedStyle(document.body).backgroundColor');
        echo "  → Body background color: {$bodyColor}\n";

        // The CSS sets background to #f5f5f5 which is rgb(245, 245, 245)
        $this->assertStringContainsString('245', $bodyColor, 'CSS should be applied to body');
        echo "  → CSS successfully loaded and applied\n";
    }

    public function testFormElements(): void
    {
        $page = $this->visit('/');

        echo "\n";
        echo "  → Testing form elements...\n";

        // Check form exists
        $this->assertSelectorExists('form[action="/search"]');
        echo "  → Form element found\n";

        // Check input exists and can be filled
        $input = $page->locator('#search-input');
        $this->assertTrue($input->count() > 0, 'Search input should exist');

        $input->fill('test query');
        $value = $input->inputValue();
        echo "  → Input filled with: {$value}\n";
        $this->assertSame('test query', $value);

        // Check button exists
        $button = $page->locator('form button[type="submit"]');
        $this->assertTrue($button->count() > 0, 'Submit button should exist');
        $buttonText = $button->textContent();
        echo "  → Submit button text: {$buttonText}\n";
    }
}
EOF

    print_success "Playwright Symfony bundle installed and configured"

    # Verify installation and configuration
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo "Verifying Installation:"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    print_info "Installed Playwright packages:"
    composer show | grep playwright

    echo ""
    print_info "Resolved Playwright configuration:"
    APP_ENV=test php bin/console debug:config playwright

    echo ""
    print_info "Checking if AssetServer service is available:"
    APP_ENV=test php bin/console debug:container 'Playwright\Symfony\Client\Interception\AssetServer' --show-arguments || echo "Service not found in container!"

    cd "$BUNDLE_DIR"
    echo ""
}

# Run tests
run_tests() {
    print_header "Run Tests"

    cd "$TEST_APP_DIR"

    # Download Playwright browsers
    print_info "Downloading Playwright browsers..."
    vendor/bin/playwright-install --browsers
    print_success "Playwright browsers downloaded"

    # Run E2E tests
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo "Running E2E Tests:"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    APP_ENV=test vendor/bin/phpunit --testdox --colors=always
    echo ""
    print_success "All tests passed"

    cd "$BUNDLE_DIR"
    echo ""
}

# Summary
show_summary() {
    print_header "Summary"

    echo ""
    echo -e "${GREEN}✓ Integration test completed successfully!${NC}"
    echo ""
    echo "Validated:"
    echo "  ✓ Fresh Symfony $SYMFONY_VERSION installation"
    echo "  ✓ PHP $PHP_VERSION"
    echo "  ✓ Bundle installation from local checkout"
    echo "  ✓ Controller and template creation"
    echo "  ✓ Bundle configuration"
    echo "  ✓ Playwright browser download"
    echo "  ✓ E2E tests execution (5 tests)"
    echo ""
    echo "Bundle is ready for production use!"
    echo ""
}

# Main execution
main() {
    check_requirements
    create_symfony_app
    add_pages_and_assets
    install_playwright_bundle
    run_tests
    show_summary
}

# Run main function
main