<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Page\Config;

/**
 * Keep the `<html lang>` attribute present after the inter-request state reset.
 *
 * Page\Config sets html.lang from the locale in its constructor, but this module's reset.json clears
 * Page\Config::elements between requests (so stale body classes/attributes never leak on a persistent
 * worker). Because the constructor never runs again, html.lang would be permanently absent from the
 * second request onward — leaving document.documentElement.lang = "", which breaks locale-aware JS such
 * as Intl.NumberFormat() (e.g. price sliders). This re-injects it whenever it has been cleared.
 */
class EnsureHtmlLangAttribute
{
    public function __construct(private readonly ResolverInterface $localeResolver) {}

    /**
     * @param Config $subject
     * @param array $result
     * @param string $elementType
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetElementAttributes(Config $subject, array $result, string $elementType): array
    {
        if ($elementType === Config::ELEMENT_TYPE_HTML && !isset($result[Config::HTML_ATTRIBUTE_LANG])) {
            $locale = $this->localeResolver->getLocale();
            if ($locale) {
                $result[Config::HTML_ATTRIBUTE_LANG] = strstr($locale, '_', true) ?: $locale;
            }
        }

        return $result;
    }
}
