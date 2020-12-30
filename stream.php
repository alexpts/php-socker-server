<?php
declare(strict_types=1);

use PTS\SocketServer\EventLoop\EventLoopServer;

require_once 'vendor/autoload.php';

$server = new EventLoopServer;
$server->listen('tcp://127.0.0.1', 3001);
$server->start();