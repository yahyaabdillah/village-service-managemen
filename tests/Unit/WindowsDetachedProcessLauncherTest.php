<?php

namespace Tests\Unit;

use App\Services\WindowsDetachedProcessLauncher;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WindowsDetachedProcessLauncherTest extends TestCase
{
    public function test_it_starts_and_detects_a_hidden_process_without_powershell(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Launcher ini khusus Windows.');
        }

        $directory = storage_path('framework/testing/windows-launcher');
        $stdout = $directory.'/stdout.log';
        $stderr = $directory.'/stderr.log';
        File::ensureDirectoryExists($directory);
        File::delete([$stdout, $stderr]);

        $launcher = app(WindowsDetachedProcessLauncher::class);
        $pid = $launcher->start(
            ['node', '-e', 'setTimeout(() => {}, 30000)'],
            base_path(),
            [],
            $stdout,
            $stderr,
        );

        try {
            $this->assertMatchesRegularExpression('/^\d+$/', $pid);
            $this->assertTrue($launcher->isRunning($pid));
            $this->assertFileExists($stdout);
            $this->assertFileExists($stderr);
        } finally {
            if (isset($pid) && ctype_digit($pid)) {
                (new Process(['taskkill.exe', '/PID', $pid, '/T', '/F']))->run();
            }
        }
    }

    public function test_it_can_stop_the_recorded_node_process_without_powershell(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Launcher ini khusus Windows.');
        }

        $directory = storage_path('framework/testing/windows-launcher-stop');
        File::ensureDirectoryExists($directory);

        $launcher = app(WindowsDetachedProcessLauncher::class);
        $pid = $launcher->start(
            ['node', '-e', 'setTimeout(() => {}, 30000)'],
            base_path(),
            [],
            $directory.'/stdout.log',
            $directory.'/stderr.log',
        );

        try {
            $this->assertTrue($launcher->stop($pid));
            $this->assertFalse($launcher->isRunning($pid));
        } finally {
            if ($launcher->isRunning($pid)) {
                (new Process(['taskkill.exe', '/PID', $pid, '/T', '/F']))->run();
            }
        }
    }

    public function test_whatsapp_service_does_not_depend_on_powershell_for_windows_processes(): void
    {
        $source = File::get(app_path('Services/WhatsAppNotificationService.php'));

        $this->assertStringNotContainsString("'powershell'", $source);
        $this->assertStringContainsString('WindowsDetachedProcessLauncher', $source);
    }

    public function test_windows_launcher_retries_transient_node_startup_failures(): void
    {
        $source = File::get(app_path('Services/WindowsDetachedProcessLauncher.php'));

        $this->assertStringContainsString('MAX_START_ATTEMPTS = 3', $source);
        $this->assertStringContainsString('ProcessFailedException', $source);
        $this->assertStringContainsString('attempt <= self::MAX_START_ATTEMPTS', $source);
    }
}
