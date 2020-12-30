<?php
declare(strict_types=1);

namespace PTS\SocketServer;

interface ServerInterface
{
    public function listen(string $address, int $port = 3000): self;
    public function start(): void;
}