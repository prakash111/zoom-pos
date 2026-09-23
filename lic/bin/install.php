<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only. Use setup.php from a browser if you have no shell access.\n");
}

require __DIR__.'/../lib/bootstrap.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to database '".DB_NAME."': ".$e->getMessage()."\n");
    fwrite(STDERR, "Create the database and check config/config.php, then re-run.\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__.'/../database/schema.sql')))) as $stmt) {
    try {
        $pdo->exec($stmt);
    } catch (Throwable $e) {
        // Idempotent upgrade statements (e.g. "duplicate column") are expected.
        if (! preg_match('/duplicate|exists/i', $e->getMessage())) {
            throw $e;
        }
    }
}

@mkdir(__DIR__.'/../storage/packages', 0770, true);

echo "Schema imported into '".DB_NAME."'. Tables: ".implode(', ', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN))."\n";