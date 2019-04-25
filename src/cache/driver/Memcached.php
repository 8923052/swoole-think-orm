<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\cache\driver;

use think\cache\Driver;

/**
 * Memcached缓存类
 */
class Memcached extends Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 11211,
        'expire'     => 0,
        'timeout'    => 0, // 超时时间（单位：毫秒）
        'prefix'     => '',
        'username'   => '', //账号
        'password'   => '', //密码
        'option'     => [],
        'serialize'  => true,
        'tag_prefix' => 'tag_',
    ];

    /**
     * 架构函数
     * @access public
     * @param  array $options 缓存参数
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('memcached')) {
            throw new \BadFunctionCallException('not support: memcached');
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->handler = new \Memcached;

        if (!empty($this->options['option'])) {
            $this->handler->setOptions($this->options['option']);
        }

        // 设置连接超时时间（单位：毫秒）
        if ($this->options['timeout'] > 0) {
            $this->handler->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->options['timeout']);
        }

        // 支持集群
        $hosts = (array) $this->options['host'];
        $ports = (array) $this->options['port'];
        if (empty($ports[0])) {
            $ports[0] = 11211;
        }

        // 建立连接
        $servers = [];
        foreach ($hosts as $i => $host) {
            $servers[] = [$host, $ports[$i] ?? $ports[0], 1];
        }

        $this->handler->addServers($servers);

        if ('' != $this->options['username']) {
            $this->handler->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->handler->setSaslAuthData($this->options['username'], $this->options['password']);
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has($name): bool
    {
        $key = $this->getCacheKey($name);

        return $this->handler->get($key) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $this->readTimes++;

        $result = $this->handler->get($this->getCacheKey($name));

        return false !== $result ? $this->unserialize($result) : $default;
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return bool
     */
    public function set($name, $value, $expire = null): bool
    {
        $this->writeTimes++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        if (!empty($this->tag) && !$this->has($name)) {
            $first = true;
        }

        $key    = $this->getCacheKey($name);
        $expire = $this->getExpireTime($expire);
        $value  = $this->serialize($value);

        if ($this->handler->set($key, $value, $expire)) {
            isset($first) && $this->setTagItem($key);
            return true;
        }

        return false;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string $name 缓存变量名
     * @param  int    $step 步长
     * @return false|int
     */
    public function inc(string $name, int $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        if ($this->handler->get($key)) {
            return $this->handler->increment($key, $step);
        }

        return $this->handler->set($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string $name 缓存变量名
     * @param  int    $step 步长
     * @return false|int
     */
    public function dec(string $name, int $step = 1)
    {
        $this->writeTimes++;

        $key   = $this->getCacheKey($name);
        $value = $this->handler->get($key) - $step;
        $res   = $this->handler->set($key, $value);

        return !$res ? false : $value;
    }

    /**
     * 删除缓存
     * @access public
     * @param  string       $name 缓存变量名
     * @param  bool|false   $ttl
     * @return bool
     */
    public function rm(string $name, $ttl = false): bool
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return false === $ttl ?
        $this->handler->delete($key) :
        $this->handler->delete($key, $ttl);
    }

    /**
     * 清除缓存
     * @access public
     * @return bool
     */
    public function clear(): bool
    {
        if (!empty($this->tag)) {
            foreach ($this->tag as $tag) {
                $this->clearTag($tag);
            }

            return true;
        }

        $this->writeTimes++;

        return $this->handler->flush();
    }

    public function clearTag(string $tag): void
    {
        // 指定标签清除
        $keys = $this->getTagItems($tag);

        $this->handler->deleteMulti($keys);

        $tagName = $this->getTagKey($tag);
        $this->rm($tagName);
    }

    /**
     * 更新标签
     * @access protected
     * @param  string $name 缓存标识
     * @return void
     */
    protected function setTagItem(string $name): void
    {
        if (!empty($this->tag)) {
            foreach ($this->tag as $tag) {
                $tagName = $this->getTagKey($tag);
                if ($this->handler->has($tagName)) {
                    $this->handler->append($tagName, ',' . $name);
                } else {
                    $this->handler->set($tagName, $name);
                }
            }

            $this->tag = null;
        }
    }

    /**
     * 获取标签包含的缓存标识
     * @access public
     * @param  string $tag 缓存标签
     * @return array
     */
    public function getTagItems(string $tag): array
    {
        $tagName = $this->getTagKey($tag);
        return explode(',', $this->handler->get($tagName));
    }
}
