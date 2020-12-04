<?php

require_once 'src/Server/SocketServer.php';

//$pid = pcntl_fork();

$server = new SocketServer;
$server->listen('0.0.0.0', 3001);
$server->runLoop();