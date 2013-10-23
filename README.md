Minibackup
==========

What is this?
-------------

A free, opensource service that allows you to store arbitrary data online
trough simple HTTP requests.

Why?
----

To provide a storage that:

 - is anonymous and simple to use.
 - guarantees that only the poster of some data can modify it.

What can I do with it?
----------------------

For example, you could backup or synchronize mobile phone data, by storing
an encrypted file that would contain your app configuration.  It was
originally designed for [Hpass](http://hpass.chmd.fr/). If you do cool
things with it, throw an email to [chmd@chmd.fr](mailto://chmd@chmd.fr).

Under which conditions?
-----------------------

You can use this service free of charge, no registration needed. Upload is
limited to 64KiB, and requests are limited to one per second. If your data
is not read nor written for more than 30 day, it is automatically deleted.

How do I use it?
----------------

To store data, just post it. The service returns a retrieval key (key) and an admin key (admin_key)

    $ curl -s -X POST -d "data=hello" https://minibackup.chmd.fr
    {"key":"3af55e44bdca6a59","admin_key":"4c3109699176234da8a1d5b761a5df1ca2025955","status":201}


To get your content back, use a get request and provide the retrieval key.

    $ curl -s -X GET "https://minibackup.chmd.fr?key=3af55e44bdca6a59"
    hello

To modify your data, use a put request, and provide the admin key, the
retrieval key, and the new data.

    $ curl -s -X PUT -d "data=bye" -d "key=3af55e44bdca6a59" \
           -d "admin_key=4c3109699176234da8a1d5b761a5df1ca2025955" \
           https://minibackup.chmd.fr
    {"message":"Resource updated","status":200}

To delete your data, use a delete request, and provide the admin key and
the retrieval key.

    $ curl -s -X DELETE -d "key=3af55e44bdca6a59" \
           -d "admin_key=4c3109699176234da8a1d5b761a5df1ca2025955" \
           https://minibackup.chmd.fr
    {"message":"Resource deleted","status":200}

Managing your data without transmitting the admin key.
------------------------------------------------------

To update or delete your data, you can also use time-based tokens. They
are easy to generate, and can be transmitted over plain HTTP without
compromising the admin key.

A token is the hmac of the current time rounded to 30 seconds concatenated
to your message and the admin key. The following bash function does it all
for you:

    function sign_request() {
        message=$1
        admin_key=$2
    
        # compute the current time rounded to 30s
        t=$(date +%s)
        timestamp=$(($t-$((t%30))))
    
        echo -n "$timestamp":"$message" |\
            openssl dgst -sha1 -hmac $admin_key -binary |\
            xxd -p
    }

Let us test that. First, we post 'foo' to the service:

    $ curl -s -X POST -d "data=foo" https://minibackup.chmd.fr
    {"key":"612d0b6445f3f992","admin_key":"2c6784cdba9dfe6daf278ddd7d11a358d723524d","status":201}

We want to update this data to 'bar'. To do so, we compute the appropriate
token and we immediately inject it in our request. Keep in mind that to
avoid replay, tokens are only valid for 1 minute, so you have to be
quick!

    $ sign_request "612d0b6445f3f992:bar" "2c6784cdba9dfe6daf278ddd7d11a358d723524d"
    8cc4c911c30400cd51dacc3fe986a73e0a39a24f
    % curl -s -X PUT -d "data=bar" -d "key=612d0b6445f3f992" \
           -d "token=8cc4c911c30400cd51dacc3fe986a73e0a39a24f" \
           https://minibackup.chmd.fr
    {"message":"Resource updated","status":200}

This also works for deletion:

    $ sign_request "612d0b6445f3f992" "2c6784cdba9dfe6daf278ddd7d11a358d723524d"
    ad2c418586b8091be82937b7c610708f6a685ef4
    $ curl -s -X DELETE -d "key=612d0b6445f3f992" \
           -d "token=ad2c418586b8091be82937b7c610708f6a685ef4" \
           https://minibackup.chmd.fr
    {"message":"Resource deleted","status":200}

Comments about tokens and admin keys:

* It is not a problem to transmit your admin key over https (requests are
  encrypted, so external attackers won't be able to observe it).
  However, over http, the service forces you to use tokens.
* You must provide either the admin key or a valid token, but not both.
* If you seem unable to generate valid tokens, it is probably because the
  time on your machine and the time on the server are different. You
  should either change the time on your machine, or use the time of the
  server. You can get the time on the server, parsing the http headers:
  `date --date="$(curl -s -I https://minibackup.chmd.fr | grep Date | sed 's/^Date: //g')" +%s`

Adding encryption on top of the service
---------------------------------------

You are encouraged to encrypt your data before uploading it to minibackup.
The goal is to help you host data online, not to spy you! To do so, you
can, for example, use openssl. Assuming your data is in `file.txt`,
encrypting can be done like this:

    $ openssl aes-256-cbc -base64 -in file.txt -out file.txt.enc

You can then put the content of file.txt.enc on minibackup, and your data
is secure. To decrypt it:

    $ openssl aes-256-cbc -d -base64 -in file.txt.enc -out decrypted.txt

Compatible code samples are provided for [python](/samples/openssl-aes.py)
and [java](/samples/OpensslAES.java).

Can I deploy this elsewhere?
----------------------------

Yes, please deploy other instances! It is free software (MIT license).
Deployment consists in 2 steps:

 1. Download the [archive](minibackup.zip)
 2. Unzip at the root of your server

No database is required, only a recent version of php.

Can I rely on it?
-----------------

Officially, you can't. I do not guarantee anything, and I shall not be
held responsible for any loss nor any leak on your data. And I might stop
the service without notice.

Unofficially, [minibackup.chmd.fr](https://minibackup.chmd.fr) will run as
long as there is no legal nor technical problems, and all precautions will
be taken to keep it safe.

Contributing
------------

You can browse the code on
[git.chmd.fr](http://git.chmd.fr/?p=minibackup.git).

    git clone http://git.chmd.fr/minibackup.git

There is also a [github
mirror](https://github.com/chmduquesne/minibackup).

Quotes
------

* "The megaupload for files smaller than 64KiB" -- Etienne Millon
* "My dream would be to build a turing machine with this service + url
  shorteners" -- Quentin Sabah

Thanks
------

* [John MacFarlane](http://johnmacfarlane.net/), for pandoc (which happily
  generates this webpage) and for kindly letting me steal his CSS for this
  webpage.
* [Sebsauvage](http://sebsauvage.net/), for zerobin (various chunks of
  code are stolen from here), and general inspiration.
* Mark Christian and Brian Klug, for
  [openkeyval.org](http://openkeyval.org) (also a source of inspiration).
* and of course, Michel.
