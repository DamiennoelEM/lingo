<?php 
namespace Chipolo\Lingo;

use Chipolo\Lingo\Support\Arr;
use Chipolo\Lingo\Helpers;

class Lingo 
{
	protected 	$apiKey;
	private 	$apiLink = 'https://api.lingohub.com/v1/';
	protected 	$lingoUser;

	private 	$client;

	public		$projects;
	public 		$resources;
	public 		$localeResources;
	public 		$localePullNames;
	public 		$currentProject;
	public 		$currentProjectName;
	public 		$workingDir;

	public 		$files = [];
	public 		$pushDataFiles = [];
	public 		$pullDataFiles = [];
	public 		$lang = [];
	public 		$rows = ['Title'];

	private 	$pullDirectories = [];
	private 	$pushDirectories = [];

	public 		$dirs = [];

	public function __construct($apiKey, $lingoUser)
	{
		$this->apiKey 		= $apiKey;
		$this->lingoUser 	= $lingoUser;
	}

	private function generateProjectsLink($call)
	{
		return $this->apiLink.$this->lingoUser.'/projects/'.$this->currentProjectName.'/'.$call.'?auth_token='.$this->apiKey;
	}
	
	private function generateBasicLink($call)
	{
		return $this->apiLink.$call.'?auth_token='.$this->apiKey;
	}

	private function generateResourceLink($link)
	{
		return $link.'?auth_token='.$this->apiKey;
	}

	private function removeFile($file)
	{
		if (file_exists($file)) {
			unlink($file);
		}
	}

	private function removeDirectory($dir)
	{
	   	$files = array_diff(scandir($dir), ['.','..']); 
	    foreach ($files as $file) { 
	    	$currentDirectory = $dir. '/'. $file;
	      	is_dir($currentDirectory) ? $this->removeDirectory($currentDirectory) : unlink($currentDirectory); 
	    } 
	    return rmdir($dir); 
	}

	private function removeDirectories($dirs)
	{
		foreach ($dirs as $dir) {
			$this->removeDirectory($dir);
		}
	}

	public function setWorkingDir($directory, $scan = true)
	{
		$this->workingDir = $directory;

		if ($scan) {
			$this->scanLangDir();
		}

		return $this->workingDir;
	}

	public function getProjects()
	{
		$request = file_get_contents($this->generateBasicLink('projects.json'));
		$response = json_decode($request, true);

       	$this->projects = array_key_exists('members', $response) ? $response['members'] : [];

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

	public function setProject($addProject)
	{
		foreach ($this->projects as $project) {
			if ($project['title'] == $addProject) {

				$this->currentProject 		= $this->prepareResource($project);
				$this->setProjectName($project['title']);

				return true;
			}
		}
		return false;
	}
	
	private function setProjectName($name)
	{
		return $this->currentProjectName = strtolower($name);
	}

	private function prepareResource($project)
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

	private function scanLangDir()
	{
		$dirs = array_diff(scandir($this->workingDir), ['.','..']); 
		foreach ($dirs as $dir) {
			$currentDir = $this->workingDir.$dir;
			if (is_dir($currentDir)) {	
				array_push($this->dirs, $this->workingDir.$dir);
				array_push($this->lang, $dir);
			}
		}
		return $this->dirs;
	}

	public function addLanguage($lang)
	{
		array_push($this->lang, $lang);
		$this->lang = array_unique($this->lang);
		return $this->lang;
	}

	private function processLangDir() 
	{
		$files = [];
		foreach ($this->dirs as $dir) {
			$this->processLangFiles($dir);
		}

		foreach ($this->pushDataFiles as $file => $files) {
			array_push($this->files, $this->createCsv($files, $file));
		}
	}

	private function processLangFiles($dir)
	{
		$files = array_diff(scandir($dir), ['.','..']); 
		foreach ($files as $file) {
			$directory = $dir.'/'.$file;

			if (is_dir($directory)) {
				// TODO create support for multiple directories
				continue;
			} 

			$fileParts = explode('.', $file);
			$ext = array_pop($fileParts);
			$dirParts = explode('/', $dir);
			$lang = array_pop($dirParts);

			if ($ext == 'php') {
				$this->pushDataFiles[$file][$lang] = ['dir' => $dir, 'name' => $file];
			}
		}
	}

	private function createCsv($files, $filename)
    {
    	$csvCombine = [];
    	foreach ($files as $lang => $file) {
    		$filePath = $file['dir'].'/'.$file['name'];
	    	if (!file_exists($filePath)) {
	    		continue;
	    	}

    		$data = include($filePath);
        	$oneDimension = $this->prepareTranslationFile($data);

        	if (empty($csvCombine)) {
        		$csvCombine = $oneDimension;
        	} else {
        		foreach ($csvCombine as $key => $combined) {
        			foreach ($oneDimension as $item) {
        				if (array_key_exists(0, $item) && array_key_exists(0, $combined)) {
        					if ($combined[0] == $item[0]) {
        						array_push($combined, $item[1]);
        						$csvCombine[$key] = $combined;
        					}
        				}
        			}
        		}
        	}
    	}

    	$rootCsvDir		= $this->workingDir.'csv-push/';
    	array_push($this->pushDirectories, $rootCsvDir);
    
    	$partsFilename = explode('.', $filename);
    	array_pop($partsFilename);
    	array_push($partsFilename, 'csv');

    	@mkdir($rootCsvDir);

    	$csvFilename 	= $rootCsvDir.implode('.', $partsFilename);
        $this->removeFile($csvFilename);

        $header = array_merge($this->rows, $this->lang);

        $fp = fopen($csvFilename, 'w');
        fwrite($fp, Helpers::arrayToCsv($header, ',', '"', true)."\n");

        foreach ($csvCombine as $fields) {
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

    public function pushFiles()
	{
		$this->processLangDir();
		return $this->startPushFiles();
	}

    private function startPushFiles()
    {
    	$retval = [];
    	foreach ($this->files as $file) {
			$push = $this->pushFile($file);
			$status = [
				'file' 		=> $file,
				'status'	=> $push
			];
    		array_push($retval, $status);

			if ($push['status'] == 'Success') {
				$this->removeFile($file);
			}
    	}

    	$this->removeDirectories(array_unique($this->pushDirectories));

    	return $retval;
    }

    private function pushFile($filename, $lang = 'en')
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

    public function getResources()
	{
		$request = file_get_contents($this->generateProjectsLink('resources.json'));
		$response = json_decode($request, true);

       	$this->resources = array_key_exists('members', $response) ? $response['members'] : [];

       	foreach ($this->resources as $resource) {
       		$this->localeResources[$resource['project_locale']][] = $resource;
       	}

       	$this->localePullNames = $this->getLocalePullNames();
       	return $this->localeResources;
	}

	public function getLocalePullNames()
	{
		return array_merge(array_keys($this->localeResources), ['all']);
	}

	public function setResource($resourceIndex)
	{
		if (array_key_exists($resourceIndex,  $this->localeResources)) {
			$resource = $this->localeResources[$resourceIndex];
		} else {
			$resource = $this->resources;
		}

		foreach ($resource as $item) {
			array_push($this->pullDataFiles, $this->prepareResource($item));
		}

		return $this->pullDataFiles;
	}

	public function pullFiles()
 	{
 		$retval = [];
 		foreach ($this->pullDataFiles as $file) {
 			array_push($retval, $this->fetchFile($file['links']['self']['href'], $file['name']));
 		}

 		$this->removeDirectories(array_unique($this->pullDirectories));

 		return $retval;
 	}

    private function fetchFile($url, $name)
    {
        $remoteFile = file_get_contents($this->generateResourceLink($url));

        $partsFile = explode('.', $name);
        $ext = array_pop($partsFile);
        $folder = array_pop($partsFile);
        $file = implode('.', $partsFile);

        array_push($partsFile, $ext);
		$csvFile 		= implode('.', $partsFile);

		$rootCsvDir		= $this->workingDir.'csv-pull/';
        $csvDir			= $rootCsvDir.$folder.'/';
        array_push($this->pullDirectories, $rootCsvDir);

        @mkdir($csvDir, 0777, true);
        $csvFilename 	= $csvDir.$csvFile;
        
        $fileCreate = file_put_contents($csvFilename, $remoteFile);

        if ($fileCreate === false) {
	        return [
	        	'file' 		=> $csvFilename,
	        	'success'	=> false
	        ];
	    }

        return  $this->createFromCsv($csvFilename, $folder, $file);
    }

    private function createFromCsv($csvFilename, $folder, $file)
    {
        $retval = [];
        $reader = array_map('str_getcsv', file($csvFilename));

        $index = 2; //  We assume this will be always available
        foreach ($reader as $key=>$row) {
            if ($key == 0) {
            	$index = array_search($folder, $row);
            } else {
            	Arr::set($retval, $row[0], $row[$index]);
            }
        }

        $langFolder = $this->workingDir.$folder;
        @mkdir($langFolder);
        $filename =  $langFolder.'/'. $file.'.php';

        if (file_exists($filename)) {
        	rename($filename, $filename.'_old');
        }

        $fileCreate = file_put_contents($filename, "<?php\nreturn " . var_export($retval, true) . ';');

        return [
        	'file' 		=> $filename,
        	'success'	=> $fileCreate !== false ? true : false	
        ];
    }
}