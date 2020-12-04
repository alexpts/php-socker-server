<?php
declare(strict_types=1);

class Server
{
    /** @var resource[] */
    protected array $sockets = [];
    public int $pid = 0;

    public function listen(string $address): self
    {
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

    public function runLoop(): void
    {
        while (true) {
            $read = $this->sockets;
            $write = $expect = null;
            stream_select($read, $write, $expect, null);

            foreach ($read as $name => $socket) {
                if ($name === 'server') {
                    $this->readServerSocket($socket);
                    continue;
                }

                $this->readClientSocket($socket);
            }
        }
    }

    protected function readClientSocket($socket): void
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

        $size = @fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 1\r\n\r\n1");
        #$size = @fwrite($socket, "HTTP/1.1 200 OK\\r\nContent-Length: 2\r\n\r\nok");
    }

    protected function readServerSocket($socket): void
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

        stream_set_read_buffer($clientSocket, 0);
        stream_set_write_buffer($clientSocket, 0);
        stream_set_blocking($clientSocket, false);
        stream_set_timeout($clientSocket, 1);

        $id = (int)$clientSocket;
        $this->sockets[$id] = $clientSocket;
        //$this->log('%d: add connect: %d', (int)$socket, $id);
    }
}