<?php
declare(strict_types=1);

class Server
{
	/** @var resource[]  */
	protected array $sockets = [];
	protected int $connectCount = 0;
	public int $pid = 0;
	public int $i = 0; // порядковый номер воркера для балансировки
	public int $total = 0; // число воркеров
	protected int $accept = 0;

	/** @var resource  */
	#protected $sync;

	public function __construct()
	{
		#$this->sync = shmop_open('accept', 'w', 0644, 1);
		#apcu_delete('acceptCount');
		#apcu_add('acceptCount', 0, 3600);
	}

	public function listen(string $address): self
	{
		$serverSocket = stream_socket_server($address, $errno, $errmsg, STREAM_SERVER_BIND |
			STREAM_SERVER_LISTEN);
		if (!$serverSocket) {
			throw new RuntimeException('Can`t create socket');
		}

		$socket = socket_import_stream($serverSocket);
		socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
		socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
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
		$buffer = fread($socket, 1);
		if ($buffer === false || $buffer === '') {
			$id = (int)$socket;
			//$this->log('%d: close socket', $id);
			unset($this->sockets[$id]);
			fclose($socket);
			return;
		}

		#$this->log('%d: read: %d', $id, strlen($buffer));
		#$message = 'OK: ' . ($this->connectCount++);
		#$len = strlen($message);
		#$size = @fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: $len\r\n\r\n$message");
		$size = @fwrite($socket, "HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nContent-Length: 2\r\n\r\nok");
		#$this->log('%d - %d: write: %d', $this->i, (int)$socket, $size);
	}

	protected function readServerSocket($socket): void
	{
		// нет балансировки коннектов, между процессами
		$clientSocket = null;
		set_error_handler(static function(){});
		$clientSocket = stream_socket_accept($socket, 0, $remote_address); // concurrency process read all, but only first success
		\restore_error_handler();

		if ($clientSocket === false) {
			return;
		}

		stream_set_read_buffer($clientSocket, 0);
		stream_set_write_buffer($clientSocket, 0);
		stream_set_blocking($clientSocket, false);
		stream_set_timeout($clientSocket, 1);

		$id = (int)$clientSocket;
		$this->sockets[$id] = $clientSocket;
		//$this->log('%d: add client: %d', (int)$socket, $id);
	}
}