<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

requireLogin();

header('Location: ' . currentUserHome());
exit;
