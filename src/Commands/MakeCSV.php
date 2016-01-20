<?php

namespace Chipolo\Lingo\Commands;

use Illuminate\Console\Command;
use Chipolo\Lingo\Lingo;

class MakeCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lingohub:pushFiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create CSV file from lang files.';

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

        $lingo->scanLangDir($directory);

        if ($this->confirm('Found languages - ' .implode(', ', $lingo->lang). ' -; do you want to add more ?', ['y', 'N'])) {
            $stop = 'quit';
            $this->info('Write ISO code and press enter to add. Stop this process with writing "'.$stop.'".');
            while ($lang = $this->ask('Add: ') != $stop) {
                $lingo->addLanguage(trim($lang));
            }
            $this->info('Final languages - '.implode(', ', $lingo->lang).' - ');
        }
        
        $projects = $lingo->projects();
        if (empty($projects)) {
            $this->info('No projects found on LingoHub. Please create some. Exiting... ');
            exit();
        }

        $projectIndex = $this->choice('Please select project on LingoHub for current project.', array_values($projects), 0);
        $lingo->setProject($projectIndex);

        $retval = $lingo->startPushFiles();

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