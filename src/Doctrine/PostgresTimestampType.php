<?php
namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;

final class PostgresTimestampType extends DateTimeTzImmutableType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        // PostgreSQL includes fractional seconds only when the stored value has them.
        return is_string($value) ? new \DateTimeImmutable($value) : parent::convertToPHPValue($value,$platform);
    }
}
