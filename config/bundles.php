<?php

$coreFile = __DIR__ . '/bundles.core.php';
$localFile = __DIR__ . '/bundles.local.php';

$core = file_exists($coreFile) ? require $coreFile : [];
$local = file_exists($localFile) ? require $localFile : [];

if (!is_array($core)) {
    $core = [];
}

if (!is_array($local)) {
    $local = [];
}

return array_merge($core, $local);
