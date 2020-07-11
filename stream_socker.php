<?php


$flags = \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN;
$serverStreamSocket = stream_socket_server('tcp://127.0.0.1:3000', $errno, $errmsg, $flags);
if (!$serverStreamSocket) {
	die('can`t bind socket');
}

$socket = socket_import_stream($serverStreamSocket);
socket_set_option($socket, \SOL_SOCKET, \SO_KEEPALIVE, 1);
socket_set_option($socket, \SOL_TCP, \TCP_NODELAY, 1);
stream_set_blocking($serverStreamSocket, false);


$serverSocket = [(int)$serverStreamSocket];
$readAll = [
	(int)$serverStreamSocket => $serverStreamSocket
];
$writeAll = [];
$expectAll = [];

// event loop
while (true) {
	$read = $readAll;
	$write = $writeAll;
	$expect = $expectAll;
	$ret = stream_select($read, $write, $expect, 0, 1000000);

	foreach ($read as $socket) {
		$id = (int)$socket;
		if (in_array($id, $serverSocket, true)) {
			$connect = stream_socket_accept($socket, 0, $remote_address);
			stream_set_blocking($connect, 0);
			stream_set_read_buffer($connect, 0);
			$readAll[(int)$connect] = $connect;
		} else {
			$buffer = fread($socket, 256);
			try {
				$size =	@fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\nContent-Type: text/plain\r\n\r\nOK");
				if ($size === false) {
					if (!is_resource($socket) || feof($socket)) {
						fclose($socket);
						unset($writeAll[$id], $readAll[$id]);
					} else {
						$writeAll[$id] = $socket;
					}
				}
			} catch (\Error $error) {
				fclose($socket);
				unset($writeAll[$id], $readAll[$id]);
			} catch (Throwable $throwable) {
				fclose($socket);
				unset($writeAll[$id], $readAll[$id]);
			}
		}
		// timeout close socket
	}

	foreach ($write as $socket) {
		$a = 1;
	}

}