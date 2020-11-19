<?php
function getObjectId(\Swoole\Http\Response $response)
{
    if (PHP_VERSION_ID < 70200) {
        $id = spl_object_hash($response);
    } else {
        $id = spl_object_id($response);
    }
    return $id;
}

//
//Co\run(function () {
//    global $wsObjects;
//    $redis = new Swoole\Coroutine\Redis();
//    $redis->connect('127.0.0.1', 6379);
//    $redis->auth("crs1rwoijdvl6rqd83dvN2jaF");
//    if ($redis->subscribe(['msg_0', 'msg_1', 'msg_2'])) // 或者使用psubscribe
//    {
//        while ($msg = $redis->recv()) {
//            // msg是一个数组, 包含以下信息
//            // $type # 返回值的类型：显示订阅成功
//            // $name # 订阅的频道名字 或 来源频道名字
//            // $info  # 目前已订阅的频道数量 或 信息内容
//            list($type, $name, $info) = $msg;
//            if ($type == 'subscribe') { // 或psubscribe
//                // 频道订阅成功消息，订阅几个频道就有几条
//            } else if ($type == 'unsubscribe' && $info == 0) { // 或punsubscribe
//                break; // 收到取消订阅消息，并且剩余订阅的频道数为0，不再接收，结束循环
//            } else if ($type == 'message') {  // 若为psubscribe，此处为pmessage
//                var_dump($name); // 打印来源频道名字
//                var_dump($info); // 打印消息
//                foreach ($wsObjects as $obj) {
//                    $obj->push("Server：{$name}");
//                }
//                // balabalaba.... // 处理消息
////                    if ($need_unsubscribe) { // 某个情况下需要退订
////                        $redis->unsubscribe(); // 继续recv等待退订完成
////                    }
//            }
//        }
//    }
//});


Co\run(function () {
    $server = new Co\Http\Server('0.0.0.0', 9502, false);
    $server->handle('/websocket', function ($request, $ws) {
        $ws->upgrade();
        global $wsObjects;
        $objectId = getObjectId($ws);
        $wsObjects[$objectId] = $ws;
        echo $objectId . "\n";
        $redis = new Swoole\Coroutine\Redis();
        $redis->connect('127.0.0.1', 6379,true);
        $redis->auth("crs1rwoijdvl6rqd83dvN2jaF");
        $redis->set("test", "xxx");
        $redis->hset("ws", $objectId, $ws->fd);
        while (true) {
            $frame = $ws->recv();
            if ($frame === '') {
                unset($wsObjects[$objectId]);
                $ws->close();
                break;
            } else if ($frame === false) {
                echo "error : " . swoole_last_error() . "\n";
                break;
            } else {
                if ($frame->data == 'close' || get_class($frame) === Swoole\WebSocket\CloseFrame::class) {
                    unset($wsObjects[$objectId]);
                    $ws->close();
                    return;
                }
                $redis->set("test", $frame->data);
                foreach ($wsObjects as $obj) {
                    $obj->push("Server：{$frame->data}");
                }
//                $ws->push("Hello {$frame->data}!");
//                $ws->push("How are you, {$frame->data}?");
            }
        }
    });

    $server->handle('/', function ($request, $response) {
        $response->end(<<<HTML
    <h1>Swoole WebSocket Server</h1>
    <script>
var wsServer = 'ws://123.207.190.86:9502/websocket';
var websocket = new WebSocket(wsServer);
websocket.onopen = function (evt) {
    console.log("Connected to WebSocket server.");
    websocket.send('hello');
};

websocket.onclose = function (evt) {
    console.log("Disconnected");
};

websocket.onmessage = function (evt) {
    console.log('Retrieved data from server: ' + evt.data);
};

websocket.onerror = function (evt, e) {
    console.log('Error occured: ' + evt.data);
};
</script>
HTML
        );
    });

    $server->start();
});

