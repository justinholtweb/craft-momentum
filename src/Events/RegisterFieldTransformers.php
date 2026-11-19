<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Events;

use JustinHoltWeb\Momentum\Props\Transformers\FieldTransformer;

/**
 * Fired once per request when Momentum builds its field transformer map. Add
 * or replace entries keyed by field class; subclasses of a key use it too,
 * unless they have their own entry.
 *
 * ```php
 * Event::listen(RegisterFieldTransformers::class, function ($event) {
 *     $event->transformers[MyField::class] = new MyFieldTransformer;
 * });
 * ```
 */
class RegisterFieldTransformers
{
    /**
     * @param  array<class-string, FieldTransformer>  $transformers
     */
    public function __construct(public array $transformers) {}
}
