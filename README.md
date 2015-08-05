# CodeIgniter框架连接操作Redis的扩展库。

## 使用说明：

- 将文件覆盖到CodeIgniter框架的application目录下。
- 在配置文件中设置相关参数信息。其中 `hostname` 是必填项。
- 在控制器中载入 `Redisdb` 类库。

``` php
$this->load->library('redisdb');
```

- 使用 `connect` 方法连接数据库。该方法返回一个Redis实例。

``` php
$r = $this->redisdb->connect();
```

- 使用该实例操作Redis。

``` php
$r->set('key','value');  //设置key的值为value
$value = $r->get('key'); //获得key的值，赋值给$value变量
```
