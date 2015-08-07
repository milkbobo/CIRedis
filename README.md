# CodeIgniter框架连接操作Redis的扩展库。

### 直接使用Redis方法

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

- 使用Redis实例的 `colse()` 方法或redisdb库的 `close()` 方法关闭连接。

``` php
$r->close();               //使用Redis实例的方法

$this->redisdb->close();   //使用redisdb库的方法
```

### 缓存数据

- 将文件覆盖到CodeIgniter框架的application目录下。
- 在配置文件中设置相关参数信息。其中 `hostname` 是必填项。
- 在控制器中载入 `redis_cache` 类库。

---

**`insert(string $sql, [array $keys])`**

该方法执行一个 `INSERT` SQL语句，将数据插入到数据库。如果指定了关键字数组 `$keys` ，将对受指定关键字约束的缓存信息标记为需要更新。

**返回值：** 返回插入数据的主键ID。

``` php
$this -> load -> library('redis_cache');
$sql= 'insert into tablename (name) values ("some values")';
$insert_id = $this -> redis_cache -> insert($sql1, array('k1','k2'));
```

---

**`update(string $sql, [array $keys])`**

该方法可执行任何SQL语句（包括 `SELECT ` ），如果指定了关键字数组 `$keys` ，将对受指定关键字约束的缓存信息标记为需要更新。

**返回值：** 返回执行SQL语句影响数据库中数据条数（仅 `INSERT`、`UPDATE` 和 配置数据库后的 `DELETE` 有效，其他总是返回 `0` ）。

``` php
$this -> load -> library('redis_cache');
$sql= 'delete from tablename';
$insert_id = $this -> redis_cache -> update($sql1, array('k1'));
```

---

**`get(string $sql, [array $keys])`**

该方法执行一个SQL查询语句（必须是 `SELECT` 语句），并根据缓存是否需要更新判断是直接取出缓存数据，还是去数据库中读数据再存到缓存里。如果指定了关键字数组 `$keys` ，仅在需要新建缓存的时候，将缓存信息和指定的关键字关联。

**返回值：** 返回查询的信息数组。

``` php
$this -> load -> library('redis_cache');
$sql= 'select * from tablename';
print_r($this -> redis_cache -> get($sql1));

//输出信息数组
//Array ([0] => Array ( [id] => 3 [name] => name1 ) [1] => Array ( [id] => 4 [name] => name2 ) )
```

---

> **Note：** 在调用 `update()` 和 `insert()` 方法时，只有指定的关键字所约束的缓存信息才会被标记为需要更新。被标记为不需要更新的缓存信息通过 `get()`方法返回的仍是缓存中的数据，即使这些数用已经在数据库中更改。