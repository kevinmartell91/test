<?php

namespace AppBundle\Service;

use Predis;

/**
* Here you have to implement a CacheService with the operations above.
* It should contain a failover, which means that if you cannot retrieve
* data you have to hit the Database.
**/
class CacheService 
{
    public $redis;

    public function __construct($host, $port, $prefix)
    {
        $this->redis = new Predis\Client("tcp://$host:$port/");
    }

    public function get($key)
    {
         try {
             //return  json_encode(($this->redis->lrange($key,0,-1)));
             return  (unserialize($this->redis->lrange($key,0,-1)));

         } catch (Exception $e) {
            echo 'exception';
             return 'failover';
         }
    }

    public function set($key, $value)
    {
        //$this->redis->set($key,serialize($value));

        $this->redis->rpush($key,serialize($value));
        $this->redis->expire($key,60);
    }

    public function del($key)
    {
        while ($this->redis->llen('customers')) {
            $this->redis->del($key);
        }
        
    }
}
