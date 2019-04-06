<?php

require __DIR__ . '/vendor/autoload.php';

$loop   = React\EventLoop\Factory::create();
$connectionsManager = new App\SocketConnectionsManager;

// Listen for the web server to make a ZeroMQ push after an ajax request
$context = new React\ZMQ\Context($loop);
// Socket to listen FROM SERVER!
$pull = $context->getSocket(ZMQ::SOCKET_PULL, 'toClient');
$pull->bind('tcp://127.0.0.1:5555'); // Binding to 127.0.0.1 means the only client that can connect is itself
$pull->on('message', array($connectionsManager, 'onPushMessage'));

// Socket to send TO SERVER
$push = $context->getSocket(ZMQ::SOCKET_PUSH, 'toServer');
$pushSocket = $push->connect('tcp://127.0.0.1:5556');

$connectionsManager->setPushToServerSocket($pushSocket);

// Set up our WebSocket server for clients wanting real-time updates
$webSock = new React\Socket\Server('0.0.0.0:8081', $loop); // Binding to 0.0.0.0 means remotes can connect
$webServer = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new Ratchet\Wamp\WampServer($connectionsManager)
        )
    ),
    $webSock
);

$loop->run();