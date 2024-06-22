<?php

declare(strict_types=1);

namespace Imarkin\EventSauce\CycleMessageRepository;

interface EventIdGenerator
{
    public function generate(): string;
}
