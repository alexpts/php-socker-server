<?php
declare(strict_types=1);

use PTS\SocketServer\HandlerRequest;
use PTS\SocketServer\Socket\ClientSocket;
use PTS\SocketServer\Socket\Server;

require_once '../vendor/autoload.php';

$server = new Server;
$handler = new HandlerRequest;
$server->setRequestHandler(static function (string $buffer, ClientSocket $socket) use ($handler) {
    //return $handler->handle($buffer, $socket);
});
$server->listen('0.0.0.0', 3001);
$server->start();