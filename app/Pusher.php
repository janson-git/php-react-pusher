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
    /**
     * A lookup of all the topics clients have subscribed to
     */
    protected $subscribedTopics = array();

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
        echo "OK!\n";
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
        $entryData = json_decode($encodedMessage, true);
        echo "receive message\n";
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($entryData['category'], $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$entryData['category']];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast($entryData);
    }
}