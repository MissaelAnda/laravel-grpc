<?php

namespace MissaelAnda\Grpc\Commands;

use Illuminate\Console\Command;
use MissaelAnda\Grpc\Commands\Server\ServerProcessInspector;

class StopCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'grpc:stop';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Stop the gRPC server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(ServerProcessInspector $inspector)
    {
        if (!$inspector->serverIsRunning()) {
            $inspector->serverStateFile->delete();

            $this->error('RoadRunner server is not running.');

            return Command::FAILURE;
        }

        $this->info('Stopping server...');

        $inspector->stopServer();

        $inspector->serverStateFile->delete();

        return Command::SUCCESS;
    }
}
