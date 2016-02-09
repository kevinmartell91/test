<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use AppBundle\Service;


class CustomersController extends Controller
{

    /**
     * @Route("/customers/")
     * @Method("GET")
     */
    public function getAction()
    {
        $cacheService = $this->get('cache_service');
        
        $customers = null;

        if($cacheService !== null){

            if($cacheService->redis !== null){
                //get all customer from cacheServer
                $customers = $cacheService->get('customer_');

                if($customers !== null || !empty($customers)){
                    echo "left in cache : " . count($customers) . "   ---  Count :" .  $cacheService->get_count('customers') . "\n";
                    if(count($customers) < $cacheService->get_count('customers')){
                        //force to hit database since cacheServer has different number of customers than stored in database.
                        $customers = null;
                    }
                }
            }

        } 

        if ( empty($customers) || $customers === null ){
            
            $database = $this->get('database_service')->getDatabase();

            if($database !== null){

                $customers = $database->customers->find();

                if($customers !== null || !empty($customers)){

                    $customers = iterator_to_array($customers,true);
                    
                    echo "\n\n =============   retrive data from database    =========== \n\n";
                    
                    if($cacheService !== null){

                        if($cacheService->redis !== null){  
                            //save in cache  
                            foreach ($customers as $customer) {

                                $cacheService->set('customer_' . $customer["_id"] , $customer);
                                
                            }
                        }     
                    }    

                } 
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
        
        if($database !== null){

            $customers = json_decode($request->getContent());

            if (empty($customers)) {

                return new JsonResponse(['status' => 'No donuts for you'], 400);

            }

            foreach ($customers as $customer) {

                $database->customers->insert($customer);
    
                $cacheService = $this->get('cache_service');

                if($cacheService !== null) {

                    if($cacheService->redis !== null){
                        //save in cache  
                        $cacheService->set('customer_' . $customer->_id, $customer);
                        $cacheService->incr_count('customers');
                    }

                }

            }   

            return new JsonResponse(['status' => 'Customers successfully created'],200);

        }       

        return new JsonResponse(['status' => 'Customers not successfully created'],500);
    }

    /**
     * @Route("/customers/")
     * @Method("DELETE")
     */
    public function deleteAction()
    {
        $database = $this->get('database_service')->getDatabase();

        if($database !== null){ 

            $database->customers->drop();
            
            $cacheService = $this->get('cache_service');

            if($cacheService !== null){

                if($cacheService->redis !== null){ 

                    $cacheService->del('customer');

                }

            }

            return new JsonResponse(['status' => 'Customers successfully deleted'],200);

        }


        return new JsonResponse(['status' => 'Customers not successfully deleted'],500);
    }
}