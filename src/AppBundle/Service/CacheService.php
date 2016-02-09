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

        $url = "tcp://$host:$port/";

        if (filter_var($host,FILTER_VALIDATE_IP) && is_int($port) ){
            
            $this->redis = new Predis\Client($url);
            
        } 
        
    }

    public function get($key)
    {

        if($key !== null && !empty($key) && $this->redis !==null){

            echo "\n\n =============   retrive data from CACHE_SERVER    =========== \n\n";

            $data_keys = $this->redis->keys($key . '*');

            $arrayData =  array();

           if($data_keys !== null && !empty($data_keys)){

                foreach ($data_keys as $key => $value) {

                    $data = unserialize($this->redis->get($value));
                    
                    if($data !== null &&  !empty($data))

                        array_push($arrayData, $data);

                }   
                
                return $arrayData;
           }

        }

        return null;

    }

    public function set($key, $value)
    {
        if($key !== null && $value !==null &&
            !empty($key) && !empty($value)) { 

             if( $this->redis !==null){

                $this->redis->set($key,serialize($value));

                $this->redis->expire($key,5);

                return true;
            }
        }

        return false;

    }

    public function del($key)
    {
        if($key !== null && !empty($key) && $this->redis !==null){

            $data = $this->redis->keys($key . '_*');
            
            if($data !== null && !empty($data) ){

                foreach ($data as $_key => $value) {

                    $this->redis->del($value);

                }

                if($this->redis->exists('customers')) 

                    $this->redis->del('customers') ; 

                return true;
            }

        }

        return false;

    }

    public function get_count($key)
    {
        
        if($key !== null && !empty($key) && $this->redis !==null){

            if($this->redis->exists($key)) {

                return $this->redis->get($key);
            }
         
        }

        return 0;

    }

    public function incr_count($key)
    {

        if($key !== null && !empty($key) && $this->redis !==null){

            if($this->redis->exists($key)) {

                 $this->redis->incr($key);

            }else{

                $this->redis->set($key,1);

                $this->redis->expire($key,3600);
            }
        }

    }

}
