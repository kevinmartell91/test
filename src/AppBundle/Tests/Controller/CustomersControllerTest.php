<?php
//<argument>tcp</argumen
namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomersControllerTest extends WebTestCase
{
    protected $client;

    public function setUp()
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
    }

    public function testCreateCustomersEmpty()
    {
        $customers = [];
        $customers = json_encode($customers);

        $this->client->request('POST', '/customers/', [], [], ['CONTENT_TYPE' => 'application/json'], $customers);

        $this->assertFalse($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateCustomersEmpty
     */
    public function testCreateCustomersNull()
    {
        $customers = null;
        $customers = json_encode($customers);

        $this->client->request('POST', '/customers/', [], [], ['CONTENT_TYPE' => 'application/json'], $customers);

        $this->assertFalse($this->client->getResponse()->isSuccessful());
    }

    /**
     * @depends testCreateCustomersNull
     */
    /////////
    public function testGetActionCustomersNotNull() 
    {
        $this->client->request('GET', '/customers/');

        $this->assertNotNull($this->client->getResponse()->getContent());
    }

    /**
     * @depends testGetActionCustomersNotNull
     */
    public function testCreateCustomers()
    {
        $customers = [
            ['name' => 'Leandro', 'age' => 26],
            ['name' => 'Marcelo', 'age' => 30],
            ['name' => 'Alex', 'age' => 18],
        ];
        $customers = json_encode($customers);

        $this->client->request('POST', '/customers/', [], [], ['CONTENT_TYPE' => 'application/json'], $customers);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
    /**
     * @depends testCreateCustomers
     */
    public function testGetActionCustomersStatusCode() 
    {
        $this->client->request('GET', '/customers/');

        $this->assertEquals($this->client->getResponse()->getStatusCode(),200);
    }

    /**
     * @depends testGetActionCustomersStatusCode
     */
    public function testDeleteCustomers()
    {
        
        $this->client->request('DELETE', '/customers/');

        $this->assertEquals($this->client->getResponse()->getStatusCode(),200); 

    }

}
