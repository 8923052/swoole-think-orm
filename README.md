# swoole-think-orm
适应swoole协程环境运行的think-orm

# 为什么有这个项目：

```
1、swoole环境操作数据库，手动写连接数据库的语句非常不方便，需要有orm提高效率

2、swoole常驻进程环境，协程环境和fpm运行环境极大不同，现有fpm环境下的orm在常驻进程环境会有各种问题。

3、已有的swoole环境下的orm都是新造的轮子，需要去学习orm用法。兼容think-orm，用法完全相同，无需学习新的用法，另外可以少量修改或不修改迁移使用think-orm的部分逻辑。

4、已实现无感数据库连接池，无感知获取连接，回收连接。

5、可以作为组件，composer引入单独使用，自带连接池功能，简单方便。
```

# 测试环境：

建议使用docker环境，需要安装docker,docker-compose

项目内有docker-compose.yml

运行docker-compose up -d

然后进入容器docker exec -it swoole bash

# 安装：
composer require prince/swoole-think-orm

# 使用：和think-php orm使用一致，详见文档
Db类用法：
~~~php
\think\facade\Db::connect($config)

\think\facade\Db::table('user')->find();
~~~

其它操作参考TP5.1的完全开发手册[数据库](https://www.kancloud.cn/manual/thinkphp5_1/353998)章节

# 特性：
1、支持swoole协程环境。

2、惰性连接池，直到查询时才会创建连接池，可以在配置中设置维持连接数。

3、支持根据配置创建多个连接池，支持关闭连接池。

4、连接池支持事务处理，事务结束前不会更换连接。

5、支持orm游标查询，处理大量数据。

6、“无感”连接池，连接池使用无感知，自动获取连接，自动回收连接，无需手动处理。

7、兼容think-orm 2.0使用，无需学习新的orm用法，无任何心智负担。

# 注意：
1、不建议使用模型、事件回调，暂未对相关功能协程化处理。

2、log，查询次数等数据暂不可用。

3、暂时不支持execute($sql)
