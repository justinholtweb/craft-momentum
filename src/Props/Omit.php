<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props;

/**
 * Returned by a transformer to leave a field out of the props entirely
 * (rather than sending it as null).
 */
enum Omit
{
    case Field;
}
