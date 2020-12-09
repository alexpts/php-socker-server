<?php

namespace PTS\SocketServer\EventLoop;

use Exception;
use PTS\SocketServer\ServerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use RuntimeException;

class SelectLoop implements ServerInterface
{
    protected LoopInterface $loop;

    public function __construct()
    {
        $this->loop = new StreamSelectLoop;
    }

    /**
     * @param string $address - tcp://0.0.0.0
     * @param int $port
     *
     * @return $this
     * @throws Exception
     */
    public function listen(string $address, int $port = 0): static
    {
        $address .=  $port ? ':'. $port : '';

        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $serverSocket = stream_socket_server($address, $errno, $errMsg, $flags);
        if (!$serverSocket) {
            throw new RuntimeException('Can`t create socket');
        }

        $socket = socket_import_stream($serverSocket);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        #socket_set_option($serverSocket, SOL_TCP, TCP_NODELAY, 1);
        stream_set_blocking($serverSocket, false);

        $this->loop->addReadStream($serverSocket, [$this, 'accept']);
        return $this;
    }

    public function accept($serverSocket): void
    {
        // нет балансировки коннектов, между процессами
        $clientSocket = null;
        set_error_handler(static function () {});
        // concurrency process read all, but only first success
        $clientSocket = stream_socket_accept($serverSocket, 0, $remote_address);
        restore_error_handler();

        if ($clientSocket === false) {
            return;
        }

        #socket_set_option($clientSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        stream_set_read_buffer($clientSocket, 1024);
        stream_set_write_buffer($clientSocket, 1024);
        stream_set_blocking($clientSocket, false);
        stream_set_timeout($clientSocket, 3);

        $this->loop->addReadStream($clientSocket, [$this, 'onIncomingMessage']);
    }

    public function onIncomingMessage($clientSocket)
    {
        // Если сокет полностью не вычитать, то он будет помечен снова на следующей итерации как активный
        // Фактически это приведет, что на 1 keep-alive запрос будет отправлено множество ответов по 1 на итерацию
        $buffer = fread($clientSocket, 1);
        if ($buffer === false || $buffer === '') {
            $this->loop->removeReadStream($clientSocket);
            fclose($clientSocket);
            return;
        }

        $size = @fwrite($clientSocket, "HTTP/1.1 200 OK\r\nConnection: Keep-Alive\r\nContent-Length: 1\r\n\r\n1");
    }

    public function start(): void
    {
        $this->loop->run();
    }
}