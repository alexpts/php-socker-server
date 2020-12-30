<?php
declare(strict_types=1);

use PTS\SocketServer\StreamSocket\Server;

require_once '../vendor/autoload.php';

$server = new Server;
$server->listen('tcp://127.0.0.1:3001');
$server->start();