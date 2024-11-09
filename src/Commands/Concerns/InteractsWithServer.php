<?php

namespace MissaelAnda\Grpc\Commands\Concerns;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MissaelAnda\Grpc\Commands\Server\ServerProcessInspector;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;
use MissaelAnda\Grpc\Exceptions\ServerShutdownException;

trait InteractsWithServer
{
    use InteractsWithIO;

    /**
     * Run the given server process.
     */
    protected function runServer(Process $server, ServerProcessInspector $inspector, string $type): int
    {
        while (!$server->isStarted()) {
            sleep(1);
        }

        $this->writeServerRunningMessage();

        $watcher = $this->startServerWatcher();

        try {
            while ($server->isRunning()) {
                $this->writeServerOutput($server);

                if (
                    $watcher->isRunning() &&
                    $watcher->getIncrementalOutput()
                ) {
                    $this->info('Application change detected. Restarting workers…');

                    $inspector->reloadServer();
                } elseif ($watcher->isTerminated()) {
                    $this->error(
                        'Watcher process has terminated. Please ensure Node and chokidar are installed.' . PHP_EOL .
                            $watcher->getErrorOutput()
                    );

                    return Command::FAILURE;
                }

                usleep(500 * 1000);
            }

            $this->writeServerOutput($server);
        } catch (ServerShutdownException) {
            return Command::FAILURE;
        } finally {
            $this->stopServer();
        }

        return $server->getExitCode();
    }

    protected function writeServerOutput($server)
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        Str::of($output)
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (!is_array($debug = json_decode($output, true))) {
                    return $this->info($output);
                }

                if (is_array($stream = json_decode($debug['msg'], true))) {
                    return $this->handleStream($stream);
                }

                if ($debug['logger'] == 'server') {
                    return $this->raw($debug['msg']);
                }

                if (
                    $debug['logger'] == 'grpc'
                    && isset($debug['elapsed'])
                    && isset($debug['msg'])
                ) {
                    [
                        'elapsed' => $elapsed,
                        'method' => $method,
                    ] = $debug;

                    return $this->requestInfo([
                        'method' => $method,
                        'duration' => $elapsed,
                        ...($debug['level'] == 'error' ? ['error' => $debug['error']] : []),
                    ]);
                }
            });

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (!Str::contains($output, ['DEBUG', 'INFO', 'WARN'])) {
                    $this->error($output);
                }
            });
    }

    /**
     * Start the watcher process for the server.
     *
     * @return \Symfony\Component\Process\Process|object
     */
    protected function startServerWatcher()
    {
        if (!$this->option('watch')) {
            return new class
            {
                public function __call($method, $parameters)
                {
                    return null;
                }
            };
        }

        if (empty($paths = config('grpc.paths.watch'))) {
            throw new InvalidArgumentException(
                'List of directories/files to watch not found. Please update your "config/grpc.php" configuration file.',
            );
        }

        return tap(new Process([
            (new ExecutableFinder)->find('node'),
            'file-watcher.cjs',
            json_encode(collect($paths)->map(fn ($path) => base_path($path))),
            $this->option('poll'),
        ], realpath(__DIR__ . '/../../../bin'), null, null, null))->start();
    }

    /**
     * Write the server start "message" to the console.
     *
     * @return void
     */
    protected function writeServerRunningMessage()
    {
        $this->info('Server running…');

        $this->output->writeln([
            '',
            '  Local: <fg=white;options=bold>' . 'tcp://' . $this->getHost() . ':' . $this->getPort() . ' </>',
            '',
            '  <fg=yellow>Press Ctrl+C to stop the server</>',
            '',
        ]);
    }

    /**
     * Retrieve the given server output and flush it.
     *
     * @return array
     */
    protected function getServerOutput($server)
    {
        $output = [
            $server->getIncrementalOutput(),
            $server->getIncrementalErrorOutput(),
        ];

        $server->clearOutput()->clearErrorOutput();

        return $output;
    }

    /**
     * Returns the list of signals to subscribe.
     */
    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    /**
     * The method will be called when the application is signaled.
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopServer();

        exit(0);
    }

    protected function stopServer()
    {
        $this->callSilent('grpc:stop');
    }
}
