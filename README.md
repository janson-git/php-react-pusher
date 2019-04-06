## WebSocket server based on Ratchet (reactphp)

It is simple sample for example project to handle and manage messages flow 
between server application and browser websocker client.


#### Run your own server by steps:

1. install dependencies (zeromq and reactphp)

```
apt-get install php-zmq
php composer.phar install
```

2. run push server

`php server.php`

3. open _client.html_ in browser

4. open browser console to get response log

5. open terminal and send some new info to push-server

`php send-message-to-pusher.php`

6. look to browser console: you have new data in it!