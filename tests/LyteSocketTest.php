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
		$pair[0]->close();
		$pair[1]->close();
	}

	/**
	 * Ensure we can pass a simple message
	 */
	public function testPassSimpleMessage() {
		$pair = LyteSocket::createPair();

		$pair[0]->send("This is just some text");
		$message = $pair[1]->receive();
		$this->assertEquals('This is just some text', $message);
		$pair[0]->close();
		$pair[1]->close();
	}

	/**
	 * Ensure we only get the expected message if we put two messages on the stream
	 */
	public function testPassTwoMessages() {
		$pair = LyteSocket::createPair();

		$pair[0]->send("This is just some text");
		$pair[0]->send("We don't want this text at first");
		$message = $pair[1]->receive();
		$this->assertEquals('This is just some text', $message);
		$message = $pair[1]->receive();
		$this->assertEquals("We don't want this text at first", $message);
		$pair[0]->close();
		$pair[1]->close();
	}

	/**
	 * Sockets should become ready when sent messages
	 */
	public function testSocketsBecomeReady() {
		$pair = LyteSocket::createPair();

		$this->assertFalse($pair[1]->ready());
		$pair[0]->send("This is just some text");
		for ($i = 0; $i < 100 && !$pair[1]->ready(); $i++)
			usleep(10000);
		$this->assertTrue($pair[1]->ready());

		$this->assertEquals('This is just some text', $pair[1]->receive());
		$pair[0]->close();
		$pair[1]->close();
	}

	/**
	 * Select over an array of sockets
	 */
	public function testSelect() {
		$pair = LyteSocket::createPair();
		$pair[0]->send("Foo");

		$ready = LyteSocket::select($pair);
		$this->assertEquals(array(1), $ready, 'socket one should be ready');

		$pair[1]->send("Bar");
		$ready = LyteSocket::select($pair);
		$this->assertEquals(array(0, 1), $ready, 'both sockets should be ready');

		$this->assertEquals('Foo', $pair[1]->receive());
		$this->assertEquals('Bar', $pair[0]->receive());
		$pair[0]->close();
		$pair[1]->close();
	}

	/**
	 * Can create a socket that listens simply
	 */
	public function testCreateListener() {
		$listener = LyteSocket::create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->assertTrue($listener->bind('127.0.0.1'));
		$this->assertTrue($listener->listen());

		$addr = ''; $port = 0;
		$this->assertTrue($listener->getSockName($addr, $port));

		// right now we're going to spawn another thread to send us some data and
		// check in this one that we get the data
		if (pcntl_fork()) {
			// parent
			$reader = $listener->accept();
			$this->assertEquals('foo', $reader->receive());
			$listener->close();
			$reader->close();
		} else {
			// child
			$listener->close();
			$writer = LyteSocket::create(AF_INET, SOCK_STREAM, SOL_TCP);
			$writer->connect('127.0.0.1', $port);
			$writer->send('foo');
			$writer->close();
			exit;
		}
	}
}
