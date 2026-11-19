<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props;

/**
 * Which fields are left out of props, and which are sent under another name.
 *
 * Rules name a field handle, optionally qualified by the entry type (or volume,
 * for assets) it's on: `internalNotes` applies everywhere, `article.internalNotes`
 * only on `article` entries. A qualified rule wins over a bare one.
 */
final class FieldRules
{
    /**
     * @param  list<string>  $exclude  `handle` or `qualifier.handle`
     * @param  array<string, string>  $rename  `handle` or `qualifier.handle` → prop name
     * @param  list<string>  $defer  `handle` or `qualifier.handle` (Pro: sent as deferred props)
     */
    public function __construct(
        private readonly array $exclude = [],
        private readonly array $rename = [],
        private readonly array $defer = [],
    ) {}

    public function excludes(string $handle, ?string $qualifier): bool
    {
        return $this->matches($this->exclude, $handle, $qualifier);
    }

    public function defers(string $handle, ?string $qualifier): bool
    {
        return $this->matches($this->defer, $handle, $qualifier);
    }

    public function propName(string $handle, ?string $qualifier): string
    {
        if ($qualifier !== null && isset($this->rename["{$qualifier}.{$handle}"])) {
            return $this->rename["{$qualifier}.{$handle}"];
        }

        return $this->rename[$handle] ?? $handle;
    }

    public function hasDeferred(): bool
    {
        return $this->defer !== [];
    }

    /**
     * Parses `handle: newName` lines. Invalid lines are skipped (the settings
     * page validates them before they're saved).
     *
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    public static function parseRenames(array $lines): array
    {
        $map = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?)\s*[:=]>?\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $line, $m)) {
                $map[$m[1]] = $m[2];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $rules
     */
    private function matches(array $rules, string $handle, ?string $qualifier): bool
    {
        return in_array($handle, $rules, true)
            || ($qualifier !== null && in_array("{$qualifier}.{$handle}", $rules, true));
    }
}
