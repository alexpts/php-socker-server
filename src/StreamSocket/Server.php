<?php
declare(strict_types=1);

namespace PTS\SocketServer\StreamSocket;

use PTS\SocketServer\ServerInterface;
use RuntimeException;

class Server implements ServerInterface
{
    /** @var resource[] */
    protected array $sockets = [];
    public int $pid = 0;

    /**
     * @param string $address - tcp://0.0.0.0:58380
     * @param int $port
     *
     * @return $this
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
        #socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        stream_set_blocking($serverSocket, false);

        $this->sockets['server'] = $serverSocket;
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
            $write = $expect = null;
            stream_select($read, $write, $expect, null);

            foreach ($read as $name => $socket) {
                if ($name === 'server') {
                    $this->accept($socket);
                    continue;
                }

                $this->incomingMessage($socket);
            }
        }
    }

    protected function incomingMessage($socket): void
    {
        // Если сокет полностью не вычитать, то он будет помечен снова на следующей итерации как активный
        // Фактически это приведет, что на 1 keep-alive запрос будет отправлено множество ответов по 1 на итерацию
        $buffer = fread($socket, 1);
        if ($buffer === false || $buffer === '') {
            $id = (int)$socket;
            //$this->log('%d: close socket', $id);
            unset($this->sockets[$id]);
            fclose($socket);
            return;
        }

        $size = @fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: Keep-Alive\r\nContent-Length: 2\r\n\r\nok");
    }

    protected function accept($socket): void
    {
        // нет балансировки коннектов, между процессами
        $clientSocket = null;
        set_error_handler(static function () {});
        // concurrency process read all, but only first success
        $clientSocket = stream_socket_accept($socket, 0, $remote_address);
        restore_error_handler();

        if ($clientSocket === false) {
            return;
        }

        #socket_set_option($clientSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        stream_set_read_buffer($clientSocket, 1024);
        stream_set_write_buffer($clientSocket, 1024);
        stream_set_blocking($clientSocket, false);
        stream_set_timeout($clientSocket, 3);

        $id = get_resource_id($clientSocket);
        $this->sockets[$id] = $clientSocket;
    }
}