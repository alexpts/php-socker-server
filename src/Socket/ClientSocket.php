<?php
declare(strict_types=1);

namespace PTS\SocketServer\Socket;

use PTS\Events\EventEmitterTrait;
use Socket;

class ClientSocket
{
    public const ON_READ = 'read';
    public const CLOSE = 'close';

    public int $id;

    use EventEmitterTrait;

    public function __construct(protected Socket $socket)
    {
        $this->id = spl_object_id($socket);
    }

    public function read(int $size = 4): ?string
    {
        //$error = socket_last_error($this->socket);
        $buffer = socket_read($this->socket, $size);
        if ($buffer === false || $buffer === '') {
            $this->close();
            return null;
        }

        $this->emit(self::ON_READ, [$buffer, $this]);
        return $buffer;
    }

    public function close(): void
    {
        $this->emit(self::CLOSE, [$this]);
        socket_close($this->socket);

        $this->listeners = [];
        unset($this->sockets);
    }

    public function write(string $buffer, int $size = null): int|bool
    {
        return @socket_write($this->socket, $buffer, $size);
    }
}