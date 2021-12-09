<?php

namespace Nextbyte\Courier\Console;

use Illuminate\Console\Command;
use Nextbyte\Courier\Clients\BestExpress\BestExpress;

class GenerateBestExpressOrderPushData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bestexpress:generate-push-data
                            {bizData?   : The request data in json string}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Best Express Push request data in json string with a valid sign.';

    /**
     * @var BestExpress
     */
    protected $bestExpress;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->bestExpress = new BestExpress(config('courier.best-express'));
    }

    public function handle()
    {
        $bizData = $this->argument('bizData');

        if (!$bizData)
            $bizData = $this->ask("What is the bizData (in JSON string)?");

        $json = $this->bestExpress->generateOrderPushData($bizData);

        $this->line(stripslashes(json_encode($json, JSON_UNESCAPED_SLASHES)));
    }
}
