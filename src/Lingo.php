<?php 
namespace Chipolo\Lingo;

use Guzzle\Service\Client;
use League\Csv\Reader;

use Chipolo\Lingo\Support\Arr;
use Chipolo\Lingo\Helpers;

class Lingo 
{
	protected 	$apiKey;
	//private 	$apiLink = 'https://api.lingohub.com/v1/';

	private 	$client;

	protected 	$lingoUser;

	private 	$projects;
	public 		$currentProject;
	public 		$currentProjectName;

	public 		$files = [];
	public 		$lang;
	public 		$rows = ['Title'];

	public function __construct($apiKey, $lingoUser)
	{
		$this->apiKey 		= $apiKey;
		$this->lingoUser 	= $lingoUser;

		$this->client = new Client([
            'exceptions'        => true,
            'redirect.disable'  => true
        ]);

       	$this->lang   = ['en', 'hr', 'pl', 'mn'];
        $this->rows   = ['Title'];
	}

	private function generateProjectsLink($call)
	{
		return $this->apiLink.$this->lingoUser.'/projects/'.$this->currentProjectName.'/'.$call.'?auth_token='.$this->apiKey;
	}
	
	private function generateBasicLink($call)
	{
		return $this->apiLink.$call.'?auth_token='.$this->apiKey;
	}

	public function resources()
	{
		$request = $this->client->get($this->generateProjectsLink('resources.json'));
        $response = $request->send();

       	return  $response->json();
	}

	public function projects()
	{
		$request = $this->client->get($this->generateBasicLink('projects.json'));
        $response = $request->send();

        $this->projects = $response->json()['members'];

        return $this->projects;
	}

	public function setProject($index)
	{
		if (array_key_exists($index, $this->projects)) {
			$this->currentProject 		= $this->projects[$index];
			$this->setProjectName($this->currentProject['title']);
		}
	}

	public function setProjectName($name)
	{
		return $this->currentProjectName = strtolower($name);
	}

	public function scanLangDir($lang)
	{
		$dirs = scandir($lang);
		$ignore = ['.', '..'];
		foreach ($dirs as $dir) {
			if (!in_array($dir, $ignore)) {
				if (is_dir($lang.$dir)) {		
					$this->files[$dir] = $this->processLangFiles($lang.$dir);
				}
			}
		}
		$this->lang = array_keys($this->files);
		return $this->files;
	}

	protected function processLangFiles($dir)
	{
		$files = scandir($dir);
		$retval = [];
		$ignore = ['.', '..'];
		foreach ($files as $file) {
			if (!in_array($file, $ignore)) {
				if (stripos($file, ".csv") === false) {
					$filename = $dir.'/'.$file;
					array_push($retval, $filename);
					$this->createCsv($filename);
				}
			}
		}
		return $retval;
	}

	public function createCsv($filename)
    {
    	if (!file_exists($filename)) {
    		var_dump($filename);
    		return 'no file';
    	}

        $list = include($filename);
        $partsFilename = explode('.', $filename);
        array_pop($partsFilename);
        array_push($partsFilename, 'csv');
        $csvFilename =  implode('.', $partsFilename);
        $oneDimension = $this->prepareTranslationFile($list);

        $fp = fopen($csvFilename, 'w');
        $header = array_merge($this->rows, $this->lang);

        fwrite($fp, Helpers::arrayToCsv($header, ',', '"', true)."\n");

        foreach ($oneDimension as $fields) {
            $count = count($this->rows) + count($this->lang) - count($fields);

            $newFields = [];
            if ($count != 0) {
                $fieldsCount = count($fields);
                $newFields = array_fill($fieldsCount, $fieldsCount + $count -2, "");
            }
            $output = array_merge($fields, $newFields);

            fwrite($fp, Helpers::arrayToCsv($output, ',', '"', true)."\n");
        }

        fclose($fp);
    }

    private function prepareTranslationFile($list)
    {
        $retval = [];
        $dots = Arr::dot($list);
        foreach ($dots as $key =>$item) {
            $retval[] = [$key, $item];
        }
        return $retval;
    }


}