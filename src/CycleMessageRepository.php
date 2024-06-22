<?php

declare(strict_types=1);

namespace Imarkin\EventSauce\CycleMessageRepository;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Query\SelectQuery;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\OffsetCursor;
use EventSauce\EventSourcing\PaginationCursor;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\UnableToPersistMessages;
use EventSauce\EventSourcing\UnableToRetrieveMessages;
use EventSauce\IdEncoding\BinaryUuidIdEncoder;
use EventSauce\IdEncoding\IdEncoder;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\MessageRepository\TableSchema\TableSchema;
use Generator;

class CycleMessageRepository implements MessageRepository
{
    private TableSchema $tableSchema;
    private IdEncoder $aggregateRootIdEncoder;
    private IdEncoder $eventIdEncoder;
    private EventIdGenerator $eventIdGenerator;

    public function __construct(
        private DatabaseInterface $database,
        private string $tableName,
        private MessageSerializer $serializer,
        private int $jsonEncodeOptions = 0,
        ?TableSchema $tableSchema = null,
        ?IdEncoder $aggregateRootIdEncoder = null,
        ?IdEncoder $eventIdEncoder = null,
        ?EventIdGenerator $eventIdGenerator = null,
    ) {
        $this->tableSchema = $tableSchema ?? new DefaultTableSchema();
        $this->aggregateRootIdEncoder = $aggregateRootIdEncoder ?? new BinaryUuidIdEncoder();
        $this->eventIdEncoder = $eventIdEncoder ?? $this->aggregateRootIdEncoder;
        $this->eventIdGenerator = $eventIdGenerator ?? new UuidEventIdGenerator(7);
    }

    public function persist(Message ...$messages): void
    {
        if (\count($messages) === 0) {
            return;
        }

        $insertColumns = [
            $this->tableSchema->eventIdColumn(),
            $this->tableSchema->aggregateRootIdColumn(),
            $this->tableSchema->versionColumn(),
            $this->tableSchema->payloadColumn(),
            ...array_keys($additionalColumns = $this->tableSchema->additionalColumns()),
        ];

        $insert = $this->database
            ->insert($this->tableName)
            ->columns(
                $insertColumns,
            );

        foreach ($messages as $message) {
            $payload = $this->serializer->serializeMessage($message);
            $payload['headers'][Header::EVENT_ID] ??= $this->eventIdGenerator->generate();

            $messageParameters = [
                $this->eventIdEncoder->encodeId($payload['headers'][Header::EVENT_ID]),
                $this->aggregateRootIdEncoder->encodeId($message->aggregateRootId()),
                $payload['headers'][Header::AGGREGATE_ROOT_VERSION] ?? 0,
                \json_encode($payload, $this->jsonEncodeOptions),
            ];

            foreach ($additionalColumns as $header) {
                $messageParameters[] = $payload['headers'][$header];
            }

            $insert->values($messageParameters);
        }


        try {
            $insert->run();
        } catch (\Throwable $exception) {
            throw UnableToPersistMessages::dueTo('', $exception);
        }
    }

    public function retrieveAll(AggregateRootId $id): \Generator
    {
        $query = $this->createQueryBuilder();
        $query->where(
            $this->tableSchema->aggregateRootIdColumn(),
            $this->aggregateRootIdEncoder->encodeId($id),
        );

        try {
            return $this->yieldMessagesFromPayloads($query->fetchAll());
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo('', $exception);
        }
    }

    public function retrieveAllAfterVersion(
        AggregateRootId $id,
        int $aggregateRootVersion,
    ): \Generator {
        $query = $this->createQueryBuilder()
            ->where(
                $this->tableSchema->aggregateRootIdColumn(),
                $this->aggregateRootIdEncoder->encodeId($id),
            )->where(
                $this->tableSchema->versionColumn(),
                '>',
                $aggregateRootVersion,
            );

        try {
            return $this->yieldMessagesFromPayloads($query->fetchAll());
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo('', $exception);
        }
    }

    public function paginate(PaginationCursor $cursor): \Generator
    {
        if (!$cursor instanceof OffsetCursor) {
            throw new \LogicException(sprintf('Wrong cursor type used, expected %s, received %s', OffsetCursor::class, get_class($cursor)));
        }

        $offset = $cursor->offset();
        $incrementalIdColumn = $this->tableSchema->incrementalIdColumn();

        $builder = $this->database
            ->select($incrementalIdColumn, $this->tableSchema->payloadColumn())
            ->from($this->tableName)
            ->orderBy($incrementalIdColumn, 'ASC')
            ->limit($cursor->limit())
            ->where($incrementalIdColumn, '>', $cursor->offset());

        try {
            foreach ($builder->fetchAll() as $row) {
                $offset = $row[$incrementalIdColumn];
                yield $this->serializer->unserializePayload(json_decode($row['payload'], true));
            }
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo($exception->getMessage(), $exception);
        }

        return $cursor->withOffset($offset);
    }

    private function createQueryBuilder(): SelectQuery
    {
        return $this->database->select($this->tableSchema->payloadColumn())
            ->from($this->tableName)
            ->orderBy($this->tableSchema->versionColumn(), 'ASC');
    }


    /**
     * @psalm-return Generator<Message>
     */
    private function yieldMessagesFromPayloads(iterable $payloads): \Generator
    {
        foreach ($payloads as $payload) {
            yield $message = $this->serializer->unserializePayload(\json_decode($payload[
                $this->tableSchema->payloadColumn()
            ], true));
        }

        return isset($message)
            ? $message->header(Header::AGGREGATE_ROOT_VERSION) ?: 0
            : 0;
    }
}
