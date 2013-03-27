Android Push Server
===============

The proxy server for the Prowork Notification app. The server:
* receives register notification from the app and subscribes the user to [Prowork's push](http://api.prowork.me/push-subscribe). (See register.php)
* receives push messages from Prowork, processes them and sends to the app via GCM server. (push.php)
* unregisters the user from Prowork push. (unregister.php)