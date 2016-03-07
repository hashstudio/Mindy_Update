<?php

/*
 * Index.php in the folder update
 */

require('src/Mindy/Update/Update.php');

use Mindy\Update\Update;

$update = new Update([
    'repoUrl' => 'http://localhost:8000/api/v1/package',
    'installDir' => __DIR__ . '/temp/install',
    'downloadDir' => __DIR__ . '/temp/download',
]);

$name = 'Pages';

assert($update->update($name, 0.1) == true);
