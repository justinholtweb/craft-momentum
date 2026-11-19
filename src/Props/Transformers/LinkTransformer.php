<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\LinkData;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;
use Throwable;

/**
 * Link: `{type, url, label, target, title, rel, download, element}`. Links to
 * elements use the element's live URL (a disabled target gives no URL) and
 * carry `{id, title, url}` of the target as `element`.
 */
final class LinkTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if (! $value instanceof LinkData) {
            return null;
        }

        try {
            $element = $value->getElement();
            $url = $value->getUrl(anyStatus: false);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return [
            'type' => $value->getType(),
            'url' => $url === '' ? null : $url,
            'label' => $value->getLabel(),
            'target' => $value->target,
            'title' => $value->title,
            'rel' => $value->rel,
            'download' => $value->download,
            'element' => $element === null ? null : $serializer->one($element, 0),
        ];
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return '{ type: string; url: string | null; label: string | null; target: string | null; title: string | null; rel: string | null; download: boolean; element: MomentumElementRef | number | null } | null';
    }
}
