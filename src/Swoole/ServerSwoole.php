<?php
declare(strict_types=1);

namespace PTS\SocketServer\Swoole;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as SwooleServer;

class Server
{

    protected SwooleServer $server;

    /**
     * @param string $address - tcp://0.0.0.0:58380
     * @param int $port
     *
     * @return $this
     */
    public function listen(string $address, int $port = 0): static
    {
        $server = new SwooleServer($address, $port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $server->set([
            'worker_num' => 1, // The number of worker processes
            'dispatch_mode' => 3,
            #'daemonize' => true, // Whether start as a daemon process
            'backlog' => 2048, // TCP backlog connection number
            #'tcp_fastopen' => true,

            'open_http_protocol' => false,
            'open_http2_protocol' => false,
            'open_websocket_protocol' => false,
            'open_mqtt_protocol' => false,
            'open_length_check' => false,
        ]);

        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('request', [$this, 'onRequest']);
        $server->on('close', [$this, 'onClose']);

        $this->server = $server;
        return $this;
    }

    public function start(): void
    {
        $this->server->start();
    }


    public function onConnect(SwooleServer $server, int $socket, int $reactorId): void
    {
        $a = 1;
    }

    public function onReceive(SwooleServer $server, int $inSocket, int $reactor_id, string $data): void
    {
        $server->send($inSocket, "HTTP/1.1 200 OK\r\nContent-Length: 1\r\n\r\n1");
    }

    public function onRequest(Request $request, Response $response)
    {
        $response->write(1);
    }

    public function onClose(SwooleServer $server, int $inSocket, int $reactorId): void
    {
        if ($reactorId < 0) {
            // close by server
        }

        $server->send($inSocket, "HTTP/1.1 200 OK\r\nContent-Length: 1\r\n\r\n1");
    }
}