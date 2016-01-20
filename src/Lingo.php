<?php 
namespace Chipolo\Lingo;

use Guzzle\Service\Client;
use League\Csv\Reader;

use Chipolo\Lingo\Support\Arr;
use Chipolo\Lingo\Helpers;

class Lingo 
{
	protected 	$apiKey;
	private 	$apiLink = 'https://api.lingohub.com/v1/';

	private 	$client;

	protected 	$lingoUser;

	public		$projects;
	public 		$currentProject;
	public 		$currentProjectName;

	public 		$files = [];
	public 		$lang = [];
	public 		$rows = ['Title'];

	public 		$dirs = [];

	public function __construct($apiKey, $lingoUser)
	{
		$this->apiKey 		= $apiKey;
		$this->lingoUser 	= $lingoUser;

		$this->client = new Client([
            'exceptions'        => true,
            'redirect.disable'  => true
        ]);

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

	public function getProjectsNames()
	{
		$retval = [];
		foreach ($this->projects as $project) {
			array_push($retval, $project['title']);
		}
		return $retval;
	}

	public function setProject($index)
	{
		dump($index);
		dump('outside');
		if (array_key_exists($index, $this->projects)) {
			dump('inside');
			$this->currentProject 		= $this->prepareProject($this->projects[$index]);
			$this->setProjectName($this->currentProject['title']);

			return true;
		} else {
			return false;
		}
	}
	
	private function setProjectName($name)
	{
		return $this->currentProjectName = strtolower($name);
	}

	private function prepareProject($project)
	{
		if (array_key_exists('links', $project)) {
			$retval = [];
			foreach ($project['links'] as $value) {
				$retval[$value['rel']] = $value;
			}
			$project['links'] = $retval;
			return $project;
		} else {
			return $project;
		}
	}

	public function scanLangDir($root)
	{
		$dirs = scandir($root);
		$ignore = ['.', '..'];
		foreach ($dirs as $dir) {
			if (!in_array($dir, $ignore)) {
				if (is_dir($root.$dir)) {		
					array_push($this->dirs, $root.$dir);
					array_push($this->lang, $dir);
				}
			}
		}
		return $this->dirs;
	}

	public function startPushFiles()
	{
		$this->processLangDir();
		return $this->pushFiles();
	}

	public function addLanguage($lang)
	{
		array_push($this->lang, $lang);
		$this->lang = array_unique($this->lang);
		return $this->lang;
	}

	private function processLangDir() 
	{
		foreach ($this->dirs as $dir) {
			$this->files[$dir] = $this->processLangFiles($dir);
		}
		return $this->files;
	}

	private function processLangFiles($dir)
	{
		$files = scandir($dir);
		$retval = ['php' => [], 'csv' => []];
		$ignore = ['.', '..'];
		foreach ($files as $file) {
			if (!in_array($file, $ignore)) {
				if (stripos($file, ".csv") === false) {
					$filename = $dir.'/'.$file;
					array_push($retval['php'], $filename);

					$csvFilename = $this->createCsv($dir, $file);
					array_push($retval['csv'], $csvFilename);
				}
			}
		}
		return $retval;
	}

	private function createCsv($dir, $file)
    {
    	$filename = $dir.'/'.$file;
    	if (!file_exists($filename)) {
    		return 'File '.$filename.'not found.';
    	}

        $partsFile = explode('.', $file);
        array_pop($partsFile);
        array_push($partsFile, 'csv');
        $csvFile 		= implode('.', $partsFile);
        $csvDir			= $dir.'/csv/';
        @mkdir($csvDir);
        $csvFilename 	= $csvDir.$csvFile;

        $data = include($filename);
        $oneDimension = $this->prepareTranslationFile($data);

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

        return $csvFilename;
    }

    private function prepareTranslationFile($data)
    {
        $retval = [];
        $dots = Arr::dot($data);
        foreach ($dots as $key =>$item) {
            $retval[] = [$key, $item];
        }
        return $retval;
    }

    private function pushFiles()
    {
    	$retval = [];
    	foreach ($this->files as $lang => $type) {
    		if (array_key_exists('csv', $type)) {
    			foreach ($type['csv'] as $file) {
    				$status = [
    					'file' 		=> $file,
    					'status'	=> $this->pushFile($file, $lang)
    				];
    				array_push($retval, $status);
    			}
    		}
    	}
    	return $retval;
    }

    public function pushFile($filename, $lang)
    {
    	$partsFile = explode('/', $filename);
        $file = array_pop($partsFile);

        $cfile = Helpers::getFileCurlValue($filename,'text/csv', $file);
        $data = array(  'file'          => $cfile,
                        'iso2_slug'     => $lang );

        $ch = curl_init();
        $options = array(CURLOPT_URL => $this->generateProjectsLink('resources.json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true, 
            CURLOPT_HEADER => true, 
            CURLOPT_SSL_VERIFYPEER => false, 
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        );

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_getinfo($ch,CURLINFO_HEADER_OUT);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        substr($result, 0, $header_size);
        $body = substr($result, $header_size);
        curl_close($ch);
        return json_decode($body, true);
    }
}