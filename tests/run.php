<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/AmountNormalizerTest.php';
require_once __DIR__ . '/PaymentIdentifierTest.php';
require_once __DIR__ . '/PaymentStatusMapperTest.php';
require_once __DIR__ . '/PaymentUpdateServiceTest.php';

$tests = [
    new AmountNormalizerTest(),
    new PaymentIdentifierTest(),
    new PaymentStatusMapperTest(),
    new PaymentUpdateServiceTest(),
];

$methodsRun = 0;

foreach ($tests as $test) {
    foreach (get_class_methods($test) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $test->{$method}();
        $methodsRun++;
        echo sprintf("PASS %s::%s\n", $test::class, $method);
    }
}

echo sprintf("\n%d tests passed.\n", $methodsRun);
