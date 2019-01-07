## reactphp pusher (Ratchet)

Simple pusher on websockets.

For example it may be used to push news from server to your browser extension or mobile app.

- extension on start will connect to push server and 'subscribe' for some category of news
- push-server wait for news from zeromq connection
- your server side app connect to zeromq and send some news
- push-server gets it from zeromq and send to connected clients
- client side gets info from socket and console.log() it for now

#### and run your own server by steps:

1. install dependencies (zeromq and reactphp)

```
apt-get install php-zmq
php composer.phar install
```

2. run push server

`php push-server.php`

3. open _client.html_ in browser

4. open browser console to get response log

5. open terminal and send some new info to push-server

`php send-message-to-pusher.php`

6. look to browser console: you have new data in it!