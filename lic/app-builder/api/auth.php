<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$input = read_json_body();
$key = trim($input['license_key'] ?? ($_POST['license_key'] ?? ''));

if ($key === '') {
    json_out(422, ['success' => false, 'message' => 'License key is required.']);
}

$res = Auth::validateAndLogin($key);
json_out($res['success'] ? 200 : 401, $res);
