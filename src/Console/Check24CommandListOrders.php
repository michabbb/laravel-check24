<?php

namespace macropage\laravel_check24\Console;

use Illuminate\Console\Command;
use Check24;

class Check24CommandListOrders extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check24:list-orders {account_name} {orderid?} {--cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all or individual Orders from Check24';

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
        if ($this->argument('orderid')) {
            $OrderXMLFiles = Check24::getSingleXMLOrder($this->argument('orderid'),$this->option('cache'));
        } else {
            $OrderXMLFiles = Check24::getXMLOrders($this->option('cache'));
        }
        /** @noinspection ForgottenDebugOutputInspection */
        dump($OrderXMLFiles);
    }
}
