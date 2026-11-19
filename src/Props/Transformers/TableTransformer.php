<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Table;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Table: a list of rows keyed by column handle. */
final class TableTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if (! is_iterable($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                // Rows also carry `col1`-style keys alongside the handles; keep the handles.
                $row = array_filter($row, fn ($key) => is_string($key) && ! preg_match('/^col\d+$/', $key), ARRAY_FILTER_USE_KEY);
                $rows[] = $serializer->value($row, $depth);
            }
        }

        return $rows;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        $columns = [];

        if ($field instanceof Table) {
            foreach ($field->columns as $column) {
                if ($column['handle'] !== '') {
                    $columns[] = TypeScriptWriter::key($column['handle']).': '.self::cellType($column['type']);
                }
            }
        }

        return $columns === [] ? 'Array<Record<string, unknown>>' : 'Array<{ '.implode('; ', $columns).' }>';
    }

    private static function cellType(string $type): string
    {
        return match (true) {
            str_contains($type, 'number') => 'number | null',
            str_contains($type, 'lightswitch'), str_contains($type, 'checkbox') => 'boolean',
            default => 'unknown',
        };
    }
}
