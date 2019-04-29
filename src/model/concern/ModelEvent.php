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
declare (strict_types = 1);

namespace think\model\concern;

use think\Container;
use think\exception\ModelEventException;
use think\facade\Db;

/**
 * 模型事件处理
 */
trait ModelEvent
{
    /**
     * 模型事件观察
     * @var array
     */
    protected $observe = ['AfterRead', 'BeforeWrite', 'AfterWrite', 'BeforeInsert', 'AfterInsert', 'BeforeUpdate', 'AfterUpdate', 'BeforeDelete', 'AfterDelete', 'BeforeRestore', 'AfterRestore'];

    /**
     * 模型事件观察者类名
     * @var string
     */
    protected $observerClass;

    /**
     * Event
     * @var array
     */
    protected $event = [];

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 注册一个模型观察者
     *
     * @param  string $class 观察者类
     * @return void
     */
    protected function observe(string $class): void
    {
        foreach ($this->observe as $event) {
            $call = 'on' . $event;

            if (method_exists($class, $call)) {
                $instance = Container::getInstance()->invokeClass($class);

                $this->event[$event][] = [$instance, $call];
            }
        }
    }

    /**
     * 当前操作的事件响应
     * @access protected
     * @param  bool $event  是否需要事件响应
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;
        return $this;
    }

    /**
     * 触发事件
     * @access protected
     * @param  string $event 事件名
     * @return bool
     */
    protected function trigger(string $event): bool
    {
        if (!$this->withEvent) {
            return true;
        }

        $call   = 'on' . Db::parseName($event, 1);
        $result = true;

        try {
            if (method_exists(static::class, $call)) {
                $callback = [static::class, $call];
            } elseif ($this->observerClass && method_exists($this->observerClass, $call)) {
                $callback = [$this->observerClass, $call];
            }

            if (isset($callback)) {
                $result = Container::getInstance()->invoke($callback, [$this]);
            }

            return false === $result ? false : true;
        } catch (ModelEventException $e) {
            return false;
        }
    }
}
