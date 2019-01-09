<?php

namespace App;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

/**
 * Class Pusher
 * @package App
 *
 * Этот класс и вообще пуш-сервер можно использовать для приложений типа расширения браузера,
 * которое при запуске коннектится к нам, и подписывается на определённые топики.
 * А пуш-сервер при обновлениях оповещает всех об изменениях в этих топиках.
 */
class Pusher implements WampServerInterface {

    protected const SEND_TO_ALL = '*';

    /**
     * A lookup of all the topics clients have subscribed to
     */
    protected $subscribedTopics = [];
    /** @var ConnectionInterface[]  */
    protected $userConnectionsMap = [];

    /**
     * Клиент подписывается на определенные события
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     */
    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->subscribedTopics[$topic->getId()] = $topic;
        echo "subscribe to {$topic->getId()}!\n";

        $topic->broadcast(['title' => 'You successfully subscribed!']);
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic) {
    }

    public function onOpen(ConnectionInterface $conn) {
        echo "Opened!\n";

        $path = $conn->wrappedConn->httpRequest->getUri()->getPath();
        $userId = str_replace('/', '', $path);

        if (!empty($userId)) {
            $this->userConnectionsMap[$userId] = $conn;
        }
    }
    public function onClose(ConnectionInterface $conn) {
    }
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->close();
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
    }

    /**
     * Входящее сообщение от приложения. Отправляем пользователям.
     * @param string $encodedMessage
     */
    public function onMessage(string $encodedMessage)
    {
        echo "receive message\n";
        echo $encodedMessage . "\n";

        $entryData = json_decode($encodedMessage, true);

        $receivers = $entryData['receivers'] ?? null;
        if (empty($receivers)) {
            echo "No receivers for message: {$encodedMessage}";
            return;
        }

        $message = $entryData['message'] ?? [];
        if (empty($message)) {
            echo "No data in message: {$encodedMessage}";
            return;
        }

        $type = $message['type'] ?? 'undefined';

        // TODO: возможно сам механизм подписки нам тут не нужен? Просто рассылка по всем, например
        if (in_array(self::SEND_TO_ALL, $receivers, true)) {
            // If the lookup topic object isn't set there is no one to publish to
            if (!array_key_exists($type, $this->subscribedTopics)) {
                return;
            }

            $topic = $this->subscribedTopics[$type];

            // re-send the data to all the clients subscribed to that category
            $topic->broadcast($message);
        } else {
            // если отправка сообщения конкретному пользователю - игнорим топики
            foreach ($receivers as $receiver) {
                $conn = $this->userConnectionsMap[$receiver] ?? null;
                if ($conn) {
                    echo 'send to ' . $receiver . "\n";
                    $conn->event($type, $message);
                }
            }
        }
    }
}