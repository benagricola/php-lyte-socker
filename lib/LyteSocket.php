<?php
class LyteSocket {

	/**
	 * A message we've already ready off the read buffer that's just waiting for
	 * receive() to be called
	 */
	protected $_message = null;

	/**
	 * Stuff we've read off the socket but not yet aligned in to any
	 * kind of message boundaries
	 */
	protected $_readBuffer = '';

	/**
	 * The raw php socket we're working over
	 */
	protected $_socket;

	/**
	 * Create a pair of sockets
	 *
	 * Usually called just before forking so that you have a pair of sockets
	 * ready to pass messages via
	 */
	public static function createPair() {
		if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
			throw new Exception("Failed to start IPC socket pair");
		}
	   
		return array(
			new LyteSocket($sockets[0]),
			new LyteSocket($sockets[1])
		);
	}

	/**
	 * Create a new LyteSocket from a PHP socket
	 *
	 * @param $socket
	 */
	public function __construct(&$socket) {
		$this->_socket =& $socket;
	}

	/**
	 * Create a new socket by specifying the type
	 *
	 * See: http://php.net/socket_create
	 */
	public static function create($domain, $type, $protocol) {
		$socket = socket_create($domain, $type, $protocol);
		return new LyteSocket($socket);
	}

	/**
	 * Bind a LyteSocket to a given address
	 *
	 * @param string $address
	 * @param int $port optional port
	 */
	public function bind($address, $port = 0) {
		return socket_bind($this->_socket, $address, $port);
	}

	/**
	 * Close the underlying socket
	 */
	public function close() {
		socket_close($this->_socket);
	}

	/**
	 * After a connection has been bound it can start listening
	 */
	public function listen() {
		return socket_listen($this->_socket);
	}

	/**
	 * Accept connections from a listening socket and returns
	 * the new connection as a new LyteSocket
	 */
	public function accept() {
		$sock = socket_accept($this->_socket);
		return new LyteSocket($sock);
	}

	/**
	 * Connect a socket
	 */
	public function connect($address, $port = 0) {
		return socket_connect($this->_socket, $address, $port);
	}

	/**
	 * Get socket name info
	 *
	 * See: http://php.net/manual/en/function.socket-getsockname.php
	 */
	public function getSockName(&$addr, &$port = 0) {
		return socket_getsockname($this->_socket, $addr, $port);
	}

	/**
	 * Send a string over the socket
	 *
	 * @param string $data 
	 */
	public function send($data) {
		$bytes = strlen($data); // yes, strlen is actually in bytes
	
		// stupidly simplistic "protocol":
		// write a line with the number of bytes we're about to send as an ascii represented int
		// write data
		// done.
		socket_write($this->_socket, $bytes."\n");
		socket_write($this->_socket, $data);
	}

	/**
	 * Get the next string off of the socket
	 *
	 * Will block if not ready
	 *
	 * @return string Full message from socket
	 */
	public function receive() {
		// try to just buffer
		while (!$this->ready()) {
			socket_set_block($this->_socket);
			$this->_readBuffer .= socket_read($this->_socket, 1024, PHP_BINARY_READ);
		}

		$message = $this->_message;
		$this->_message = null;
		return $message;
	}

	/**
	 * Select over an array of sockets for reading
	 *
	 * @param array $reads sockets to check if they are ready to read from
	 *
	 * @return array keys of sockets ready to read from
	 */
	public static function select($reads) {
		$rawReads = array();
		$rawWrites = array();
		$rawExcepts = array();
		foreach ($reads as &$read) $rawReads []=& $read->_socket;

		$ready = self::_checkReady($reads);
		while (empty($ready)) {
			socket_select($rawReads, $rawWrites, $rawExcepts, null);
		}

		return $ready;
	}

	/**
	 * Check an array of sockets to see if any are ready
	 *
	 * @param array $sockets
	 *
	 * @return array keys of sockets that are ready
	 */
	protected static function _checkReady(&$sockets) {
		$ready = array();
		foreach ($sockets as $key => &$socket)
			if ($socket->ready())
				$ready []= $key;
		return $ready;
	}

	/**
	 * See whether this socket is ready to be ready from yet or not
	 *
	 * @return bool
	 */
	public function ready() {
		$this->_buffer();
		return !is_null($this->_message);
	}

	/**
	 * Buffer stuff off the socket until we run out of data
	 * or we have a full message in $this->_message
	 */
	protected function _buffer() {
		socket_set_nonblock($this->_socket);
		while ($data = socket_read($this->_socket, 1024, PHP_BINARY_READ)) {
			if ($data == '') break; // EOF
			$this->_readBuffer .= $data;
		}

		$this->_storeMessage();
	}

	/**
	 * Store the message on the $_readBuffer if there's enough data
	 */
	protected function _storeMessage() {
		// if we have the byte count for the next message
		if (preg_match('%^([0-9]+)\n%', $this->_readBuffer, $match)) {
			$bytes = $match[1];
			// if we have enough data for the message
			if (strlen($this->_readBuffer) >= strlen($bytes) + 1 + $bytes) {
				// store it
				$this->_message = substr($this->_readBuffer, strlen($bytes)+1, $bytes);
				// remove from buffer
				$this->_readBuffer = substr($this->_readBuffer, strlen($bytes)+1+$bytes);
			}
		}
	}
}
