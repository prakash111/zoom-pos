<?php
header_remove('X-Frame-Options');
header("Content-Security-Policy: frame-ancestors *;");
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
exit;
