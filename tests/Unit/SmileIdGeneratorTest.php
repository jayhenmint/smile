<?php

use App\Support\SmileIdGenerator;

test('SMILE IDs use the required alphabet and have no personal-data inputs', function () {
    $generator = new SmileIdGenerator;
    $ids = [];

    for ($sample = 0; $sample < 200; $sample++) {
        $id = $generator->generate();
        expect($id)->toMatch('/^SM-[A-HJ-NP-Z2-9]{8}$/');
        expect($id)->not->toBe('001234567890');
        $ids[] = $id;
    }

    expect(array_unique($ids))->toHaveCount(200);
    expect((new ReflectionMethod(SmileIdGenerator::class, 'generate'))->getNumberOfParameters())->toBe(0);
});
