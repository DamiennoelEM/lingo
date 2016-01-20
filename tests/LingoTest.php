<?php 
use Chipolo\Lingo\Lingo;
 
class LingoTest extends PHPUnit_Framework_TestCase {

	public $apiKey;
	public $user;

	public function __construct()
	{
		$this->apiKey  	= '632778495ac92a60a6f8c44c0c702ea677ef87d5de58b6b47d4ce03c2623d20e';
		$this->user  	= 'marko-zagar';
	}
 
	public function testResources()
	{
		$lingo = new Lingo($this->apiKey, $this->user);
		$projects = $lingo->projects();
		$lingo->setProject(0);
		$response = $lingo->resources();
		var_dump($response);
	}

	public function testProjects()
	{
		$lingo = new Lingo($this->apiKey, $this->user);

		$projects = $lingo->projects();
		$lingo->setProject(0);

		$response = $lingo->projects();
		var_dump($response);
	}

	public function testSetProjects()
	{
		$lingo = new Lingo($this->apiKey, $this->user);
		$projects = $lingo->projects();
		$lingo->setProject(0);
		$this->assertEquals(strtolower($lingo->currentProject['title']), 'testproject');
	}

	public function testMakeCvs()
	{
		$lingo = new Lingo($this->apiKey, $this->user);
		$p = $lingo->scanLangDir('resources/lang/');
		var_dump($p);
	}

	public function testSendFile()
	{
		$lingo = new Lingo($this->apiKey, $this->user);
		$projects = $lingo->projects();
		$lingo->setProject(0);
		$retval = $lingo->startPushFiles('resources/lang/');
		var_dump($retval);
	}

}