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
    protected $signature = 'lang:makeCSV';

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
        $apiKey     = '632778495ac92a60a6f8c44c0c702ea677ef87d5de58b6b47d4ce03c2623d20e';
        $user       = 'marko-zagar';
        $lingo = new Lingo($apiKey, $user);
        $path = '../resources/lang/';
        $directory = $this->ask('Where do you keep you lang files ? (default is: '.$path.', leave empty for default)');
        if (trim($directory) == "") {
            $directory = $path;
        }
        $retval = $lingo->scanLangDir(app_path($directory));
        if (!empty($retval)) {
            $this->info('CSV\'s created.')
        } else {
            $this->info('CSV\'s creation failed.')
        }
    }
}