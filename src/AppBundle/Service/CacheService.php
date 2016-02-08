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
            
            $this->redis = new Predis\Client("tcp://$host:$port/");
                    
        } catch (Exception $e) {
            //save in log
            $this->redis = null;
        }        
    }

    public function get($key)
    {
        try {
            echo "\n =============   retrive data from cacheServer    =========== \n\n";

            $data = $this->redis->keys($key . '_*');
            $arrayData =  array();
            foreach ($data as $key => $value) {
                $customer = unserialize($this->redis->get($value));
                array_push($arrayData, $customer);
            }   
            return $arrayData;

        } catch (Exception $e) {
            //save in log
        }
    }

    public function set($key, $value)
    {
        try {
           
            $this->redis->set($key,serialize($value));
            $this->redis->expire($key,5);

        } catch (Exception $e) {
            //save in log 
        }
    }

    public function del($key)
    {
        try {
            
            $data = $this->redis->keys($key . '_*');
            
            foreach ($data as $keyy => $value) {
                $this->redis->del($value);
            }

            if($this->redis->exists('customers_count')) $this->redis->del('customers_count') ;  

        } catch (Exception $e) {
            //save in log       
        } 
    }

    public function get_num_Customers($key)
    {
        try {
            if($this->redis->exists($key)) {
                return $this->redis->get($key);
            }else{
                return  0;
            }
        } catch (Exception $e) {
            //save in log
        }
    }
    public function incr_count_cust($key){

        try {
            if($this->redis->exists($key)) {
                 $this->redis->incr($key);  
            }else{
                $this->redis->set($key,1);
                $this->redis->expire($key,3600);
            }
            
        } catch (Exception $e) {
            //save in log
        }

    }

}
