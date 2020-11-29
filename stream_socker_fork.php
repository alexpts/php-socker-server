<?php

require_once 'src/Server/Server.php';

$server = new Server;
$server->listen('tcp://127.0.0.1:3000');

$countWorker = 3;

for ($i = 0; $i <= $countWorker; $i++) {
	$pid = pcntl_fork();

	if ($pid === 0) {
		echo 'worker: ' . posix_getpid() . PHP_EOL;
		$server->pid = posix_getpid();
		$server->i = $i;
		$server->total = $countWorker;
		$server->runLoop();
	}
}

sleep(999999999999);
