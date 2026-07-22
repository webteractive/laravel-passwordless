<?php

use Webteractive\Passwordless\Strategies\LoginCode\CodeGenerator;

it('produces digits-only codes of requested length', function () {
    $g = new CodeGenerator;
    $code = $g->generate(6);
    expect($code)->toMatch('/^\d{6}$/');
});

it('clamps length between 6 and 10', function () {
    $g = new CodeGenerator;
    expect(strlen($g->generate(4)))->toBe(6);
    expect(strlen($g->generate(20)))->toBe(10);
});

it('preserves leading zeros as a string', function () {
    $g = new CodeGenerator;
    $codes = [];
    for ($i = 0; $i < 200; $i++) {
        $codes[] = $g->generate(6);
    }
    foreach ($codes as $c) {
        expect($c)->toBeString();
        expect(strlen($c))->toBe(6);
    }
    // assert leading-zero preservation is possible (string never converted to int)
    $g2 = new CodeGenerator;
    $hash = hash('sha256', '012345');
    expect($hash)->toBe(hash('sha256', $g2->normalize(' 012345 ')));
});

it('strips whitespace from input', function () {
    $g = new CodeGenerator;
    expect($g->normalize(' 12 34 56 '))->toBe('123456');
});
