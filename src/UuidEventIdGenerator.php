<?php

declare(strict_types=1);

namespace Imarkin\EventSauce\CycleMessageRepository;

use Ramsey\Uuid\Nonstandard\Uuid;

final readonly class UuidEventIdGenerator implements EventIdGenerator
{
    public function __construct(private int $version = 4)
    {
        \in_array($this->version, [
            1,
            4,
            6,
            7,
        ]) or throw new \InvalidArgumentException(
            \sprintf('Version %s is not supported', $this->version),
        );
    }

    public function generate(): string
    {
        return match ($this->version) {
            1 => Uuid::uuid1()->toString(),
            4 => Uuid::uuid4()->toString(),
            6 => Uuid::uuid6()->toString(),
            7 => Uuid::uuid7()->toString(),
        };
    }
}
