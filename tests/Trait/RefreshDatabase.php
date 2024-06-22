<?php

declare(strict_types=1);

namespace Tests\Trait;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;

trait RefreshDatabase
{
    protected DatabaseManager $dbal;

    protected function initConnection(): void
    {
        $this->dbal = new DatabaseManager(
            new DatabaseConfig([
                'default' => 'default',
                'databases' => [
                    'default' => ['connection' => 'pgsql'],
                ],
                'connections' => [
                    'pgsql' => new PostgresDriverConfig(
                        new DsnConnectionConfig(
                            dsn: \sprintf(
                                'pgsql:host=%s;port=%s;dbname=%s',
                                \getenv('TEST_DB_HOST') ?: '127.0.0.1',
                                \getenv('TEST_DB_PORT') ?: '15432',
                                \getenv('TEST_DB_DATABASE') ?: 'spiral',
                            ),
                            user: \getenv('TEST_DB_USERNAME') ?: 'postgres',
                            password: \getenv('TEST_DB_PASSWORD') ?: 'postgres',
                        ),
                    ),
                ],
            ]),
        );
    }

    protected function refreshTables(): void
    {
        $sql = <<<SQL
            CREATE TABLE domain_messages_uuid (
                id SERIAL PRIMARY KEY,
                event_id uuid NOT NULL,
                aggregate_root_id uuid NOT NULL,
                version int NULL,
                payload jsonb NOT NULL
            )
            SQL;

        $this->dbal->database()->execute('DROP TABLE IF EXISTS domain_messages_uuid');
        $this->dbal->database()->execute($sql);

        $this->assertTrue(true);
    }
}
