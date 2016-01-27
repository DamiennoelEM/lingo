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
	public 		$defaultLanguage = 'en';
	private 	$nonDefault = false;
	private		$ignoredStrings = [];

	public 		$files = [];
	public 		$pushDataFiles = [];
	public 		$pullDataFiles = [];
	public 		$lang = [];
	public 		$rows = ['Title'];

	private 	$pullDirectories = [];
	private 	$pushDirectories = [];

	public 		$dirs = [];

	/**
	 * ApiKey and username that we use when we are connection to LingoHub
	 * 
	 * @param string $apiKey
	 * @param string $lingoUser
	 */
	public function __construct($apiKey, $lingoUser)
	{
		$this->apiKey 		= $apiKey;
		$this->lingoUser 	= $lingoUser;
	}

	/**
	 * Generates link for project calls
	 * 
	 * @param  string $call
	 * @return string 	
	 */
	private function generateProjectsLink($call)
	{
		return $this->apiLink.$this->lingoUser.'/projects/'.$this->currentProjectName.'/'.$call.'?auth_token='.$this->apiKey;
	}

	/**
	 * Generates link for basic calls
	 * 
	 * @param  string $call
	 * @return string 	
	 */
	private function generateBasicLink($call)
	{
		return $this->apiLink.$call.'?auth_token='.$this->apiKey;
	}

	/**
	 * Generates link for resource
	 * 
	 * @param  string $call
	 * @return string 	
	 */
	private function generateResourceLink($link)
	{
		return $link.'?auth_token='.$this->apiKey;
	}

	/**
	 * Removes file from filepath
	 * 
	 * @param  string $file
	 * @return void
	 */
	private function removeFile($file)
	{
		if (file_exists($file)) {
			unlink($file);
		}
	}

	/**
	 * Removes full directory tree and files from root directory given
	 * 
	 * @param  string $dir
	 * @return boolean
	 */
	private function removeDirectory($dir)
	{
	   	$files = array_diff(scandir($dir), ['.','..']); 
	    foreach ($files as $file) { 
	    	$currentDirectory = $dir. '/'. $file;
	      	is_dir($currentDirectory) ? $this->removeDirectory($currentDirectory) : unlink($currentDirectory); 
	    } 
	    return rmdir($dir); 
	}

	/**
	 * Takes range of directories and feeds them to recursive function to delete
	 * 
	 * @param  string $dir
	 * @return void
	 */
	private function removeDirectories($dirs)
	{
		foreach ($dirs as $dir) {
			$this->removeDirectory($dir);
		}
	}

	/**
	 * Here we set root working directory for current project and
	 * have options to scan for files inside
	 * 
	 * @param string $dir
	 * @param boolean $scan
	 * @return string
	 */
	public function setWorkingDir($dir, $scan = true)
	{
		$this->workingDir = $dir;

		if ($scan) {
			$this->scanLangDir();
		}

		return $this->workingDir;
	}

	/**
	 * Gets projects for current apiKey from LingoHub
	 * 	
	 * @return array
	 */
	public function getProjects()
	{
		$request = file_get_contents($this->generateBasicLink('projects.json'));
		$response = json_decode($request, true);

       	$this->projects = array_key_exists('members', $response) ? $response['members'] : [];

        return $this->projects;
	}

	/**
	 * Take projects and get their title and put them into array for displaying
	 * 
	 * @return array
	 */
	public function getProjectsNames()
	{
		$retval = [];
		foreach ($this->projects as $project) {
			array_push($retval, $project['title']);
		}
		return $retval;
	}

	/**
	 * Set project on which we will work on
	 * 
	 * @param boolean
	 */
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
	
	/**
	 * Set current project name for creating request links for LingoHub
	 * 
	 * @param string
	 */
	private function setProjectName($name)
	{
		return $this->currentProjectName = strtolower($name);
	}

	/**
	 * Get one project from LingoHub and make links easier accessible
	 * 
	 * @param  array $project
	 * @return array
	 */
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

	/**
	 * Take working directory of current project and get its language directories
	 * 
	 * @return array
	 */
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
		$this->sortLang();
		return $this->dirs;
	}

	/**
	 * Sort languages we set with scanning so English is always first
	 * 
	 * @return void
	 */
	private function sortLang()
	{
		$index = array_search($this->defaultLanguage, $this->lang);
		$element = $this->lang[$index];
		unset($this->lang[$index]);
		array_unshift($this->lang, $element);
	}

	/**
	 * Add language we didn't find from scanning, via command
	 * 
	 * @param array
	 */
	public function addLanguage($lang)
	{
		array_push($this->lang, $lang);
		$this->lang = array_unique($this->lang);
		return $this->lang;
	}

	/**
	 * Take directories from project and fetch its relevant files
	 * 
	 * @return void
	 */
	private function processLangDir() 
	{
		$files = [];
		foreach ($this->dirs as $dir) {
			$this->processLangFiles($dir);
		}

		$this->sortLangFiles();

		foreach ($this->pushDataFiles as $file => $files) {
			array_push($this->files, $this->createCsv($files, $file));
		}

	}

	/**
	 * Orders data files so we have default language on the first place
	 * 
	 * @return void
	 */
	private function sortLangFiles()
	{
		foreach ($this->pushDataFiles as $file => $files) {
			if (array_key_exists($this->defaultLanguage, $files)) {
				$element[$this->defaultLanguage] = $this->pushDataFiles[$file][$this->defaultLanguage];
				unset($this->pushDataFiles[$file][$this->defaultLanguage]);
				$this->pushDataFiles[$file] = array_merge($element, $this->pushDataFiles[$file]);
			}
		}
	}

	/**
	 * Take one directory and get php files which we assume return array with translation files
	 * 
	 * @param  string $dir
	 * @return void
	 */
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

	/**
	 * Return array of keys that weren't in default language
	 * 
	 * @return array
	 */
	public function getIgnoredStrings()
	{
		return $this->ignoredStrings;
	}

	/**
	 * Allow non default languages to create new keys
	 * 
	 * @param boolean
	 */
	public function setOverideNonDefault($bool) 
	{
		if (is_bool($bool)) {
			$this->nonDefault = $bool;
		}
		return $this->nonDefault;
	}

	/**
	 * Get files from project (all files from all languages) same file from all languages
	 * and create one csv that combines all
	 * 
	 * @param  array $files
	 * @param  string $filename
	 * @return string
	 */
	private function createCsv($files, $filename)
    {
    	// Make csv header from title and all the languages
    	$header = array_merge($this->rows, $this->lang);
    	$csvCombine = [];

    	// Go trough all the files
    	foreach ($files as $lang => $file) {
    		$filePath = $file['dir'].'/'.$file['name'];
	    	if (!file_exists($filePath)) {
	    		continue;
	    	}

	    	// Find index from header so we know on which place to write it
	    	$index = array_search($lang, $header);
	    	// Get array from language file
    		$data = include($filePath);

    		// If file is empty we skip
    		if (!is_array($data)) {
    			continue;
    		}

    		// We make array one dimensional with laravel dot magic
        	$oneDimension = $this->prepareTranslationFile($data);
			// Start combining files from different languages
        	if (empty($csvCombine)) {
        		foreach ($oneDimension as $key => $translation) {
        			$combined = [];
    				array_push($combined, $key);
    				$combined[$index] = $translation;
        			$csvCombine[$key] = $combined;
        		}
        	} else {
        		foreach ($oneDimension as $key => $translation) {
        			// If we find that something is not is our default language 
        			// we remeber that so we can report it later
        			if (!array_key_exists($key, $csvCombine)) {	
        				// If we want to create new keys even from other languages 
        				if ($this->nonDefault) {	
							$csvCombine[$key] = [$key];
						}
    					
    					$data = [
    						'filePath' 	=> $filePath,
    						'string'	=> $key,
    						'language'	=> $lang
    					];
    					array_push($this->ignoredStrings, $data);
    					
    					continue;
        			} 
        			$csvCombine[$key][$index] = $translation;
        		}
        	}
    	}

    	// Feel the unset indexes with empty values
    	$finalCombine = [];
    	foreach ($csvCombine as $key => $combined) {
    		$k = 0;
    		$newArray = [];
    		for ($i = 0; $i < count($header); $i++) {
    			if (!array_key_exists($i, $combined)) {
    				$newArray[$i] = '';
    			} else {
    				$newArray[$i] = $combined[$i];
    			}
    		}
    		array_push($finalCombine, $newArray);
    	}

    	// Calculate path and filename for current csv created
    	$rootCsvDir		= $this->workingDir.'csv-push/';
    	array_push($this->pushDirectories, $rootCsvDir);
    
    	$partsFilename = explode('.', $filename);
    	array_pop($partsFilename);
    	array_push($partsFilename, 'csv');

    	@mkdir($rootCsvDir);

    	$csvFilename 	= $rootCsvDir.implode('.', $partsFilename);
        $this->removeFile($csvFilename);

        // Start writing csv file
        $fp = fopen($csvFilename, 'w');
        // Header that needs title and languages
        fwrite($fp, Helpers::arrayToCsv($header, ',', '"', true)."\n");

        foreach ($finalCombine as $fields) {
        	// Break array and combined it to the string via helper function
        	// so we can make proper csv easier
            fwrite($fp, Helpers::arrayToCsv($fields, ',', '"', true)."\n");

        }

        fclose($fp);
        // Return filename we were working on
        return $csvFilename;
    }

    /**
     * Get returned array from language file and use Laravel's sneaky dot notation 
     * to make array one dimensional
     * 
     * @param  array $data
     * @return array
     */
    private function prepareTranslationFile($data)
    {
        $retval = [];
        $dots = Arr::dot($data);
        foreach ($dots as $key =>$item) {
            $retval[$key] = $item;
        }
        return $retval;
    }

    /**
     * Start pusing files that we prepared via other functions
     * 
     * @return array
     */
    public function pushFiles()
	{

		$this->processLangDir();
		return $this->startPushFiles();
	}

	/**
	 * Push files one by one, remove it and remove everything after we are done
	 * 
	 * @return array
	 */
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

    /**
     * Push one file via curl to LingoHub and set its default language
     * 
     * @param  string $filename
     * @param  string $lang
     * @return array
     */
    private function pushFile($filename, $lang = false)
    {
    	/*return [
    		'status' => 'Success'
    	];*/

    	if ($lang === false) {
    		$lang = $this->defaultLanguage;
    	}
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

    /**
     * Fetch resources (all language files) from LingoHub for current project
     * 
     * @return array
     */
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

	/**
	 * Get language names from resources we set before
	 * 
	 * @return array
	 */
	public function getLocalePullNames()
	{
		return array_merge(array_keys($this->localeResources), ['all']);
	}

	/**
	 * Set resource we will use from resources we got from LingoHub, either all languages or just one
	 * 
	 * @param 	string $resourceIndex
	 * @return  array
	 */
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


	/**
	 * Start pulling files from LingoHub, return success rate and remove temp directories afters
	 * 
	 * @return array
	 */
	public function pullFiles()
 	{
 		$retval = [];
 		foreach ($this->pullDataFiles as $file) {
 			array_push($retval, $this->fetchFile($file['links']['self']['href'], $file['name']));
 		}

 		$this->removeDirectories(array_unique($this->pullDirectories));

 		return $retval;
 	}

 	/**
 	 * Fetch specific file from LingoHub and save it to temporary file
 	 * 
 	 * @param  string $url
 	 * @param  string $name
 	 * @return array
 	 */
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

    /**
     * Create php file and save it to the correct directory,
     * from csv file created from LingoHub
     * 
     * @param  string $csvFilename
     * @param  string $langFolder
     * @param  string $file
     * @return array
     */
    private function createFromCsv($csvFilename, $folder, $file)
    {
        $retval = [];
        $reader = [];

		ini_set('auto_detect_line_endings', true);
		$handle = fopen($csvFilename, 'r');
		while (($data = fgetcsv($handle)) !== false) {
			array_push($reader, $data);
		}
		ini_set('auto_detect_line_endings', false);

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
        	//rename($filename, $filename.'_old');
        }

        $fileCreate = file_put_contents($filename, "<?php\nreturn " . var_export($retval, true) . ';');

        return [
        	'file' 		=> $filename,
        	'success'	=> $fileCreate !== false ? true : false	
        ];
    }
}