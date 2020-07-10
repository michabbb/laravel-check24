<?php

namespace macropage\laravel_check24\Console;

use Illuminate\Console\Command;
use Check24;

class Check24CommandsetDone extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check24:set-done {account_name} {orderid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move order to "done" folder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        Check24::setCustomerConfig($this->argument('account_name'));
        Check24::setDone($this->argument('orderid')); // throws exception if something goes wrong
        $this->info('DONE');
        return 0;
    }
}
