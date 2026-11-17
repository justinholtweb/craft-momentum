<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Routing;

/**
 * Parses a section's template setting. `inertia:Pages/Article` names an Inertia
 * component; anything else is an ordinary Twig template and is left alone.
 */
final class InertiaTemplate
{
    public const string PREFIX = 'inertia:';

    /**
     * The component name, or null when the template isn't an Inertia one.
     */
    public static function component(?string $template): ?string
    {
        if ($template === null) {
            return null;
        }

        $template = trim($template);

        if (! str_starts_with(strtolower($template), self::PREFIX)) {
            return null;
        }

        $component = trim(substr($template, strlen(self::PREFIX)), " \t/");

        // Component names are paths like `Pages/Article` or `blog/show`: no dots,
        // no traversal, nothing a client-side resolver could misread.
        if ($component === '' || ! preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $component)) {
            return null;
        }

        return $component;
    }
}
