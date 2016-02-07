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
        try {
            echo "__construct cache";
            $this->redis = new Predis\Client("tcp://$host:$port/");
                    
        } catch (Exception $e) {
            
        }        
    }

    public function get($key)
    {
        try {
            echo "\n =============   retrive data from cacheServer    =========== \n\n";
            //return  json_encode(($this->redis->lrange($key,0,-1)));
            //return  (($this->redis->keys($key . '_??')));
            $data = $this->redis->keys($key . '_*');
            $arrayData =  array();
            foreach ($data as $key => $value) {
                $des = unserialize($this->redis->get($value));
                //var_dump( $des);
                array_push($arrayData, $des);
            }   
            return $arrayData;

        } catch (Exception $e) {
            echo 'exception';
            return 'failover';
        }
    }

    public function set($key, $value)
    {
        try {
            $this->redis->set($key,serialize($value));
            $this->redis->expire($key,60);
        } catch (Exception $e) {
            echo 'exception';
            return 'failover';
        }
    }

    public function del($key)
    {
        $data = $this->redis->keys($key . '_*');
        foreach ($data as $keyy => $value) {
            $this->redis->del($value);
        }   
    }
}
