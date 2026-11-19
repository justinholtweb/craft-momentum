<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use Illuminate\Contracts\Support\Htmlable;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;
use Stringable;

/**
 * Rich text (CKEditor, Markdown): `{html, chunks}`.
 *
 * `html` is the field's HTML with reference tags parsed, leaving out any
 * nested entries. `chunks` is the same content in order, split where CKEditor
 * nests an entry: `{type: 'markup', html}` and `{type: 'entry', entry}`, so a
 * front end can render nested entries with its own components. Rendering the
 * HTML is your front end's job (`v-html`, `dangerouslySetInnerHTML`, `{@html}`):
 * it's the editor's HTML, purified by the field's own settings.
 */
final class HtmlTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // CKEditor's FieldData is iterable chunks; other HTML values are one markup chunk.
        if (is_object($value) && method_exists($value, 'getChunks')) {
            $chunks = [];
            $html = '';

            foreach ($value->getChunks() as $chunk) {
                if (method_exists($chunk, 'getEntry')) {
                    $entry = $chunk->getEntry();

                    if ($entry instanceof Entry) {
                        $chunks[] = ['type' => 'entry', 'entry' => $serializer->one($entry, $depth)];
                    }

                    continue;
                }

                $markup = method_exists($chunk, 'getHtml') ? (string) $chunk->getHtml() : (string) $chunk;
                $html .= $markup;
                $chunks[] = ['type' => 'markup', 'html' => $markup];
            }

            return ['html' => $html, 'chunks' => $chunks];
        }

        $html = match (true) {
            $value instanceof Htmlable => $value->toHtml(),
            $value instanceof Stringable, is_string($value) => (string) $value,
            default => null,
        };

        return $html === null ? null : ['html' => $html, 'chunks' => [['type' => 'markup', 'html' => $html]]];
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return 'MomentumRichText | null';
    }
}
