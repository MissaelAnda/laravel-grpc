<?php

namespace MissaelAnda\Grpc\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use MissaelAnda\Grpc\Commands\Concerns\FindsRoadRunnerBinary;
use MissaelAnda\Grpc\Commands\Concerns\InteractsWithServer;
use MissaelAnda\Grpc\Commands\Server\ServerProcessInspector;
use MissaelAnda\Grpc\Commands\Server\ServerStateFile;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class GrpcServeCommand extends Command
{
    use FindsRoadRunnerBinary, InteractsWithServer;

    /**
     * The minimum required version of the RoadRunner binary.
     *
     * @var string
     */
    protected $requiredRoadRunnerVersion = '2023.3.0';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grpc:serve
                        {--host= : The IP address the server should bind to}
                        {--port= : The port the server should be available on}
                        {--rpc-host= : The RPC IP address the server should bind to}
                        {--rpc-port= : The RPC port the server should be available on}
                        {--workers=auto : The number of workers that should be available to handle requests}
                        {--max-requests=500 : The number of requests to process before reloading the server}
                        {--rr-config= : The path to the RoadRunner .rr.yaml file}
                        {--watch : Automatically reload the server when the application is modified}
                        {--poll : Use file system polling while watching in order to watch files over a network}
                        {--log-level= : Log messages at or above the specified log level}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the gRPC server.';

    /**
     * Execute the console command.
     */
    public function handle(ServerProcessInspector $inspector)
    {
        if (($roadRunnerBinary = $this->findRoadRunnerBinary()) == null) {
            $this->error('Could not find the roadrunner binary.');

            return Command::FAILURE;
        }

        if ($inspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return Command::FAILURE;
        }

        $this->ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary);

        $this->writeServerStateFile($inspector->serverStateFile);

        /** @var Process */
        $server = tap(new Process(array_filter([
            $roadRunnerBinary,
            '-c', $this->configPath(),
            '-o', 'version=3',
            '-o', 'server.command=' . (new PhpExecutableFinder)->find() . ',' . base_path(config('grpc.command', 'vendor/bin/grpc-worker')),
            '-o', 'server.relay=pipes',
            '-o', 'rpc.listen=tcp://' . $this->rpcHost() . ':' . $this->rpcPort(),
            ...$this->collectServiceFiles(),
            '-o', 'grpc.listen=' . $this->getHost() . ':' . $this->getPort(),
            '-o', 'grpc.pool.num_workers=' . $this->workerCount(),
            '-o', 'logs.mode=production',
            '-o', 'logs.level=' . ($this->option('log-level') ?: (app()->environment('local') ? 'debug' : 'warn')),
            '-o', 'logs.output=stdout',
            '-o', 'logs.encoding=json',
            'serve',
        ]), base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_GRPC' => 1,
        ]))->start();

        $inspector->serverStateFile->writeProcessId($server->getPid());

        return $this->runServer($server, $inspector, 'roadrunner');
    }

    protected function ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary)
    {
        $version = tap(new Process([$roadRunnerBinary, '--version'], base_path()))
            ->run()
            ->getOutput();

        if (!Str::startsWith($version, 'rr version')) {
            return $this->warn(
                'Unable to determine the current RoadRunner binary version'
            );
        }

        $version = explode(' ', $version)[2];

        if (version_compare($version, $this->requiredRoadRunnerVersion, '<')) {
            $this->warn("Your RoadRunner binary version (<fg=red>$version</>) may be incompatible with gRPC.");
        }
    }

    /**
     * Write the RoadRunner server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'rpcPort' => $this->rpcPort(),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->option('max-requests'),
            'config' => config('grpc'),
        ]);
    }

    protected function collectServiceFiles(): array
    {
        $basePath = base_path('/');
        $protos = collect(config('grpc.paths.protos'))
            ->map(fn ($path) => array_merge(glob($path . '/*.proto'), glob('/**/*.proto')))
            ->flatten();

        if ($protos->isEmpty()) {
            return [];
        }

        return ['-o', 'grpc.proto=' . $protos
            ->map(fn ($filePath) => Str::after($filePath, $basePath))
            ->implode(',')];
    }

    /**
     * Get the gRPC server host
     */
    protected function getHost(): string
    {
        return $this->option('host') ?? config('grpc.server.host') ?? $_ENV['GRPC_HOST'] ?? '127.0.0.1';
    }

    /**
     * Get the gRPC server port.
     */
    protected function getPort(): string
    {
        return $this->option('port') ?? config('grpc.server.port') ?? $_ENV['GRPC_PORT'] ?? '9501';
    }

    /**
     * Get the RPC IP address the server should be available on.
     */
    protected function rpcHost(): string
    {
        return $this->option('rpc-host') ?: $this->getHost();
    }

    /**
     * Get the roadrunner rpc port
     */
    protected function rpcPort(): string
    {
        return $this->option('rpc-port') ?: $this->getPort() - 2000;
    }

    /**
     * Get the number of workers that should be started.
     *
     * @return int
     */
    protected function workerCount(): int
    {
        return $this->option('workers') == 'auto'
            ? 0 : $this->option('workers');
    }

    /**
     * Get the path to the RoadRunner configuration file.
     */
    protected function configPath(): string
    {
        $path = $this->option('rr-config');

        if (!$path) {
            touch(base_path('.rr.yaml'));

            return base_path('.rr.yaml');
        }

        if ($path && !realpath($path)) {
            throw new \InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return realpath($path);
    }
}
