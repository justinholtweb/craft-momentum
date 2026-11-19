<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use JustinHoltWeb\Momentum\Props\PropSerializer;

enum Tone: string
{
    case Warm = 'warm';
}

enum Plain
{
    case Thing;
}

it('passes scalars and null through', function (mixed $value) {
    expect(new PropSerializer()->value($value, 2))->toBe($value);
})->with([null, 'text', 42, 1.5, true, false]);

it('normalises dates, enums and stringables', function () {
    $s = new PropSerializer;

    expect($s->value(new DateTimeImmutable('2026-11-17 12:00:00', new DateTimeZone('UTC')), 2))->toBe('2026-11-17T12:00:00+00:00')
        ->and($s->value(Tone::Warm, 2))->toBe('warm')
        ->and($s->value(Plain::Thing, 2))->toBe('Thing')
        ->and($s->value(new HtmlString('<p>Hi</p>'), 2))->toBe('<p>Hi</p>');
});

it('recurses into arrays and JsonSerializable values', function () {
    $json = new class implements JsonSerializable
    {
        public function jsonSerialize(): array
        {
            return ['when' => new DateTimeImmutable('2026-01-01T00:00:00+00:00')];
        }
    };

    expect(new PropSerializer()->value(['a' => [1, 'b'], 'j' => $json], 2))
        ->toBe(['a' => [1, 'b'], 'j' => ['when' => '2026-01-01T00:00:00+00:00']]);
});

it('drops objects it cannot represent instead of leaking them', function () {
    $secret = new class
    {
        public string $password = 'hunter2';
    };

    expect(new PropSerializer()->value($secret, 2))->toBeNull();
});

it('serialises collections item by item, never through toArray()', function () {
    $item = new class implements Arrayable
    {
        public function toArray(): array
        {
            return ['safe' => true];
        }
    };

    expect(new PropSerializer()->value(collect([1, 'two', $item]), 2))->toBe([1, 'two', ['safe' => true]]);
});

it('renders Htmlable values as their HTML', function () {
    $html = new class implements Htmlable
    {
        public function toHtml(): string
        {
            return '<em>hi</em>';
        }
    };

    expect(new PropSerializer()->value($html, 2))->toBe('<em>hi</em>');
});

it('treats an empty relation value as an empty list', function () {
    expect(new PropSerializer()->relations(null, 2))->toBe([])
        ->and(new PropSerializer()->relations([], 2))->toBe([])
        ->and(new PropSerializer()->relations(collect(), 0))->toBe([]);
});
