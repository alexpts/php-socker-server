<?php
declare(strict_types=1);

class SocketServer
{
    # https://github.com/freebsd/freebsd/blob/master/sys/sys/socket.h
    protected const SO_REUSEPORT_LB = 65536;
    protected const SO_REUSEPORT_LB2 = 0x00010000;

    /** @var Socket[] */
    protected array $sockets = [];
    public int $id = 0;
    protected int $currentSocket = 0;

    public function __construct()
    {
        $this->id = random_int(0, 99999);
    }

    public function listen(string $address = '127.0.0.1', $port = 3000): self
    {
        $severSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($severSocket, SOL_SOCKET, 65536, 1);
        socket_set_option($severSocket, SOL_SOCKET, 0x00010000, 1);
        socket_set_option($severSocket, SOL_SOCKET, SO_REUSEPORT, 1);
        socket_set_option($severSocket, SOL_SOCKET, 65536, 1);
        socket_set_option($severSocket, SOL_SOCKET, 0x00010000, 1);

        $isBind = socket_bind($severSocket, $address, $port);
        if (!$isBind) {
            throw new RuntimeException('Can`t bind socket to address');
        }

        $isListen = socket_listen($severSocket, 1024);
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

    public function runLoop(): void
    {
        while (true) {
            $read = $this->sockets;
            $write = $expect = null;
            socket_select($read, $write, $expect, null);

            foreach ($read as $name => $socket) {
                if ($name === 'server') {
                    $this->readServerSocket($socket);
                    #$this->log($this->id . ': incoming client: ' . $this->currentSocket);
                    continue;
                }

                #$this->currentSocket = $name;
                if ($this->readClientSocket($socket) === false) {
                    socket_close($socket);
                    #$this->log($this->id . ': close: ' . $name);
                    unset($this->sockets[$name]);
                }
            }
        }
    }

    protected function readClientSocket(Socket $socket): int|bool
    {
        // Если сокет полностью не вычитать, то он будет помечен снова на следующей итерации как активный
        // Фактически это приведет, что на 1 keep-alive запрос будет отправлено множество ответов по 1 на итерацию
        $buffer = @socket_read($socket, 1);
        if ($buffer === false || $buffer === '') {
            return false;
        }

        $size = @socket_write($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 1\r\n\r\n1");
        #$this->log($this->id . ': handled by: ' . $this->currentSocket);
        return $size;
    }

    protected function readServerSocket(Socket $socket): void
    {
        // нет балансировки коннектов, между процессами
        $clientSocket = null;
        set_error_handler(static function () {});
        // concurrency process read all, but only first success
        $clientSocket = socket_accept($socket);
        restore_error_handler();

        if ($clientSocket === false) {
            return;
        }

        #socket_set_option($clientSocket, SOL_SOCKET, TCP_NODELAY, 1);
        #socket_set_option($clientSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        socket_set_nonblock($clientSocket);

        $this->sockets[] = $clientSocket;
        //$this->log('%d: add connect: %d', (int)$socket, $id);
    }
}