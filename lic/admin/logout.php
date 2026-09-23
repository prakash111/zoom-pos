<?php

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');