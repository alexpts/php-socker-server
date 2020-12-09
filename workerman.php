<?php
declare(strict_types=1);

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// #### http worker ####
$server = new Worker('http://127.0.0.1:3001');

// 4 processes
$server->count = 1;
#$server->reusePort = true;

// Emitted when data received
$server->onMessage = function (TcpConnection $connection, Request $request) {
    //$request->get();
    //$request->post();
    //$request->header();
    //$request->cookie();
    //$requset->session();
    //$request->uri();
    //$request->path();
    //$request->method();

    // Send data to client
    #$all = $request->header();
	$connection->send("1");
};

// Run all workers
Worker::runAll();