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

        if ($apiKey == '') {
            $apiKey = $this->ask('Can\'t find api key in lingohub config, please insert it now.');
        }

        if ($user == '') {
            $user = $this->ask('Can\'t find username in lingohub config, please insert it now.');
        }

        $lingo = new Lingo($apiKey, $user);
        $path = 'resources/lang/';

        $directory = $this->ask('Where do you keep your lang files ? (default is: '.$path.', write "default" to keep)');
        if (trim($directory) == "default") {
            $directory = $path;
        }

        $projects = $lingo->getProjects();
   
        if (empty($projects)) {
            $this->info('No projects found on LingoHub. Please create some. Exiting... ');
            exit();
        }

        $projectIndex = $this->choice('Please select project on LingoHub for current project.', $lingo->getProjectsNames(), 0);
        $lingo->setProject($projectIndex);

        $resources = $lingo->getResources();
        
        $projectLocale = $this->choice('Please select locale you wish to export.', $lingo->getLocalePullNames());
        dd($projectLocale);
        $lingo->setFilesToExport($projectLocale);

        dd($lingo->pullFiles);
        
        
    }
}