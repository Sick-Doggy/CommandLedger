<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Integration\Persistence;

use ArcaCodicum\CommandLedger\Exception\TableNotInitializedException;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepository;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ExecutionRepositoryTest extends TestCase
{
    private Connection $connection;
    private ExecutionRepository $repository;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->repository = new ExecutionRepository($this->connection, 'command_ledger_executions');
    }

    public function testTableIsCreatedIdempotently(): void
    {
        self::assertFalse($this->repository->tableExists());

        $this->repository->createTable();
        $this->repository->createTable();

        self::assertTrue($this->repository->tableExists());
    }

    public function testFindAllRequiresInitializedTable(): void
    {
        $this->expectException(TableNotInitializedException::class);

        $this->repository->findAll();
    }

    public function testRunningAndSuccessArePersisted(): void
    {
        $this->repository->createTable();
        $startedAt = new \DateTimeImmutable('2026-01-01 12:00:00');

        $this->repository->markRunning('20260101120000', '20260101120000_ok.php', $startedAt);
        $this->repository->markSuccess(
            '20260101120000',
            0,
            "ok\n",
            '',
            $startedAt,
            $startedAt->modify('+2 seconds'),
            2000,
        );

        $record = $this->repository->findAll()['20260101120000'];
        self::assertSame(ExecutionStatus::SUCCESS, $record->status);
        self::assertSame(0, $record->exitCode);
        self::assertSame("ok\n", $record->stdout);
        self::assertSame('2026-01-01 12:00:00', $record->startedAt?->format('Y-m-d H:i:s'));
        self::assertSame(2000, $record->durationMs);
    }

    public function testFailedIsPersistedAndFindSuccessfulFilters(): void
    {
        $this->repository->createTable();
        $startedAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $this->repository->markRunning('20260101120000', 'a.php', $startedAt);
        $this->repository->markFailed('20260101120000', 3, '', 'boom', $startedAt, $startedAt, 0);
        $this->repository->markRunning('20260101120001', 'b.php', $startedAt);
        $this->repository->markSuccess('20260101120001', 0, '', '', $startedAt, $startedAt, 0);

        self::assertSame(
            ExecutionStatus::FAILED,
            $this->repository->findAll()['20260101120000']->status,
        );
        self::assertArrayHasKey('20260101120001', $this->repository->findSuccessful());
        self::assertArrayNotHasKey('20260101120000', $this->repository->findSuccessful());
    }

    public function testDeleteRemovesRecord(): void
    {
        $this->repository->createTable();
        $this->repository->markRunning('20260101120000', 'a.php', new \DateTimeImmutable());

        $this->repository->delete('20260101120000');

        self::assertSame([], $this->repository->findAll());
    }

    public function testCreatesTwoTablesInOneSchema(): void
    {
        $second = new ExecutionRepository($this->connection, 'second_command_ledger');

        $this->repository->createTable();
        $second->createTable();

        self::assertTrue($this->repository->tableExists());
        self::assertTrue($second->tableExists());
    }

    public function testSupportsReservedWordTableName(): void
    {
        $repository = new ExecutionRepository($this->connection, 'order');
        $startedAt = new \DateTimeImmutable('2026-01-01 12:00:00');

        $repository->createTable();
        $repository->markRunning('20260101120000', 'a.php', $startedAt);
        $repository->markSuccess('20260101120000', 0, '', '', $startedAt, $startedAt, 0);

        self::assertSame(
            ExecutionStatus::SUCCESS,
            $repository->findAll()['20260101120000']->status,
        );

        $repository->delete('20260101120000');
        self::assertSame([], $repository->findAll());
    }
}
