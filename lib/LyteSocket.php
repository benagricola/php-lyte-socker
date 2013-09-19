<?php
class LyteSocket {
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
		$this->_readBuffer = '';
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
	 * @return string Full message from socket
	 */
	public function receive() {
		// while the read buffer is empty or doesn't contain an integer followed by a newline
		while (strlen($this->_readBuffer) == 0 || !preg_match('%^([0-9]+)\n%', $this->_readBuffer, $match))
			$this->_readBuffer .= socket_read($this->_socket, 1024, PHP_BINARY_READ);

		// now we should know how many bytes the message will be
		$bytes = $match[1];

		// remove byte identifier from buffer
		$this->_readBuffer = substr($this->_readBuffer, strlen($bytes)+1);

		while (strlen($this->_readBuffer) < $bytes)
			$this->_readBuffer .= socket_read($this->_socket, 1024, PHP_BINARY_READ);

		// just grab our message out of the stream
		$data = substr($this->_readBuffer, 0, $bytes);

		// remove message from buffer
		$this->_readBuffer = substr($this->_readBuffer, $bytes);

		return $data;
	}

	/**
	 * Select over an array of sockets for reading
	 *
	 * @param array $reads sockets to check if they are ready to read from
	 *
	 * @return array keys of sockets ready to read from
	 */
	public static function select($reads, $writes = array(), $excepts = array()) {
		$rawReads = array();
		$rawWrites = array();
		$rawExcepts = array();
		foreach ($reads as &$read) $rawReads []=& $read->_socket;
		foreach ($writes as &$write) $rawWrites []=& $write->_socket;
		foreach ($excepts as &$except) $rawExcepts []=& $except->_socket;
		return socket_select($rawReads, $rawWrites, $rawExcepts, null);
	}
}
