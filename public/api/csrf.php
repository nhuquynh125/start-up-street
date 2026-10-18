<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
json_response(['ok'=>true,'csrf_token'=>csrf_token()]);
