<?php

Co\run(function () {
    $client = new Swoole\Coroutine\Http\Client("123.207.190.86", 9501);
    $ret = $client->upgrade("/");
    if ($ret) {
        while (true) {
            $client->push("hello");
            var_dump($client->recv());
            co::sleep(1);
        }
    }
});
