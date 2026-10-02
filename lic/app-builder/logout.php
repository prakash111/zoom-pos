<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';

Auth::logout();
header('Location: index.php?msg=' . urlencode('Logged out successfully.'));
exit;
