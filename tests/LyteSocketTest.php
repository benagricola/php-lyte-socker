<?php
require_once(dirname(__FILE__).'/Autoload.php');
class LyteSocketTest extends PHPUnit_Framework_TestCase {
	/**
	 * Ensure we can ask for a socket pair
	 */
	public function testCreateSocketPair() {
		$pair = LyteSocket::createPair();
		$this->assertInternalType('array', $pair);
		$this->assertInstanceOf('LyteSocket', $pair[0]);
		$this->assertInstanceOf('LyteSocket', $pair[1]);
	}

	/**
	 * Ensure we can pass a simple message
	 */
	public function testPassSimpleMessage() {
		$pair = LyteSocket::createPair();

		$pair[0]->send("This is just some text");
		$message = $pair[1]->receive();
		$this->assertEquals('This is just some text', $message);
	}

	/**
	 * Ensure we only get the expected message if we put two messages on the stream
	 */
	public function testPassTwoMessages() {
		$pair = LyteSocket::createPair();

		$pair[0]->send("This is just some text");
		$pair[0]->send("We don't want this text yet");
		$message = $pair[1]->receive();
		$this->assertEquals('This is just some text', $message);
		$message = $pair[1]->receive();
		$this->assertEquals("We don't want this text yet", $message);
	}

	/**
	 * Select over an array of sockets
	 */
	public function testSelect() {
		$pair = LyteSocket::createPair();
		$pair[0]->send("Test");

		$ready = LyteSocket::select($pair);
		$this->assertEquals(1, $ready, 'one socket should be ready');
	}
}
