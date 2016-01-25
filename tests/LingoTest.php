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
		
		$lingo->getProjects();
		$lingo->setProject(0);

		$retval = $lingo->getResources();
		var_dump($retval);
	}

	public function testProjects()
	{
		$lingo = new Lingo($this->apiKey, $this->user);

		$lingo->getProjects();
		$lingo->setProject(0);

		$response = $lingo->getProjects();
		var_dump($response);
	}

	public function testSetProject()
	{
		$lingo = new Lingo($this->apiKey, $this->user);
		$projects = $lingo->projects();
		$lingo->setProject(0);

		var_dump($lingo->currentProject);
	}

	public function testPushFiles()
	{
		$lingo = new Lingo($this->apiKey, $this->user);

		$lingo->setWorkingDir('resources/lang/');

		$projects = $lingo->getProjects();
	
		$lingo->setProject('Api6');

		$lingo->addLanguage('hr');

		$retval = $lingo->pushFiles();
		var_dump($retval);
	}

	public function testPullFiles()
	{
		$lingo = new Lingo($this->apiKey, $this->user);

		$lingo->setWorkingDir('resources/lang/', false);

		$lingo->getProjects();
		$lingo->setProject(0);

		$lingo->getResources();
		$lingo->setResource(0);

       	$retval = $lingo->pullFiles();
       	var_dump($retval);
	}
}