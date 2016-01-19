<?php 
use Chipolo\Lingo\Lingo;
 
class LingoTest extends PHPUnit_Framework_TestCase {
 
	public function testNachHasCheese()
	{
		$lingo = new Lingo;
		$this->assertTrue($lingo->forTest());
	}

}