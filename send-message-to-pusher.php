<?php

// Это пример того, как можно отправлять сообщения через сокеты клиентам,
// используя ZMQ сокеты. Можно сделать этакий Sender на стороне приложения,
// который будет отправлять запакованые сообщения пуш-серверу, а тот - клиентам.

require __DIR__ . '/vendor/autoload.php';

$entryData = [
    'topic'    => 'newPost',
    'title'    => 'Title Here',
    'article'  => 'Some text data will be here',
    'when'     => time()
];

$context = new ZMQContext();
$socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'toClient');
$socket->connect("tcp://localhost:5555");

$socket->send(json_encode($entryData));
