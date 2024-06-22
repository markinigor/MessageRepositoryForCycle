<?php

declare(strict_types=1);

namespace Tests\Unit;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TestTooling\MessageRepositoryTestCase;
use Imarkin\EventSauce\CycleMessageRepository\CycleMessageRepository;
use Ramsey\Uuid\Uuid;
use Tests\Dummy\DummyAggregateRootId;
use Tests\Trait\RefreshDatabase;

final class CycleMessageRepositoryTest extends MessageRepositoryTestCase
{
    use RefreshDatabase;

    protected string $tableName = 'domain_messages_uuid';

    protected function setUp(): void
    {
        parent::setUp();

        $this->initConnection();
        $this->refreshTables();
    }

    protected function eventId(): string
    {
        return Uuid::uuid4()->toString();
    }

    protected function aggregateRootId(): AggregateRootId
    {
        return DummyAggregateRootId::generate();
    }

    protected function messageRepository(): MessageRepository
    {
        return new CycleMessageRepository(
            database: $this->dbal->database(),
            tableName: $this->tableName,
            serializer: new ConstructingMessageSerializer(),
            tableSchema: new DefaultTableSchema(),
            aggregateRootIdEncoder: new StringIdEncoder(),
        );
    }
}
