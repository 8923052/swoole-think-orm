# swoole-think-orm
适应swoole协程环境运行的think-orm

# 安装：
composer require prince/swoole-think-orm

# 使用：和think-php orm使用一致，详见文档
Db类用法：
~~~php
use think\Db;
// 数据库配置信息设置（全局有效）
Db::setConfig(['数据库配置参数（数组）']);
// 进行CURD操作
Db::table('user')
	->data(['name'=>'thinkphp','email'=>'thinkphp@qq.com'])
	->insert();	
Db::table('user')->find();
Db::table('user')
	->where('id','>',10)
	->order('id','desc')
	->limit(10)
	->select();
Db::table('user')
	->where('id',10)
	->update(['name'=>'test']);	
Db::table('user')
	->where('id',10)
	->delete();
~~~

其它操作参考TP5.1的完全开发手册[数据库](https://www.kancloud.cn/manual/thinkphp5_1/353998?_blank)章节

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
