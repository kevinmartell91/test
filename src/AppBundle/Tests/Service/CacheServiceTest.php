<?php

namespace AppBundle\Test\Service;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use AppBundle\Service\CacheService;

class cacheServiceTest extends WebTestCase{

	protected $cacheService ;

	public function setUp() 
	{
		$this->cacheService = new CacheService("127.0.0.1",6379,"tcp");
	}

	public function testGetKeyNonExist()
	{
		$this->assertNull($this->cacheService->get('NON_EXISTING_KEY'));
	}

	public function testGetKeyNullValue()
	{
		$this->assertNull($this->cacheService->get(null));
	}

	public function testGetKeyEmptyValue()
	{
		$this->assertNull($this->cacheService->get(''));
	}

	public function testSetKeyValues()
	{
		$this->assertTrue($this->cacheService->set('customer_u000000000000000000','feafaffdf426536753753'));
	}

	/**
     * @depends testSetKeyValues
     */
	public function testGetKeyReturnArray()
	{
		$this->assertInternalType("array",$this->cacheService->get('customer_'));
	}
	
	public function testGetKey()
	{
		$this->assertInternalType("int",(int)$this->cacheService->get_count('customers'));
	}

	public function testSetKeyNullValues()
	{
		$this->assertFalse($this->cacheService->set(null,null));
	}

	public function testSetKeyEmptyValues()
	{
		$this->assertFalse($this->cacheService->set('',''));
	}

	public function testDelKeyNonExisting()
	{
		$this->assertEquals($this->cacheService->del('NON_EXISTING_KEY'),0);
	}

	public function testDelKeyEmptyValue()
	{
		$this->assertFalse($this->cacheService->del(''));
	}

	/**
     * @depends testSetKeyValues
     */
	public function testDelKeyCorrectValue()
	{
		$this->assertTrue($this->cacheService->del('customer'));
	}

	
}