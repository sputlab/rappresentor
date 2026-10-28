<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

Auth::logout();
Helper::setFlash('info', 'Ti sei disconnesso correttamente.');
header('Location: login.php');
exit;
