<?php

$tcp = uv_tcp_init();
uv_tcp_bind($tcp, uv_ip4_addr('0.0.0.0', 3000));
//$loop = uv_default_loop();
//uv_fs_poll_init($loop);

uv_listen($tcp, 100, static function($server) {
	$client = uv_tcp_init();
	uv_accept($server, $client);

	//	uv_fs_read($loop, $client, 0, 1, static function($socket, int $nread, $buffer) {
	//		var_dump($buffer);
	//		var_dump($nread);
	//		uv_close($socket);
	//	});
	uv_read_start($client, static function($socket, $nread, $buffer){
		uv_write($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\n\r\nok", static function() {});
	});
});

$worker = 6;
while ($worker--) {
	$pid = pcntl_fork();
	if ($pid === 0) {
		uv_run();
	}
}


sleep(9999999999);