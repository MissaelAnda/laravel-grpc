<?php

namespace MissaelAnda\Grpc\Commands\Concerns;

use Illuminate\Support\Str;
use MissaelAnda\Grpc\Exceptions\ServerShutdownException;
use Symfony\Component\Console\Terminal;

trait InteractsWithIO
{
    /**
     * The current terminal width.
     */
    protected ?int $terminalWidth;

    /**
     * A list of error messages that should be ignored.
     *
     * @var array
     */
    protected $ignoreMessages = [
        'destroy signal received',
        'req-resp mode',
        'scan command',
        'sending stop request to the worker',
        'stop signal received, grace timeout is: ',
        'exit forced',
        'worker allocated',
        'worker is allocated',
        'worker constructed',
        'worker destructed',
        'worker destroyed',
        '[INFO] RoadRunner server started; version:',
        '[INFO] sdnotify: not notified',
        'exiting; byeee!!',
        'storage cleaning happened too recently',
        'write error',
        'unable to determine directory for user configuration; falling back to current directory',
        '$HOME environment variable is empty',
        'unable to get instance ID',
    ];

    /**
     * Write a string as raw output.
     *
     * @param  string  $string
     * @return void
     */
    public function raw($string)
    {
        if (!Str::startsWith($string, $this->ignoreMessages)) {
            $this->output->writeln($string);
        }
    }

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function info($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'INFO', 'blue', 'white');
    }

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function error($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'ERROR', 'red', 'white');
    }

    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function warn($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'WARN', 'yellow', 'black');
    }

    /**
     * Write a string as label output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @param  string  $level
     * @param  string  $background
     * @param  string  $foreground
     * @return void
     */
    public function label($string, $verbosity, $level, $background, $foreground)
    {
        if (!empty($string) && !Str::startsWith($string, $this->ignoreMessages)) {
            $this->output->writeln([
                '',
                "  <bg=$background;fg=$foreground;options=bold> $level </> $string",
            ], $this->parseVerbosity($verbosity));
        }
    }

    /**
     * Write information about a request to the console.
     *
     * @param  array  $request
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function requestInfo($request, $verbosity = null)
    {
        $terminalWidth = $this->getTerminalWidth();

        $duration = number_format(round($request['duration'], 2), 2, '.', '');

        $memory = isset($request['memory'])
            ? (number_format($request['memory'] / 1024 / 1024, 2, '.', '') . ' mb ')
            : '';

        ['method' => $method] = $request;
        $status = 'SUCCESS';

        if ($failed = isset($request['error'])) {
            [$status, $description] = Str::of($request['error'])
                ->after('rpc error: code = ')->explode(' desc = ', 2);

            $status = strtoupper($status);
        }

        $dots = str_repeat('.', max($terminalWidth - strlen($status . $method . $duration . $memory) - 16, 0));

        if (empty($dots) && !$this->output->isVerbose()) {
            $method = substr($method, 0, $terminalWidth - strlen($status . $duration . $memory) - 15 - 3) . '...';
        } else {
            $dots .= ' ';
        }

        $this->output->writeln(sprintf(
            '  <fg=%s;options=bold>%s </>  <options=bold>%s</><fg=#6C7280> %s%s%s ms</>',
            $failed ? 'red' : 'green',
            $status,
            $method,
            $dots,
            $memory,
            $duration,
        ), $this->parseVerbosity($verbosity));

        if ($this->output->isVerbose() && isset($description)) {
            $this->error('     ' . $description);
        }
    }

    /**
     * Write information about a throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function throwableInfo($throwable, $verbosity = null)
    {
        $this->label($throwable['message'], $verbosity, $throwable['class'], 'red', 'white');

        $this->newLine();

        $outputTrace = function ($trace, $number) {
            $number++;

            if (isset($trace['line'])) {
                ['line' => $line, 'file' => $file] = $trace;

                $this->line("  <fg=yellow>$number</>   $file:$line");
            }
        };

        $outputTrace($throwable, -1);

        return collect($throwable['trace'])->each($outputTrace);
    }

    /**
     * Write information about a "shutdown" throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function shutdownInfo($throwable, $verbosity = null)
    {
        $this->throwableInfo($throwable, $verbosity);

        throw new ServerShutdownException;
    }

    /**
     * Handle stream information from the worker.
     *
     * @param  array  $stream
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function handleStream($stream, $verbosity = null)
    {
        match ($stream['type'] ?? null) {
            'request' => $this->requestInfo($stream, $verbosity),
            'throwable' => $this->throwableInfo($stream, $verbosity),
            'shutdown' => $this->shutdownInfo($stream, $verbosity),
            'raw' => $this->raw(json_encode($stream)),
            default => $this->info(json_encode($stream), $verbosity)
        };
    }

    /**
     * Computes the terminal width.
     *
     * @return int
     */
    protected function getTerminalWidth()
    {
        if (!isset($this->terminalWidth)) {
            $this->terminalWidth = (new Terminal)->getWidth();

            $this->terminalWidth = $this->terminalWidth >= 30
                ? $this->terminalWidth
                : 30;
        }

        return $this->terminalWidth;
    }
}
