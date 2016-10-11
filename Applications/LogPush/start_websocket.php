<?php
use Workerman\Worker;
require_once __DIR__ . '/../../Workerman/Autoloader.php';

// 创建一个Worker监听2346端口
$websocket_worker = new Worker("websocket://0.0.0.0:1993");

// 启动1个进程对外提供服务
$websocket_worker->count = 1;
$websocket_worker->Connection = null;

$websocket_worker->onMessage = function($connection, $data)use($websocket_worker)
{  

    // 判断当前客户端是否已经验证,既是否设置了uid
    if($data == 'uid1')
    {
       // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
    
       /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
        * 实现针对特定uid推送数据
        */
       $websocket_worker->Connection = $connection;
       return;
    }

    if ($websocket_worker->Connection != null) {
        $websocket_worker->Connection->send($data);
    }
  
   
};



// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}