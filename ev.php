<?php

$loop = new EvLoop;

require_once 'src/Server/EvServer.php';

$server = new EvServer;
$server->listen('tcp://127.0.0.1:3000');
$server->runLoop();