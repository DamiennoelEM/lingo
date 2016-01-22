<?php

namespace Chipolo\Lingo\Commands;

use Illuminate\Console\Command;
use Chipolo\Lingo\Lingo;

class Pull extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lingohub:pull';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets translations files from lingohub.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $apiKey     = config('lingohub.apiKey', '');
        $user       = config('lingohub.username', '');
        $project    = config('lingohub.project', '');

        if ($apiKey == '') {
            $apiKey = $this->ask('Can\'t find api key in lingohub config, please insert it now.');
        }

        if ($user == '') {
            $user = $this->ask('Can\'t find username in lingohub config, please insert it now.');
        }

        if ($project == '') {
            $project = $this->ask('Can\'t find project in lingohub config, please insert it now.');
        }

        $lingo = new Lingo($apiKey, $user);
        $path = 'resources/lang/';
        $default = 'd';

        $directory = $this->ask('Where do you keep your lang files ? (default is: '.$path.', write "'.$default.'" to keep)');
        if (trim($directory) == $default) {
            $directory = $path;
        }
        $lingo->setWorkingDir($directory, false);

        $projects = $lingo->getProjects();
   
        if (empty($projects)) {
            $this->info('No projects found on LingoHub. Please create some. Exiting... ');
            exit();
        }

        if (!$lingo->setProject($project)) {
             $projectIndex = $this->choice('Please select project on LingoHub for current project.', $lingo->getProjectsNames(), 0);
             $lingo->setProject($projectIndex);
        }

        $resources = $lingo->getResources();
        
        $projectLocale = $this->choice('Please select locale you wish to export.', $lingo->getLocalePullNames());
        $lingo->setResource($projectLocale);

      
        $retval = $lingo->pullFiles();

        $valid = true;
        foreach ($retval as $value) {
            $status = $value['success'];
            if (!$status) {
                $valid = false;
            }
            $this->info('Creating - '. $value['file'].': '.($status ? 'success' : 'failed').'.');
        }
        if (!$valid) {
            $this->info('Failed to create some files.');
        } else {
            $this->info('All files created successfully.');
        }
        
    }
}