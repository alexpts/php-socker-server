<?php
declare(strict_types=1);

namespace PTS\SocketServer\Ev;

use EvIo;
use EvLoop;
use RuntimeException;

class Server
{
    /** @var resource[] */
    protected array $sockets = [];
    protected EvLoop $loop;

    /** @var EvIo[] */
    protected array $serverEvents;
    /** @var EvIo[] */
    protected array $clientEvents;

    public function __construct()
    {
        //EV::BACKEND_POLL;
        //EV::BACKEND_KQUEUE;
        //EV::BACKEND_SELECT;
        $this->loop = new EvLoop;

        $event = $this->loop->idle(function ($watcher, $revent) {
            $this->log('idle event');
        });
        $event->keepalive(true);

        $event = $this->loop->timer(10, 10, function ($watcher, $revent) {
            $this->log('timer event');
        });
        #$event->keepalive(true);
    }

    public function listen(string $address): self
    {
        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $serverSocket = stream_socket_server($address, $errno, $errMsg, $flags);
        if (!$serverSocket) {
            throw new RuntimeException('Can`t create socket');
        }

        $socket = socket_import_stream($serverSocket);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        stream_set_blocking($serverSocket, false);

        /** @var EvIo $event */
        $event = $this->loop->io($serverSocket, Ev::READ, $this->acceptCallback($serverSocket));
        #$event->keepalive(true);

        $this->serverEvents[(int)$serverSocket] = $event;
        return $this;
    }

    protected function acceptCallback($socket): callable
    {
        return function (object $watcher, int $flags) use ($socket) {
            #set_error_handler(static function(){});
            $client =
                stream_socket_accept($socket, 0,
                    $remote_address); // concurrency process read all, but only first success
            #\restore_error_handler();
            if ($client === false) {
                return;
            }

            stream_set_read_buffer($client, 1024);
            stream_set_write_buffer($client, 0);
            stream_set_blocking($client, false);
            stream_set_timeout($client, 1);

            /** @var EvIo $event */
            $event = $this->loop->io($client, Ev::READ, $this->readyReadCallback($client));
            $this->clientEvents[(int)$client] = $event;
            $this->log('%d: connect', (int)$client);
            $data = fread($socket, 1024);
            $a = 1;
        };
    }

    protected function readyReadCallback($socket): callable
    {
        return function (object $watcher, int $flags) use ($socket) {
            $buffer = fread($socket, 1024);
            if ($buffer === false || $buffer === '') {
                $id = (int)$socket;
                $this->log('%d: close socket', $id);
                unset($this->sockets[$id]);
                fclose($socket);

                $this->clientEvents[$id]->stop();
                $this->clientEvents[$id]->clear();

                #$this->log('%d: close', $id);
                return;
            }

            $size = @fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\n\r\nok");
            #$this->log('%d: request', (int)$socket);
        };
    }

    protected function log(string $message, ...$params): void
    {
        echo sprintf($message, ...$params) . PHP_EOL;
    }

    public function runLoop(): void
    {
        $this->loop->run();
    }
}