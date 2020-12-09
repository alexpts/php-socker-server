<?php
declare(strict_types=1);

use PTS\SocketServer\Swoole\Server;

require_once 'vendor/autoload.php';

$server = new Server;
$server->listen("127.0.0.1", 3001);
$server->start();