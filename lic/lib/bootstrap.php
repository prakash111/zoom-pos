<?php

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/landing_helper.php';
require_once __DIR__.'/invoice.php';
require_once __DIR__.'/orders.php';

// Global CORS headers for cross-origin API clients (Flutter Web, mobile, desktop)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-Server-Secret, X-Client-Platform, DNT, User-Agent, If-Modified-Since, Cache-Control, Range');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
