<?php

$ws = new Swoole\WebSocket\Server('0.0.0.0', 9501);

//Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]);

Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

$ws->set(array(
    'debug_mode' => 1,
    'worker_num' => 1,
    'max_request' => 10000,
    'daemonize' => 1, //以守护进程执行
    'dispatch_mode' => 2,
    'task_worker_num' => 2, //task进程的数量
    'max_wait_time' => 60,
    'reload_async' => true,
    'heartbeat_check_interval' => 60,
    'heartbeat_idle_time' => 120,
    'pid_file' => 'swoole.pid',
    "log_file" => "swoole.log",//日志
//    'ssl_cert_file' => __DIR__ . '/../config/fullchain.pem',
//    'ssl_key_file' => __DIR__ . '/../config/privkey.pem'
));

$newprocess = new Swoole\Process(function ($process) use ($ws) {
    $redis = new Swoole\Coroutine\Redis();
    $redis->connect('127.0.0.1', 6379, true);
    $redis->auth("crs1rwoijdvl6rqd83dvN2jaF");
    if ($redis->subscribe(['msg'])) // 或者使用psubscribe
    {
        while ($msg = $redis->recv()) {
            // msg是一个数组, 包含以下信息
            // $type # 返回值的类型：显示订阅成功
            // $name # 订阅的频道名字 或 来源频道名字
            // $info  # 目前已订阅的频道数量 或 信息内容
            list($type, $name, $info) = $msg;
            if ($type == 'subscribe') { // 或psubscribe
                // 频道订阅成功消息，订阅几个频道就有几条
            } else if ($type == 'unsubscribe' && $info == 0) { // 或punsubscribe
                break; // 收到取消订阅消息，并且剩余订阅的频道数为0，不再接收，结束循环
            } else if ($type == 'message') {  // 若为psubscribe，此处为pmessage
//                    var_dump($name); // 打印来源频道名字
                var_dump($info); // 打印消息
                var_dump(count($ws->connections) . " conns");
                foreach ($ws->connections as $fd) {
                    // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                    if ($ws->isEstablished($fd)) {
                        var_dump($fd . " fd");
                        //向 WebSocket 客户端连接推送数据，长度最大不得超过 2M。
                        $ws->push($fd, "hello, welcome $info\n");
                    } else {
                        var_dump($fd . " fd error");
                    }
                }
                // balabalaba.... // 处理消息
//                    if ($need_unsubscribe) { // 某个情况下需要退订
//                        $redis->unsubscribe(); // 继续recv等待退订完成
//                    }
            }
        }
    }
    Co::sleep(1);
}, false, 2, 1);
$ws->addProcess($newprocess);

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
//    $ws->connections[] = $request->fd;
    var_dump($request->fd);
    $ws->push($request->fd, "open\n");
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
    echo "Message: {$frame->data}\n";
    $ws->push($frame->fd, "server: {$frame->data}");
});

//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
});


//workstart事件
$ws->on('workerstart', function ($ws, $work_id) {

});


//server task
$ws->on('task', function ($ws, $taskid, $from_id, $data) {
    global $table;
    foreach ($table as $row) {
        $ws->send($row['fd'], "{$row['fd']} i am broadcast");
    }

    $ws->finish("$data -> OK");
});

//处理异步任务的结果
$ws->on('finish', function ($ws, $task_id, $data) {
    echo "AsyncTask[$task_id] Finish: $data" . PHP_EOL;
});

$ws->start();
