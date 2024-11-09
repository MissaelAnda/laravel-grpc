<?php

namespace MissaelAnda\Grpc\Commands;

use Illuminate\Console\Command;

class GenerateProtosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grpc:protos
                                    {-path= : The path to the proto file or folder}
                                    {-c|client : Only generate the client}
                                    {-s|service : Only generate the service}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates the protos services and/or clients.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // TODO: create protos services and clients
    }
}
