<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Support\Money as MoneyHelper;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;
use Money\Money;

/**
 * Money: `{amount, currency, formatted}`. `amount` is a decimal string
 * (`"12.50"`) so no precision is lost in JSON; `formatted` uses the site's locale.
 */
final class MoneyTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if (! $value instanceof Money) {
            return null;
        }

        return [
            'amount' => MoneyHelper::toDecimal($value) ?: null,
            'currency' => $value->getCurrency()->getCode(),
            'formatted' => MoneyHelper::toString($value) ?: null,
        ];
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return '{ amount: string | null; currency: string; formatted: string | null } | null';
    }
}
