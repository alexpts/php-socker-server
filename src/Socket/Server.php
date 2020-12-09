<?php
declare(strict_types=1);

namespace PTS\SocketServer\Socket;

use PTS\Events\EventEmitterTrait;
use PTS\SocketServer\ServerInterface;
use RuntimeException;
use Socket;

class Server implements ServerInterface
{

    use EventEmitterTrait;

    # https://github.com/freebsd/freebsd/blob/master/sys/sys/socket.h
    #protected const SO_REUSEPORT_LB = 65536;
    #protected const SO_REUSEPORT_LB2 = 0x00010000;

    /** @var Socket[] */
    protected array $sockets = [];
    /** @var ClientSocket[] */
    protected array $clients = [];
    public int $id = 0;

    /** @var callable|null $requestHandler*/
    protected $requestHandler;

    public function setRequestHandler(callable $requestHandler): static
    {
        $this->requestHandler = $requestHandler;
        return $this;
    }

    public function listen(string $address = '127.0.0.1', $port = 3000): static
    {
        $severSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($severSocket, SOL_SOCKET, SO_REUSEPORT, 1);
        #socket_set_option($severSocket, SOL_SOCKET, 65536, 1);
        #socket_set_option($severSocket, SOL_SOCKET, 0x00010000, 1);

        $isBind = socket_bind($severSocket, $address, $port);
        if (!$isBind) {
            throw new RuntimeException('Can`t bind socket to address');
        }

        $isListen = socket_listen($severSocket, 512);
        if (!$isListen) {
            throw new RuntimeException('Can`t listen socket');
        }

        socket_set_option($severSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        #socket_set_option($severSocket, SOL_TCP, TCP_NODELAY, 1);
        socket_set_nonblock($severSocket);

        $this->sockets['server'] = $severSocket;
        return $this;
    }

    protected function log(string $message, ...$params): void
    {
        echo sprintf($message, ...$params) . PHP_EOL;
    }

    public function start(): void
    {
        while (true) {
            $read = $this->sockets;
            $expect = $this->sockets;
            $write = null;
            socket_select($read, $write, $expect, null);

            foreach ($read as $name => $socket) {
                if ($name === 'server') {
                    $this->accept($socket);
                    continue;
                }

                $this->incomingMessage($this->clients[$name]);
            }
        }
    }

    protected function incomingMessage(ClientSocket $socket): void
    {
        // Если сокет полностью не вычитать, то он будет помечен снова на следующей итерации как активный
        // Фактически это приведет, что на 1 keep-alive запрос будет отправлено множество ответов по 1 на итерацию
        $buffer = $socket->read(1024);
        if ($buffer) {
            $socket->write("HTTP/1.1 200 OK\r\nContent-Length: 1\r\n\r\n1");
        }
    }

    protected function accept(Socket $serverSocket): void
    {
        set_error_handler(static function () {});
        $inSocket = socket_accept($serverSocket);
        restore_error_handler();

        if ($inSocket === false) {
            return;
        }

        // можно выставить опции - https://www.php.net/manual/ru/function.socket-get-option.php

        $clientSocket = new ClientSocket($inSocket);
        $clientSocket->once(ClientSocket::CLOSE, function(ClientSocket $clientSocket) {
            $this->emit('close', [$clientSocket]);
            unset($this->sockets[$clientSocket->id], $this->clients[$clientSocket->id]);
        });

        $clientSocket->on(ClientSocket::ON_READ, $this->requestHandler);

        $this->sockets[$clientSocket->id] = $inSocket;
        $this->clients[$clientSocket->id] = $clientSocket;

        $this->emit('connect', [$clientSocket]);
    }
}