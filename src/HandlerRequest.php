<?php
declare(strict_types=1);

namespace PTS\SocketServer;

use Generator;
use JetBrains\PhpStorm\Pure;
use JetBrains\PhpStorm\Immutable;
use PTS\SocketServer\Socket\ClientSocket;

class HandlerRequest
{
    public function handle(string $buffer, ClientSocket $socket)
    {
        $result = yield $this->action($buffer);
        return $result;
    }

    protected function action(string $buffer): Generator
    {
        yield 1;

        return 2;
    }
}