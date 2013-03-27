Android Push Server
===================

The proxy server for the Prowork Notification app. The server:
* receives register notification from the app and subscribes the user to [Prowork's push](http://dev.prowork.me/push-subscribe). (See register.php)
* receives push messages from Prowork, processes them and sends to the app via GCM server. (push.php)
* unregisters the user from Prowork push. (unregister.php)

The android application source is available at [Prowork Notifications](https://github.com/kehers/Prowork-Notifications). See the blog post '[Hacking Prowork: Building a Prowork Android App for notifications](http://blog.prowork.me/post/46420992101/hacking-prowork-building-a-prowork-android-app-for)' for more.