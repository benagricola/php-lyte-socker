# php-lyte-socker

[![Build Status](https://travis-ci.org/neerolyte/php-lyte-socker.png)](https://travis-ci.org/neerolyte/php-lyte-socker)

A simple message passer to sit over PHP sockets when doing IPC.

I've only written this because I couldn't find an existing one that works, if you know of another one let me know!

## Creating a socket pair

```php
$pair = LyteSocket::createPair();
```

## Pass a message

```php
$pair[0]->send("Any arbitrary length message");
```

## Receive a message

```php
// Get back exactly one message:
$message = $pair[1]->receive();
```
