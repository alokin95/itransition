<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Force the test environment before Dotenv loads. Without this the Docker
// APP_ENV=dev bleeds into the test kernel and framework.test is never enabled,
// causing KernelTestCase to fail looking for test.service_container.
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';
putenv('APP_ENV=test');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
