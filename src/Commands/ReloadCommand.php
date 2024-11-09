<?php

namespace MissaelAnda\Grpc\Commands;

use Illuminate\Console\Command;
use MissaelAnda\Grpc\Commands\Server\ServerProcessInspector;

class ReloadCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'grpc:reload';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Reload the gRPC workers';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(ServerProcessInspector $inspector)
    {
        if (!$inspector->serverIsRunning()) {
            $this->error('Octane server is not running.');

            return Command::FAILURE;
        }

        $this->info('Reloading workers...');

        $inspector->reloadServer();

        return Command::SUCCESS;
    }
}
