<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WindowsDetachedProcessLauncher
{
    private const MAX_START_ATTEMPTS = 3;

    public function start(array $command, string $workingDirectory, array $environment, string $stdout, string $stderr): string
    {
        if ($command === []) {
            throw new InvalidArgumentException('Perintah proses tidak boleh kosong.');
        }

        foreach ([$stdout, $stderr] as $logFile) {
            if (! is_dir(dirname($logFile))) {
                mkdir(dirname($logFile), 0755, true);
            }
        }

        for ($attempt = 1; $attempt <= self::MAX_START_ATTEMPTS; $attempt++) {
            $process = new Process([
                'node',
                base_path('wa-bridge/detached-launcher.js'),
            ], $workingDirectory, array_merge($environment, [
                'DETACHED_COMMAND_JSON' => json_encode(array_values($command), JSON_THROW_ON_ERROR),
                'DETACHED_WORKING_DIRECTORY' => $workingDirectory,
                'DETACHED_STDOUT' => $stdout,
                'DETACHED_STDERR' => $stderr,
            ]));
            $process->setTimeout(10);

            try {
                $process->mustRun();
                break;
            } catch (ProcessFailedException $exception) {
                if ($attempt >= self::MAX_START_ATTEMPTS) {
                    throw $exception;
                }

                usleep(250_000);
            }
        }

        $pid = trim($process->getOutput());
        if (! ctype_digit($pid)) {
            throw new RuntimeException('Launcher Windows tidak mengembalikan PID yang valid.');
        }

        return $pid;
    }

    public function isRunning(string $pid): bool
    {
        if (! ctype_digit($pid)) {
            return false;
        }

        $process = new Process([
            'tasklist.exe',
            '/FI',
            'PID eq '.$pid,
            '/NH',
            '/FO',
            'CSV',
        ]);
        $process->run();

        return $process->isSuccessful() && str_contains($process->getOutput(), ',"'.$pid.'",');
    }

    public function stop(string $pid): bool
    {
        if (! ctype_digit($pid) || (int) $pid <= 0) {
            return false;
        }

        $taskList = new Process([
            'tasklist.exe',
            '/FI',
            'PID eq '.$pid,
            '/NH',
            '/FO',
            'CSV',
        ]);
        $taskList->run();

        if (! $taskList->isSuccessful() || ! str_contains(strtolower($taskList->getOutput()), '"node.exe","'.$pid.'",')) {
            return ! $this->isRunning($pid);
        }

        $taskKill = new Process(['taskkill.exe', '/PID', $pid, '/T', '/F']);
        $taskKill->run();

        $deadline = microtime(true) + 3;
        while ($this->isRunning($pid) && microtime(true) < $deadline) {
            usleep(100_000);
        }

        return ! $this->isRunning($pid);
    }
}
