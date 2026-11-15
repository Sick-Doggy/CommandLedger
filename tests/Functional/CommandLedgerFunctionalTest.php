<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Functional;

use ArcaCodicum\CommandLedger\Tests\Fake\TestConnectionFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpKernel\Kernel;

final class CommandLedgerFunctionalTest extends TestCase
{
    private string $commandsDirectory;
    private Kernel $kernel;
    private Application $application;

    protected function setUp(): void
    {
        TestConnectionFactory::reset();
        $this->commandsDirectory = sys_get_temp_dir().'/cl-func-'.bin2hex(random_bytes(6));
        mkdir($this->commandsDirectory);
        $this->kernel = new TestKernel('test', true, $this->commandsDirectory);
        $this->kernel->boot();
        $this->application = new Application($this->kernel);
        $this->application->setAutoExit(false);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();

        foreach (glob($this->commandsDirectory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->commandsDirectory);
    }

    private function writeCommandFile(string $fileName, string $className, string $upBody): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use ArcaCodicum\\CommandLedger\\AbstractCommandFile;

final class {$className} extends AbstractCommandFile
{
    public function up(): void
    {
        {$upBody}
    }

    public function down(): void
    {
        \$this->execute('test:ok');
    }
}

PHP;
        file_put_contents($this->commandsDirectory.'/'.$fileName, $content);
    }

    private function runCommand(string $name, array $input = []): ApplicationTester
    {
        $tester = new ApplicationTester($this->application);
        $tester->run(['command' => $name] + $input);

        return $tester;
    }

    private function connection(): Connection
    {
        return $this->kernel->getContainer()->get('doctrine.dbal.default_connection');
    }

    /** @return list<array<string, mixed>> */
    private function records(): array
    {
        return $this->connection()->fetchAllAssociative('SELECT * FROM command_ledger_executions ORDER BY version ASC');
    }

    public function testSetupMigrateStatusAndRollback(): void
    {
        $this->writeCommandFile(
            '20260101120000_functional_ok.php',
            'Version20260101120000FunctionalOk',
            "\$this->execute('test:ok');",
        );

        self::assertSame(0, $this->runCommand('command-ledger:setup')->getStatusCode());
        self::assertSame([], $this->records());

        $migrate = $this->runCommand('command-ledger:migrate');
        self::assertSame(0, $migrate->getStatusCode());
        self::assertStringContainsString('EXECUTED', $migrate->getDisplay());

        $rows = $this->records();
        self::assertCount(1, $rows);
        self::assertSame('success', $rows[0]['status']);
        self::assertSame(0, (int) $rows[0]['exit_code']);
        self::assertStringContainsString('ok', (string) $rows[0]['stdout']);
        self::assertNotNull($rows[0]['finished_at']);

        $second = $this->runCommand('command-ledger:migrate');
        self::assertSame(0, $second->getStatusCode());
        self::assertStringContainsString('SKIPPED', $second->getDisplay());

        $status = $this->runCommand('command-ledger:status');
        self::assertSame(0, $status->getStatusCode());
        self::assertStringContainsString('success', $status->getDisplay());

        $rollback = $this->runCommand('command-ledger:rollback');
        self::assertSame(0, $rollback->getStatusCode());
        self::assertStringContainsString('REVERTED', $rollback->getDisplay());
        self::assertSame([], $this->records());
    }

    public function testFailureIsRecordedAndReportedByStatus(): void
    {
        $this->writeCommandFile(
            '20260101120001_functional_fail.php',
            'Version20260101120001FunctionalFail',
            "throw new \\RuntimeException('functional failure');",
        );

        self::assertSame(0, $this->runCommand('command-ledger:setup')->getStatusCode());

        $migrate = $this->runCommand('command-ledger:migrate');
        self::assertSame(1, $migrate->getStatusCode());
        self::assertStringContainsString('FAILED', $migrate->getDisplay());

        $rows = $this->records();
        self::assertCount(1, $rows);
        self::assertSame('failed', $rows[0]['status']);
        self::assertStringContainsString('functional failure', (string) $rows[0]['stderr']);

        $status = $this->runCommand('command-ledger:status');
        self::assertStringContainsString('failed', $status->getDisplay());
    }

    public function testBashHelpersAreExecutedAndRecorded(): void
    {
        $scriptPath = $this->commandsDirectory.'/example-script.sh';
        file_put_contents($scriptPath, "#!/usr/bin/env bash\nprintf 'script:%s\\n' \"\$1\"\n");

        $upBody = sprintf(
            "\$this->executeScript(%s, ['hello world']);\n        \$this->executeBash('printf bash-functional');",
            var_export($scriptPath, true),
        );

        $this->writeCommandFile(
            '20260101120002_functional_bash.php',
            'Version20260101120002FunctionalBash',
            $upBody,
        );

        self::assertSame(0, $this->runCommand('command-ledger:setup')->getStatusCode());

        $migrate = $this->runCommand('command-ledger:migrate');
        self::assertSame(0, $migrate->getStatusCode());
        self::assertStringContainsString('EXECUTED', $migrate->getDisplay());

        $rows = $this->records();
        self::assertCount(1, $rows);
        self::assertSame('success', $rows[0]['status']);
        self::assertStringContainsString('script:hello world', (string) $rows[0]['stdout']);
        self::assertStringContainsString('bash-functional', (string) $rows[0]['stdout']);
    }
}
