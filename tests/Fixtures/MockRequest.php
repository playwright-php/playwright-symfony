<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures;

use Playwright\Network\RequestInterface;

/**
 * Mock implementation of RequestInterface for testing purposes.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final readonly class MockRequest implements RequestInterface
{
    public function __construct(
        private string $url,
        private string $method = 'GET',
        private array $headers = [],
        private ?string $postData = null,
        private string $resourceType = 'document',
    ) {
    }

    public function url(): string
    {
        return $this->url;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the request headers as an associative array.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function postData(): ?string
    {
        return $this->postData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function postDataJSON(): ?array
    {
        if (null === $this->postData) {
            return null;
        }

        if (!json_validate($this->postData)) {
            return null;
        }

        return json_decode($this->postData, true, 512, JSON_THROW_ON_ERROR);
    }

    public function resourceType(): string
    {
        return $this->resourceType;
    }
}
