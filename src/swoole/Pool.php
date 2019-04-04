<?php

namespace think\swoole;

use Swoole\Coroutine\Channel;

/**
 *
 * @author : phpprince QQ:8923052
 * @Time: 2019年3月25日 下午12:04:10
 * @version : 1.0.0
 */
class Pool {
	/**
	 * 连接池channel数组
	 *
	 * @var array[\Swoole\Coroutine\Channel]
	 */
	protected static $pool = [ ];
	/**
	 * 连接池容量限制
	 *
	 * @var array
	 */
	protected static $max_size = [ ];
	/**
	 * 记录用户初始化连接的方法
	 *
	 * @var array
	 */
	protected static $call_back = [ ];
	
	/**
	 * RedisPool constructor.
	 *
	 * @param int $size 连接池的尺寸
	 */
	public static function init(callable $callback, array $config, $size = 0) {
		if(self::isInit($config)) {
			return;
		}
		$id = self::id($config);
		
		switch (true) {
			//形参优先
			case $size > 0 :
				break;
			//配置连接池参数
			case isset($config ['connection_pool_size']) && $config ['connection_pool_size'] > 0 :
				$size = intval($config ['connection_pool_size']);
				break;
			//默认连接池容量
			default :
				$size = 8;
				break;
		}
		self::$max_size [$id] = $size;
		self::console("【 初始化 】容量:{$size} {$id}");
		self::$pool [$id] = new Channel($size);
		self::$call_back [$id] = $callback;
		self::createClient($callback, $config);
	}
	
	/**
	 * 关闭连接池
	 */
	public static function close(array $config) {
		$id = self::id($config);
		if(! isset(self::$pool [$id])) {
			return false;
		}
		self::$pool [$id]->close();
		unset(self::$pool [$id], self::$call_back [$id]);
	}
	
	/**
	 * 根据配置生成唯一id
	 *
	 * @param array $config
	 * @return string
	 */
	public static function id(array $config) {
		return md5(serialize($config));
	}
	
	/**
	 * 判断是否初始化过
	 *
	 * @return boolean
	 */
	public static function isInit(array $config) {
		$id = self::id($config);
		return isset(self::$pool [$id]) ? $id : false;
	}
	
	/**
	 * 创建客户端连接
	 */
	protected static function createClient(callable $callback, array $config) {
		$id = self::id($config);
		self::$call_back [$id] = $callback;
		for($i = 1; $i <= self::$max_size [$id]; $i ++) {
			try {
				$connection = $callback();
				if(isset($connection) && is_object($connection)) {
					$res = self::put($connection, $config);
					$res = $res ? '成功' : '失败';
					self::console("【 初始化 】增加连接{$i} {$res} {$id}");
				}
			} catch (\PDOException | \Exception $e) {
				//超过服务器支持连接数
				if(preg_match('/Too many connections/isU', $e->getMessage())) {
					self::console("【 初始化 】已超过服务器支持连接数,不再创建,当前第{$i}个连接 {$id}");
					return;
				}
				self::exception($e);
			}
		}
	}
	
	/**
	 */
	protected static function exceptions($msg) {
		return (bool)preg_match('/Unknown/isU', $msg);
	}
	
	/**
	 *
	 * @param \Exception $e
	 * @throws \Exception
	 */
	protected static function exception(\Exception $e) {
		if(self::exceptions($e->getMessage())) {
			self::console("【 初始化 】创建连接异常" . $e->getMessage() . ",不再创建连接");
			throw new \Exception($e->getTraceAsString());
			return;
		}
	}
	
	/**
	 * 异常情况下增加连接
	 */
	public static function add(array $config, $size = 1) {
		$id = self::id($config);
		for($i = 1; $i <= $size; $i ++) {
			try {
				if(! isset(self::$call_back [$id])) {
					throw new \exception('请先初始化连接池');
				}
				$callback = self::$call_back [$id];
				$connection = $callback();
				if(isset($connection) && is_object($connection)) {
					$res = self::$pool [$id]->push($connection);
					
					$length = self::$pool [$id]->length();
					self::console("【异常补充】增加连接{$i},{$res},当前剩余{$length} {$id}");
				}
			} catch (\PDOException | \Exception $e) {
				if(self::exceptions($e->getMessage())) {
					throw new \Exception($e->getMessage());
				}
			}
		}
	}
	
	/**
	 * 归还连接
	 *
	 * @param \PDO|\Redis|\Swoole\Coroutine\MySQL|\Swoole\Coroutine\Redis $connection
	 */
	public static function put($connection, array $config) {
		$id = self::id($config);
		if(! isset(self::$pool [$id])) {
			return false;
		}
		
		//指定时间内没有push成功（某些情况可能补充连接导致连接超出连接池数量），则放弃，只保留连接池内数量连接
		$result = self::$pool [$id]->push($connection, 0.1);
		
		$length = self::$pool [$id]->length();
		self::console("【归还连接】连接{$result},当前剩余{$length} {$id}");
		return $result;
	}
	
	/**
	 * 获取可用连接
	 *
	 * @return mixed
	 */
	public static function get(array $config, $timeout = 0) {
		$id = self::id($config);
		if(! isset(self::$pool [$id])) {
			return false;
		}
		//【暂时禁止默认浮动连接，部分情况导致段错误】检查忙闲状态，适当浮动部分连接
		if($timeout == 0) {
// 			$status = self::$pool [$id]->stats();
// 			if($status ['consumer_num'] / self::$pool [$id]->capacity > 1.5) {
// 				$timeout = 0.5;
// 			}
		}
		
		$connection = self::$pool [$id]->pop($timeout);
		
		$length = self::$pool [$id]->length();
		self::console("【获取连接】当前剩余{$length} {$id}");
		// connection居然可能是NULL
		if(! $connection) {
			// 特殊情况下，补充连接
			if(self::$pool [$id]->isEmpty()) {
				self::console("【自动补充】{$timeout}内获取连接失败 {$id}");
				self::add($config);
			}
			$connection = self::get($config, 0);
		}
		return $connection;
	}
	
	/**
	 * 生成屏幕日志
	 *
	 * @param mixed $log
	 * @return string
	 */
	public static function console($log) {
		if(is_array($log) or is_object($log)) {
			$log = json_encode($log, JSON_UNESCAPED_UNICODE);
		}
		$time = explode(' ', microtime());
		$time = date('Y-m-d H:i:s') . " {$time ['0']}";
		$cid = \Swoole\Coroutine::getCid();
		$log = "{$time} [{$cid}] {$log}" . PHP_EOL;
		echo $log;
		return $log;
	}
}