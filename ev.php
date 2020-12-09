<?php
declare(strict_types=1);

use PTS\SocketServer\Ev\Server;

require_once 'vendor/autoload.php';
$loop = new EvLoop;

$server = new Server;
$server->listen('tcp://127.0.0.1:3000');
$server->runLoop();