<?php

namespace Chipolo\Lingo\Commands;

use Illuminate\Console\Command;
use Chipolo\Lingo\Lingo;

class Push extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lingohub:push';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends translation files in CSV to lingohub';

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
        $default = 'd';

        $directory = $this->ask('Where do you keep your lang files ? (default is: '.$path.', write "'.$default.'" to keep)');
        if (trim($directory) == $default) {
            $directory = $path;
        }
        $lingo->setWorkingDir($directory);

        if ($this->confirm('Found languages - ' .implode(', ', $lingo->lang). ' -; do you want to add more ?', ['y', 'N'])) {
            $stop = 'q';
            $this->info('Write ISO code and press enter to add. Stop this process with writing "'.$stop.'".');
            while (true) {
                $lang = $this->ask('Add');
                if ($lang != $stop) {
                    $lingo->addLanguage(trim($lang));
                } else {
                    break;
                }  
            }
            $this->info('Final languages - '.implode(', ', $lingo->lang).' - ');
        }
        
        $projects = $lingo->getProjects();
   
        if (empty($projects)) {
            $this->info('No projects found on LingoHub. Please create some. Exiting... ');
            exit();
        }

        $projectIndex = $this->choice('Please select project on LingoHub for current project.', $lingo->getProjectsNames(), 0);
        $lingo->setProject($projectIndex);

        $retval = $lingo->pushFiles();

        $valid = true;
        foreach ($retval as $value) {
            $status = $value['status']['status'];
            if ($status !== 'Success') {
                $valid = false;
            }
            $this->info('Upload status for file - '. $value['file'].': '.$status);
        }
        if (!$valid) {
            $this->info('Some files failed to update, check LingoHub Web Gui for more info.');
        } else {
            $this->info('All files uploaded successfully.');
        }
    }
}