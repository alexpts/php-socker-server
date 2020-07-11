<?php
declare(strict_types=1);

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// #### http worker ####
$server = new Worker('http://0.0.0.0:3000');

// 4 processes
$server->count = 1;

// Emitted when data received
$server->onMessage = function ($connection, $request) {
	//$request->get();
	//$request->post();
	//$request->header();
	//$request->cookie();
	//$requset->session();
	//$request->uri();
	//$request->path();
	//$request->method();

	// Send data to client
	$connection->send("Hello World");
};

// Run all workers
Worker::runAll();