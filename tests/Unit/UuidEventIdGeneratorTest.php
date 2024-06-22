<?php

declare(strict_types=1);

use Imarkin\EventSauce\CycleMessageRepository\UuidEventIdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class UuidEventIdGeneratorTest extends \PHPUnit\Framework\TestCase
{
    public static function validVersions(): \Generator
    {
        yield [1];
        yield [4];
        yield [6];
        yield [7];
    }

    public static function invalidVersions(): \Generator
    {
        yield [2];
        yield [3];
        yield [5];
        yield [-1];
        yield [11];
    }

    #[DataProvider('validVersions')]
    #[Test]
    public function it_generate_uuid_of_version(int $version): void
    {
        $generator = new UuidEventIdGenerator(version: $version);

        $uuidString = $generator->generate();

        $uuid = \Ramsey\Uuid\Uuid::fromString($uuidString);

        $this->assertEquals($version, $uuid->getFields()->getVersion());
    }

    #[DataProvider('invalidVersions')]
    #[Test]
    public function it_can_not_create_unsupported_uuid(int $version): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UuidEventIdGenerator(version: $version);
    }
}
