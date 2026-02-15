<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Util;

use Playwright\Page\PageInterface;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;

/**
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class FormInteractor
{
    public static function fill(PageInterface $page, Form $form): void
    {
        foreach ($form->all() as $field) {
            $node = self::getNodeFromField($field);
            $xpath = XPathHelper::buildXPath($node);
            $locator = $page->locator('xpath='.$xpath);

            $type = $node->getAttribute('type') ?: '';

            if ('select' === $node->tagName) {
                $values = $field->getValue();
                $values = is_array($values) ? $values : [$values];
                $locator->selectOption($values);
                continue;
            }

            if ('checkbox' === $type) {
                $shouldBeChecked = (bool) $field->getValue();
                $shouldBeChecked ? $locator->check() : $locator->uncheck();
                continue;
            }

            if ('radio' === $type) {
                if (null !== $field->getValue()) {
                    $locator->check();
                }
                continue;
            }

            if ('file' === $type) {
                $value = $field->getValue();
                if ($value) {
                    $locator->setInputFiles($value);
                }
                continue;
            }

            // Default: text-like inputs and textarea
            $value = $field->getValue();
            if (\is_array($value)) {
                $value = implode('', $value);
            }
            $locator->fill((string) $value);
        }
    }

    private static function getNodeFromField(FormField $field): \DOMElement
    {
        $reflection = new \ReflectionClass($field);
        $property = $reflection->getProperty('node');
        $node = $property->getValue($field);

        \assert($node instanceof \DOMElement);

        return $node;
    }
}
