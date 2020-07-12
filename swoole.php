<?php

use Swoole\Server as Server;

$server = new Server("127.0.0.1", 3000, SWOOLE_BASE, SWOOLE_SOCK_TCP);

$server->set([
	'worker_num' => 6, // The number of worker processes
	#'daemonize' => true, // Whether start as a daemon process
	'backlog' => 128, // TCP backlog connection number

	'open_http_protocol' => false,
	'open_http2_protocol' => false,
	'open_websocket_protocol' => false,
	'open_mqtt_protocol' => false,
	'open_length_check' => false,
]);

$server->on('receive', function (Server $server, int $fd, int $reactor_id, string $data) {
	//echo $data;
	//$size = @fwrite($fd, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\n\r\nok");
	$server->send($fd, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\n\r\nok");
});

$server->start();