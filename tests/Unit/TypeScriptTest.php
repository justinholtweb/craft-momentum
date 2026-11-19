<?php

declare(strict_types=1);

use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

it('quotes literals as JSON strings', function () {
    expect(TypeScriptWriter::literal('Pages/Article'))->toBe('"Pages/Article"')
        ->and(TypeScriptWriter::literal('it"s'))->toBe('"it\"s"')
        ->and(TypeScriptWriter::literal('</script>'))->toBe('"</script>"');
});

it('leaves identifier keys bare and quotes the rest', function () {
    expect(TypeScriptWriter::key('summary'))->toBe('summary')
        ->and(TypeScriptWriter::key('$meta'))->toBe('$meta')
        ->and(TypeScriptWriter::key('2col'))->toBe('"2col"')
        ->and(TypeScriptWriter::key('with-dash'))->toBe('"with-dash"');
});
