<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
require_once __DIR__ . '/../../Workerman/Autoloader.php';

$udp_worker = new Worker('udp://0.0.0.0:1994');
$udp_worker->onMessage = function($connection, $data){
	//判断编码方式，设为utf-8
	$e=mb_detect_encoding($data, array('UTF-8', 'GBK'));
	if ($e == 'CP936') {
		$data = mb_convert_encoding($data, 'UTF-8','GBK');
	}

	//记录日志
	$myfile = fopen("log.txt", "a");
	fwrite($myfile, date('Y-m-d H:i:s', time()));
	fwrite($myfile, $data);
	fclose($myfile);
	
	//智付通初始化
	$DevNo = '';
	$Class = '';
	$AccNo = '';
	$IsErrorLog = '';
	$Exception = '';
	$Description = '';
	$Time = '';

	date_default_timezone_set("Asia/Shanghai");
	if (preg_match('/\..*?\s/', $data)) {//通过正则判断是否为zft

		preg_match('/\..*?\s/', $data, $m);
		$Class =substr($m['0'], 1);

		if(preg_match('/SafeDevId=[0-9]*/', $data, $m)) {
			$DevNo = substr($m['0'], 10);
		}

		if(preg_match('/"AccNo":"\d+"/', $data, $m)) {
			$AccNo = substr($m['0'], 9, -1);
		}
		
		if(preg_match('/ERROR/', $data, $m)) {
			$IsErrorLog = true;
			$Exception = $data;
		} else {
			$IsErrorLog = false;
			$Description = $data;
		}
		
		$timeString = date('Y').'-'.substr($data, 0, 2).'-'.substr($data, 3, 2).' '.substr($data, 6, 8);

		//mongodb 连接
		$manager = new MongoDB\Driver\Manager("mongodb://192.168.10.8:27017");
		$bulk = new MongoDB\Driver\BulkWrite;
		$document = array(
				'Level' => 1, 
				'Label' => 'UDP广播',
				'DevNo' => $DevNo, 
				'Class' => $Class, 
				'AccNo' => $AccNo, 
				'Description' => $Description, 
				'Exception' => $Exception, 
				'Time' => $timeString, 
				'LogName' => 'ZFTPAD', 
				'IsErrorLog' => $IsErrorLog, 
				'__V' => '0'
			);
		$_id= $bulk->insert($document);
		$writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
		$result = $manager->executeBulkWrite('test.test_zft', $bulk, $writeConcern);

		$data = json_encode($document);
	}
	
	//交行 初始化
 	$Succeed = false;
 	$Trade_Type = '';
 	$Resp_No = '';
 	$Ftag = '';
 	$Ping_Code = '';

	if (preg_match('/交易类型:\[[^-]*\]/', $data, $m)) {
		$Trade_type = substr($m['0'], 14, -1);

		if (preg_match('/返回码:\[[^-]*\]/', $data, $m)) {
			$Resp_No = substr($m['0'], 11, -1);
			if ($Resp_No == '000000') {
				$Succeed = true;
			}
		}

		$Trade_Time = date('Y-m-d').' '.substr($data, 0, 7);

		if (preg_match('/流水号:\[[^-]*\]/', $data, $m)) {
			$Ftag = substr($m['0'], 11, -1);
		}

		if (preg_match('/终端:\[[^-]*\]/', $data, $m)) {
			$Ping_Code = substr($m['0'], 8, -1);
		}

		$Message = $data;
		//mongodb 连接
		$manager = new MongoDB\Driver\Manager("mongodb://192.168.10.8:27017");
		$bulk = new MongoDB\Driver\BulkWrite;
		$document = array(
				'Trade_type' => $Trade_type, 
				'Succeed' => $Succeed,
				'Trade_Time' => $Trade_Time, 
				'Message' => $Message, 
				'Resp_No' => $Resp_No, 
				'Ftag' => $Ftag, 
				'Ping_Code' => $Ping_Code
			);
		$_id= $bulk->insert($document);
		$writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
		$result = $manager->executeBulkWrite('test.test_jh', $bulk, $writeConcern);

		$data = json_encode($document);
		echo $data;
	}
	
	// 以websocket协议连接远程websocket服务器
	$ws_connection = new AsyncTcpConnection("ws://192.168.10.147:1993");
	// 连上后发送hello字符串
	$ws_connection->onConnect = function($connection) use($data) {
	    $connection->send($data);
	};
	// 远程websocket服务器发来消息时
	$ws_connection->onMessage = function($connection, $data){
	    echo "recv: $data\n";
	};
	// 连接上发生错误时，一般是连接远程websocket服务器失败错误
	$ws_connection->onError = function($connection, $code, $msg){
	    echo "error: $msg\n";
	};
	// 当连接远程websocket服务器的连接断开时
	$ws_connection->onClose = function($connection){
	    echo "connection closed\n";
	};
	// 设置好以上各种回调后，执行连接操作
	$ws_connection->connect();
	
};

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}