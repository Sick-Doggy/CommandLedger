<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Persistence;

use ArcaCodicum\CommandLedger\Exception\TableNotInitializedException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

final class ExecutionRepository implements ExecutionRepositoryInterface
{
    private readonly string $quotedTableName;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName,
    ) {
        $this->quotedTableName = $connection->quoteIdentifier($tableName);
    }

    public function findAll(): array
    {
        $this->assertTableExists();

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s ORDER BY version ASC', $this->quotedTableName),
        );

        $records = [];
        foreach ($rows as $row) {
            $record = $this->hydrate($row);
            $records[$record->version] = $record;
        }

        return $records;
    }

    public function findSuccessful(): array
    {
        return array_filter(
            $this->findAll(),
            static fn (ExecutionRecord $record): bool => $record->status === ExecutionStatus::SUCCESS,
        );
    }

    public function markRunning(string $version, string $fileName, \DateTimeImmutable $startedAt): void
    {
        $values = [
            'file_name' => $fileName,
            'status' => ExecutionStatus::RUNNING,
            'exit_code' => null,
            'stdout' => null,
            'stderr' => null,
            'started_at' => $this->formatDate($startedAt),
            'finished_at' => null,
            'duration_ms' => null,
        ];

        if ($this->exists($version)) {
            $this->connection->update($this->quotedTableName, $values, ['version' => $version]);

            return;
        }

        $this->connection->insert($this->quotedTableName, ['version' => $version] + $values);
    }

    public function markSuccess(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $this->writeResult($version, ExecutionStatus::SUCCESS, $exitCode, $stdout, $stderr, $startedAt, $finishedAt, $durationMs);
    }

    public function markFailed(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $this->writeResult($version, ExecutionStatus::FAILED, $exitCode, $stdout, $stderr, $startedAt, $finishedAt, $durationMs);
    }

    public function delete(string $version): void
    {
        $this->connection->delete($this->quotedTableName, ['version' => $version]);
    }

    public function tableExists(): bool
    {
        return $this->schemaManager()->tablesExist([$this->tableName]);
    }

    public function createTable(): void
    {
        if ($this->tableExists()) {
            return;
        }

        $schema = new Schema();
        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('version', Types::STRING, ['length' => 14]);
        $table->addColumn('file_name', Types::STRING, ['length' => 255]);
        $table->addColumn('status', Types::STRING, ['length' => 16]);
        $table->addColumn('exit_code', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('stdout', Types::TEXT, ['notnull' => false]);
        $table->addColumn('stderr', Types::TEXT, ['notnull' => false]);
        $table->addColumn('started_at', Types::DATETIME_MUTABLE);
        $table->addColumn('finished_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('duration_ms', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            ['version'],
            'uniq_cl_'.substr(hash('sha256', $this->tableName), 0, 24),
        );

        $this->schemaManager()->createTable($table);
    }

    private function writeResult(
        string $version,
        string $status,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $this->connection->update($this->quotedTableName, [
            'status' => $status,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'started_at' => $this->formatDate($startedAt),
            'finished_at' => $this->formatDate($finishedAt),
            'duration_ms' => $durationMs,
        ], ['version' => $version]);
    }

    private function exists(string $version): bool
    {
        return $this->connection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE version = ?', $this->quotedTableName),
            [$version],
        ) !== false;
    }

    private function assertTableExists(): void
    {
        if (!$this->tableExists()) {
            throw new TableNotInitializedException(sprintf(
                'Table "%s" does not exist. Run "command-ledger:setup" first.',
                $this->tableName,
            ));
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ExecutionRecord
    {
        return new ExecutionRecord(
            (int) $row['id'],
            (string) $row['version'],
            (string) $row['file_name'],
            (string) $row['status'],
            $row['exit_code'] !== null ? (int) $row['exit_code'] : null,
            $row['stdout'] !== null ? (string) $row['stdout'] : null,
            $row['stderr'] !== null ? (string) $row['stderr'] : null,
            $row['started_at'] !== null ? new \DateTimeImmutable((string) $row['started_at']) : null,
            $row['finished_at'] !== null ? new \DateTimeImmutable((string) $row['finished_at']) : null,
            $row['duration_ms'] !== null ? (int) $row['duration_ms'] : null,
        );
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /** @return AbstractSchemaManager<AbstractPlatform> */
    private function schemaManager(): AbstractSchemaManager
    {
        if (method_exists($this->connection, 'createSchemaManager')) { // @phpstan-ignore function.alreadyNarrowedType
            return $this->connection->createSchemaManager();
        }

        return $this->connection->getSchemaManager();
    }
}
