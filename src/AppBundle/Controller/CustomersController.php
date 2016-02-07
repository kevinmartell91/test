<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use AppBundle\Service;


use Predis;


class CustomersController extends Controller
{

    /**
     * @Route("/customers/")
     * @Method("GET")
     */
    public function getAction()
    {
        $cacheService = $this->get('cache_service');
        

        $customers = $cacheService->get('customers');

        if (empty($customers) || $customers === 'failover') {
            echo "retrive data from database//////////////////////";
            $database = $this->get('database_service')->getDatabase();
            $customers = $database->customers->find();
            $customers = iterator_to_array($customers,true);
            
            //save on  
            foreach ($customers as $customer) {
                
                //$cacheService->set($customer["_id"],serialize($customer));
                $cacheService->set('customers',$customer);
            }
        }

        return new JsonResponse($customers);
    }

    /**
     * @Route("/customers/")
     * @Method("POST")
     */
    public function postAction(Request $request)
    {
        $database = $this->get('database_service')->getDatabase();
        $customers = json_decode($request->getContent());

        if (empty($customers)) {
            return new JsonResponse(['status' => 'No donuts for you'], 400);
        }
        
        $cacheService = $this->get('cache_service');

        foreach ($customers as $customer) {
            $data = $cacheService->get($customer["_id"]);
            if(data === false){
                //do slowly query
                $database->customers->insert($customer);
                //save in cache server
                //$cacheService->set($customer["_id"],serialize($customer));
                $cacheService->set('customers',$custumer);
            }else{
                //save in log => customer already created
            }

        }

        return new JsonResponse(['status' => 'Customers successfully created']);
    }

    /**
     * @Route("/customers/")
     * @Method("DELETE")
     */
    public function deleteAction()
    {
        $database = $this->get('database_service')->getDatabase();
        $database->customers->drop();

        $cacheService->$this->get('cache_service');
        $cacheService->del('customers');

        return new JsonResponse(['status' => 'Customers successfully deleted']);
    }
}