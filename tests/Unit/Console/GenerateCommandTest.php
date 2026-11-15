<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Console;

use ArcaCodicum\CommandLedger\Console\GenerateCommand;
use ArcaCodicum\CommandLedger\Generator\CommandFileGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cl-gen-cmd-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    private function command(): GenerateCommand
    {
        return new GenerateCommand(new CommandFileGenerator(
            $this->directory,
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-01-01 12:00:00'),
        ));
    }

    public function testGeneratesFileFromArgument(): void
    {
        $tester = new CommandTester($this->command());
        $status = $tester->execute(['name' => 'import_products']);

        self::assertSame(0, $status);
        self::assertStringContainsString('20260101120000_import_products.php', $tester->getDisplay());
        self::assertFileExists($this->directory.'/20260101120000_import_products.php');
    }

    public function testAsksForNameInteractively(): void
    {
        $tester = new CommandTester($this->command());
        $tester->setInputs(['from_prompt']);
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertFileExists($this->directory.'/20260101120000_from_prompt.php');
    }

    public function testFailsWithoutNameInNonInteractiveMode(): void
    {
        $tester = new CommandTester($this->command());
        $status = $tester->execute([], ['interactive' => false]);

        self::assertSame(1, $status);
        self::assertStringContainsString('required in non-interactive mode', $tester->getDisplay());
    }
}
