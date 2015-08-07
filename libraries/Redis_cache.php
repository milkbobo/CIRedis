<?php
/**
 * CIRedis
 *
 * 在CodeIgniter框架中，根据sql缓存数据的扩展库。
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2015 - 2016, Yuan Xibin
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CIRedis
 * @author	Yuan Xibin
 * @copyright	Copyright (c) 2015 - 2016, Yuan Xibin
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://github.com/yxbunix/CIRedis
 * @since	Version 1.0.0
 * @filesource
 */
defined('BASEPATH') or exit('No direct script access allowed');
require_once 'Redisdb.php';
/**
 * Redis_cache Class
 *
 * 根据sql新建、读取、更改、删除缓存数据。
 *
 * 新建缓存信息时，在Redis中建立一个K-V键值对，其中K是对应的SQL语句（仅SELECT语句），V是从数据库查询的数据。
 * 同时将该SQL语句关联到多个关键字
 * 并建立一个针对该SQL语句的缓存版本号和数据库版本号，初始值为1。
 *
 * 当执行修改、删除、插入数据操作时，先根据指定的关键字，对所约束的SQL的数据库版本号进行自加1操作。然后执行数据库操作。
 *
 * 当执行查询数据操作时，先根据SQL语句查找是否有对应的缓存数据。如果没有，执行数据库查询并将返回的信息存储到缓存中。
 * 如果有缓存信息，判断缓存版本号和数据库版本号是否一致。
 * 不一致时，执行数据库查询并更新缓存数据同时将缓存版本号设置为与数据库版本号一致，然后返回数据。
 * 一致时，返回缓存数据。
 *
 * 读数据时取数据库数据为空，将缓存数据也更新为空，并不会删除对应的缓存K-V键值对。
 *
 * @package CIRedis
 * @subpackage Libraries
 * @category Redis_cache
 * @author Yuan Xibin
 * @link https://github.com/yxbunix/CIRedis
 */
class Redis_cache extends Redisdb
{
    protected $db;
    
    /**
     * 构造函数
     *
     * 连接到Redis服务器，并创建一个数据库对象
     *
     * @return void
     */
    public function __construct()
    {
        parent :: __construct();
        $this -> connect();
        
        if (! isset($this -> CI -> db))
        {
            $this -> CI -> load -> database();
            $this -> db = $this -> CI -> db;
        }
    }
    
    /**
     * 根据sql获得缓存。
     *
     * 如果缓存不存在，查询数据库并缓存数据。
     *
     * 当新建缓存时，会将sql语句关联到关键字的每个元素上。
     * 否则不会更改sql所关联的关键字。
     *
     * @param string $sql 查询SQL语句
     * @param array $keys sql的关键字数组
     *       
     * @return array 返回数据数组。发生错误时返回空数据。
     */
    public function get($sql, $keys = array())
    {
        if (! preg_match('/^SELECT/', strtoupper($sql)))
        {
            return array();
        }
        
        $sql_encode = sha1($sql);
        
        /**
         * 如果缓存存在且是最新的。那就返回该缓存信息。
         *
         * 否则去数据库读数据，存入缓存。
         */
        if ($this -> Redis -> EXISTS($sql_encode) && $this -> is_new($sql_encode))
        {
            return $this -> info_decode($this -> Redis -> get($sql_encode));
        }
        else
        {
            $info = $this -> db -> query($sql) -> result_array();
            
            $this -> set_cache($sql_encode, $info, $keys);
            
            return $info;
        }
    }
    
    /**
     * 执行更新、删除数据操作。
     *
     * 先对所给定的每一个关键字所关联的缓存进行更新数据库版本号操作。
     * 然后操作数据库中的数据。
     *
     * @param string $sql SQL语句
     * @param array $keys 关键字数组
     *       
     * @return int 返回数据的影响条数
     */
    public function update($sql, $keys = array())
    {
        if (! is_array($keys))
        {
            return 0;
        }
        
        if (! $this -> update_cache($keys))
        {
            return 0;
        }
        
        // 执行数据库更新。
        $this -> db -> query($sql);
        
        return $this -> db -> affected_rows();
    }
    
    /**
     * 插入数据
     *
     * @param string $sql 更新SQL语句
     * @param array $keys 关键字数组
     *       
     * @return int 插入成功返回主键ID，否则返回0；
     */
    public function insert($sql, $keys = array())
    {
        if (! preg_match('/^INSERT/', strtoupper($sql)))
        {
            return 0;
        }
        
        return $this -> update($sql, $keys) > 0 ? $this -> db -> insert_id() : 0;
    }
    
    /**
     * 判断缓存中的数据版本是否时最新的。
     *
     * @param string $sql_encode SQL语句
     *       
     * @return boolean 是最新的返回TRUE，否则返回FALSE
     */
    protected function is_new($sql_encode)
    {
        $rversion = (int)$this -> Redis -> get('rversion_' . $sql_encode);
        $dbversion = (int)$this -> Redis -> get('dbversion_' . $sql_encode);
        
        return $rversion > 0 && $dbversion > 0 && $rversion == $dbversion;
    }
    
    /**
     * 设置缓存
     *
     * @param string $sql_encode SQL语句
     * @param array $info 数据数组
     * @param array $keys 关键字数组
     *       
     * @return void
     */
    protected function set_cache($sql_encode, $info, $keys = array())
    {
        
        // 参数验证
        if ($sql_encode == '')
        {
            return FALSE;
        }
        
        // 获得对应SQL的缓存版本和数据库版本
        $rversion = (int)$this -> Redis -> get('rversion_' . $sql_encode);
        $dbversion = (int)$this -> Redis -> get('dbversion_' . $sql_encode);
        
        // 开启Redis事务
        $this -> Redis -> multi();
        
        // 设置数据缓存
        $this -> Redis -> set($sql_encode, $this -> info_encode($info));
        
        /**
         * 设置版本号码。如果版本号存在，对缓存版本加1。否则对缓存版本号和数据库版本号初始化1.
         */
        if ($rversion > 0 && $dbversion > 0)
        {
            $this -> Redis -> set('rversion_' . $sql_encode, $dbversion);
        }
        else
        {
            $this -> Redis -> mset(array(
                    'rversion_' . $sql_encode => 1,
                    'dbversion_' . $sql_encode => 1 
            ));
        }
        
        // 设置关键字
        for ($i = 0; $i < count($keys); $i ++)
        {
            $this -> Redis -> sAdd('set_key_' . $keys[$i], $sql_encode);
        }
        
        // 执行事务
        $this -> Redis -> exec();
    }
    
    /**
     * 修改缓存数据库版本。
     *
     * 更改缓存中对应关键字的所有SQL语句的数据库版本加1。
     *
     * @param array $keys
     *
     * @return boolean 全部更新返回TRUE，部分更新返回FALSE。
     */
    protected function update_cache($keys = array())
    {
        // 已更新数，表示成功更新的条数。
        $affected = 0;
        
        // 需要更新的条数。
        $needNo = 0;
        
        for ($i = 0; $i < count($keys); $i ++)
        {
            // 获得KEY对应的SQL
            $cache_sql = $this -> Redis -> sMembers('set_key_' . $keys[$i]);
            if (is_array($cache_sql) && count($cache_sql) > 0)
            {
                foreach ($cache_sql as $v)
                {
                    if ($this -> Redis -> exists('dbversion_' . $v))
                    {
                        $needNo ++;
                        
                        // 更新数据库版本号成功，已更新数加1
                        $this -> Redis -> incr('dbversion_' . $v) > 0 ? $affected ++ : 0;
                    }
                }
            }
        }
        
        return $affected == $needNo;
    }
    
    /**
     * 对编码了的信息解码成数组。
     *
     * @param string $info 编码了的信息
     *       
     * @return array
     */
    public function info_decode($info)
    {
        return json_decode(base64_decode($info), TRUE);
    }
    
    /**
     * 对信息数组进行编码成字符串。
     *
     * @param string $info 信息数组
     *       
     * @return string
     */
    public function info_encode($info)
    {
        return base64_encode(json_encode($info));
    }
    
    public function __destruct()
    {
        $this -> close();
    }
}