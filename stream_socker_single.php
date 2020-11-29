<?php

require_once 'src/Server/Server.php';

$server = new Server;
$server->listen('tcp://127.0.0.1:3000');
$server->runLoop();