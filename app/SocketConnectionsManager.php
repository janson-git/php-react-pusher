<?php

namespace App;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class SocketConnectionsManager implements WampServerInterface
{
    protected const INFO = 'INFO';
    protected const ERROR = 'ERROR';

    protected const SEND_TO_ALL = '*';

    /** A lookup of all the topics clients have subscribed to */
    protected $subscribedTopics = [];
    /** @var ConnectionInterface[]  */
    protected $userConnectionsMap = [];
    /** @var \ZMQSocket */
    protected $zmqSocket;

    /**
     * Set ZMQ socket to forward client messages to server
     * @param \ZMQSocket $zmqSocket
     */
    public function setPushToServerSocket(\ZMQSocket $zmqSocket) : void
    {
        $this->zmqSocket = $zmqSocket;
    }

    protected function log(string $message, string $level = self::INFO) : void
    {
        echo "{$level}: {$message}\n";
    }

    /**
     * Client subscribe for topics
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     */
    public function onSubscribe(ConnectionInterface $conn, $topic) : void
    {
        $this->log("Subscribe to {$topic->getId()}!");
        $this->subscribedTopics[$topic->getId()] = $topic;

        $topic->broadcast(['title' => 'You successfully subscribed!']);
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic) : void
    {
    }

    /**
     * Handles when connection is open. On connection established save connection to userId map for server messages routing
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) : void
    {
        $path = $conn->wrappedConn->httpRequest->getUri()->getPath();
        $userId = str_replace('/', '', $path);

        if (!empty($userId)) {
            $this->userConnectionsMap[$userId] = $conn;
        }

        $this->log("Opened for {$userId}!");
    }

    /**
     * When connection closed - remove it from userConnectionMap
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) : void
    {
        $path = $conn->wrappedConn->httpRequest->getUri()->getPath();
        $userId = str_replace('/', '', $path);

        if (!empty($userId)) {
            unset($this->userConnectionsMap[$userId]);
        }

        $this->log("Closed for {$userId}!");
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }

    /**
     * Handles on web-socket message receive.
     * We get message and send to application via ZMQ socket
     *
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     * @param string $event
     * @param array $exclude
     * @param array $eligible
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) : void
    {
        $message = json_encode(['topic' => $topic->getId(), 'event' => $event]);

        $this->log('Published message from client!');
        $this->log($message);

        try {
            $this->zmqSocket->send($message);
        } catch (\ZMQSocketException $e) {
            $this->log('Failed send client message to ZMQ: ' . $e->getMessage(), self::ERROR);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo 'error! ' . $e->getMessage();
    }


    /**
     * Handles on server push message receive. Search for receiver ID in message and route it in connections map.
     * If receiver array has '*' value - send message to ALL connections.
     *
     * @param string $encodedMessage
     */
    public function onPushMessage(string $encodedMessage) : void
    {
        $this->log('Pushed message from server!');
        $this->log($encodedMessage);

        $entryData = json_decode($encodedMessage, true);

        $receivers = $entryData['receivers'] ?? null;
        $topicId = $entryData['topic'] ?? null;
        if (empty($receivers) && empty($topicId)) {
            $this->log('No receivers for message!', self::ERROR);
            return;
        }


        if ($topicId) {
            // send to all the clients subscribed to that topic
            /** @var ConnectionInterface $conn */
            if (!array_key_exists($topicId, $this->subscribedTopics)) {
                $this->log("No subscribers for {$topicId}", self::ERROR);
                return;
            }

            $topic = $this->subscribedTopics[$topicId];
            $topic->broadcast($entryData);
        } else {
            // send to receivers
            $message = $entryData['message'] ?? [];
            if (empty($message)) {
                $this->log('No data in message', self::ERROR);
                return;
            }

            if (in_array(self::SEND_TO_ALL, $receivers, true)) {
                $receivers = $this->userConnectionsMap;
            }

            // send message to all $receivers
            foreach ($receivers as $receiver) {
                /** @var ConnectionInterface $conn */
                $conn = $this->userConnectionsMap[ (string)$receiver ] ?? null;
                if ($conn) {
                    $conn->send(json_encode($message));
                    $this->log("Sent to {$receiver}");
                }
            }
        }
    }
}