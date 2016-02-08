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

    public function testCreateCustomersNull()
    {
        $customers = null;
        $customers = json_encode($customers);

        $this->client->request('POST', '/customers/', [], [], ['CONTENT_TYPE' => 'application/json'], $customers);

        $this->assertFalse($this->client->getResponse()->isSuccessful());
    }

    public function testGetActionCustomersEmpty() 
    {
        $this->client->request('GET', '/customers/');

        $this->assertEquals($this->client->getResponse()->getContent(),'[]');
    }

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

    public function testGetActionCustomers() 
    {
        $this->client->request('GET', '/customers/');

        $this->assertNotNull($this->client->getResponse()->getContent());
        //$this->assertEquals(($this->client->getResponse()->getContent()),'[{"name":"Alex","age":18,"_id":{"$id":"56b915246803fa4f618b4569"}},{"name":"Leandro","age":26,"_id":{"$id":"56b915246803fa4f618b4567"}},{"name":"Marcelo","age":30,"_id":{"$id":"56b915246803fa4f618b4568"}}]');
        //$this->assertEquals(($this->client->getResponse()->getContent()),'[{"name":"Leandro","age":26,"_id":{"$id":"56b9158e6803fa78618b4567"}},{"name":"Marcelo","age":30,"_id":{"$id":"56b9158e6803fa78618b4568"}},{"name":"Alex","age":18,"_id":{"$id":"56b9158e6803fa78618b4569"}}]');
    }

    public function testDeleteCustomers()
    {
        
        $this->client->request('DELETE', '/customers/');

        $this->assertTrue($this->client->getResponse()->isSuccessful()); 
    }
}
