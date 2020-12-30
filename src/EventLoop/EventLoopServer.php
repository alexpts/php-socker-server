<?php

namespace PTS\SocketServer\EventLoop;

use Exception;
use PTS\SocketServer\ServerInterface;
use React\EventLoop\ExtEventLoop;
use React\EventLoop\ExtEvLoop;
use React\EventLoop\ExtUvLoop;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use RuntimeException;

class EventLoopServer implements ServerInterface
{
    protected LoopInterface $loop;

    public function __construct()
    {
        $this->loop = Factory::create();
        #$this->loop = new StreamSelectLoop;
        #$this->loop = new ExtEventLoop;
        #$this->loop = new ExtUvLoop;
        #$this->loop = new ExtEvLoop;
    }

    /**
     * @param string $address - tcp://0.0.0.0
     * @param int $port
     *
     * @return $this
     * @throws Exception
     */
    public function listen(string $address, int $port = 0): self
    {
        $address .=  $port ? ':'. $port : '';

        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $context = stream_context_create([
            'socket' => [
                'backlog' => 1024,
                'so_reuseport' => true,
                #'tcp_nodelay' => true,
            ]
        ]);
        $serverSocket = stream_socket_server($address, $errno, $errMsg, $flags, $context);
        if (!$serverSocket) {
            throw new RuntimeException('Can`t create socket');
        }

        stream_set_blocking($serverSocket, false);

        $this->loop->addReadStream($serverSocket, [$this, 'accept']);
        return $this;
    }

    public function accept($serverSocket): void
    {
        // нет балансировки коннектов, между процессами
        $clientSocket = null;
        set_error_handler(static function (...$args) {
            echo 'can`t accept: ' . print_r($args, 1) . PHP_EOL;
        });
        // concurrency process read all, but only first success
        $clientSocket = stream_socket_accept($serverSocket, 0, $remote_address);
        restore_error_handler();

        if ($clientSocket === false) {
            echo 'can`t accept';
            return;
        }

        #socket_set_option($clientSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        #stream_set_read_buffer($clientSocket, 1);
        #stream_set_write_buffer($clientSocket, 1);
        #stream_set_blocking($clientSocket, false);
        #stream_set_timeout($clientSocket, 10);

        $this->loop->addReadStream($clientSocket, [$this, 'onIncomingMessage']);
    }

    public function onIncomingMessage($clientSocket): void
    {
        // Если сокет полностью не вычитать, то он будет помечен снова на следующей итерации как активный
        // Фактически это приведет, что на 1 keep-alive запрос будет отправлено множество ответов по 1 на итерацию
        $buffer = fread($clientSocket, 1);
        if ($buffer === false || $buffer === '') {
            $this->loop->removeReadStream($clientSocket);
            @fclose($clientSocket);
            return;
        }

        @fwrite($clientSocket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 1\r\n\r\n1");
    }

    public function start(): void
    {
        $this->loop->run();
    }
}