<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDERR, "Usage: php bin/hash-password.php 'your-password'\n");
    exit(1);
}

echo password_hash($pw, PASSWORD_DEFAULT), "\n";