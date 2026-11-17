<?php

declare(strict_types=1);

use JustinHoltWeb\Momentum\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->rootView)->toBe('app');
});
