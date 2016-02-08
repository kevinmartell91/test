<?php

namespace AppBundle\Service;

use \MongoClient;
use \MongoDB;

class DatabaseService
{
    protected $database;

    public function __construct($host, $port, $database)
    {

        $connectionString = "mongodb://$host:$port/";
        
        if (filter_var($host,FILTER_VALIDATE_IP) && is_int($port) ){
            
            $mongoClient = new MongoClient($connectionString);
            
            $this->setDatabase($mongoClient->selectDB($database));
            
        }
                    
    }

    public function setDatabase(MongoDB $database)
    {
        $this->database = $database; 
    }

    public function getDatabase()
    {
        return $this->database;
    }
}
