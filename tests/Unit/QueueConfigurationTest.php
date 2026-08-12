<?php

it('mantiene retry after por encima del timeout de generacion', function () {
    $queueFile = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'queue.php';
    $queue = require $queueFile;

    expect($queue['connections']['database']['retry_after'])->toBeGreaterThan(120)
        ->and($queue['connections']['beanstalkd']['retry_after'])->toBeGreaterThan(120)
        ->and($queue['connections']['redis']['retry_after'])->toBeGreaterThan(120);
});
