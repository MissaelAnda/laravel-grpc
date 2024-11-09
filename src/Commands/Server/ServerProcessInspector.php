<?php

namespace MissaelAnda\Grpc\Commands\Server;

use Symfony\Component\Process\Process;
use MissaelAnda\Grpc\Commands\Concerns\FindsRoadRunnerBinary;

class ServerProcessInspector
{
    use FindsRoadRunnerBinary;

    public function __construct(
        public ServerStateFile $serverStateFile,
    ) {
    }

    /**
     * Determine if the RoadRunner server process is running.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->signal($masterProcessId, 0);
    }

    /**
     * Reload the RoadRunner workers.
     */
    public function reloadServer(): void
    {
        [
            'state' => [
                'host' => $host,
                'rpcPort' => $rpcPort,
            ],
        ] = $this->serverStateFile->read();

        tap(new Process([
            $this->findRoadRunnerBinary(),
            'reset',
            '-o', "rpc.listen=tcp://$host:$rpcPort",
            '-s',
        ], base_path()))->start()->waitUntil(function ($type, $buffer) {
            if ($type === Process::ERR) {
                throw new \RuntimeException('Cannot reload RoadRunner: ' . $buffer);
            }

            return true;
        });
    }

    /**
     * Stop the RoadRunner server.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $this->signal($masterProcessId, SIGTERM);
    }

    protected function signal(int $processId, int $signal): bool
    {
        return posix_kill($processId, $signal);
    }
}
