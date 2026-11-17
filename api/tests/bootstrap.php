<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// PDFs gravados pelos testes (app.storage_dir em when@test): começa sempre vazio
(new Symfony\Component\Filesystem\Filesystem())->remove(dirname(__DIR__).'/var/storage_test');
