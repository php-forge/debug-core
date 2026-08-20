<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\Panel\User\UserRbacRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserRbacRow} covering hydration from the normalized adapter array shape: string coercion for
 * textual fields, timestamp narrowing (`int`, numeric-string, non-numeric) and missing-key defaults.
 */
#[Group('panel')]
#[Group('user')]
final class UserRbacRowTest extends TestCase
{
    public function testConstructorExposesAllPropertiesVerbatim(): void
    {
        $row = new UserRbacRow(
            name: 'admin',
            description: 'Administrator',
            ruleName: 'isAdmin',
            data: '{"scope":"all"}',
            createdAt: 1_700_000_000,
            updatedAt: 1_700_000_001,
        );

        self::assertSame(
            'admin',
            $row->name,
            'Name must be exposed verbatim.',
        );
        self::assertSame(
            'Administrator',
            $row->description,
            'Description must be exposed verbatim.',
        );
        self::assertSame(
            'isAdmin',
            $row->ruleName,
            'Rule name must be exposed verbatim.',
        );
        self::assertSame(
            '{"scope":"all"}',
            $row->data,
            'Data must be exposed verbatim.',
        );
        self::assertSame(
            1_700_000_000,
            $row->createdAt,
            'Created-at timestamp must be exposed verbatim.',
        );
        self::assertSame(
            1_700_000_001,
            $row->updatedAt,
            'Updated-at timestamp must be exposed verbatim.',
        );
    }

    public function testFromArrayCastsNumericStringTimestampsToInt(): void
    {
        $row = UserRbacRow::fromArray(
            [
                'name' => 'editor',
                'createdAt' => '1700000000',
                'updatedAt' => '1700000001',
            ],
        );

        self::assertSame(
            1_700_000_000,
            $row->createdAt,
            'Numeric string must be cast to `int`.',
        );
        self::assertSame(
            1_700_000_001,
            $row->updatedAt,
            'Numeric string must be cast to `int`.',
        );
    }

    public function testFromArrayCoercesNonStringTextualFieldsToEmptyStrings(): void
    {
        $row = UserRbacRow::fromArray(
            [
                'name' => 42,
                'description' => ['nested'],
                'ruleName' => null,
                'data' => 3.14,
                'createdAt' => 1_700_000_000,
                'updatedAt' => 1_700_000_001,
            ],
        );

        self::assertSame(
            '',
            $row->name,
            'Non-string name must collapse to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->description,
            'Non-string description must collapse to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->ruleName,
            'Non-string rule name must collapse to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->data,
            'Non-string data must collapse to an empty `string`.',
        );
    }

    public function testFromArrayDefaultsMissingKeysToEmptyStringsAndNullTimestamps(): void
    {
        $row = UserRbacRow::fromArray([]);

        self::assertSame(
            '',
            $row->name,
            'Missing name must default to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->description,
            'Missing description must default to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->ruleName,
            'Missing rule name must default to an empty `string`.',
        );
        self::assertSame(
            '',
            $row->data,
            'Missing data must default to an empty `string`.',
        );
        self::assertNull(
            $row->createdAt,
            'Missing created-at must default to `null`.',
        );
        self::assertNull(
            $row->updatedAt,
            'Missing updated-at must default to `null`.',
        );
    }

    public function testFromArrayHydratesAllFieldsFromCompleteRow(): void
    {
        $row = UserRbacRow::fromArray(
            [
                'name' => 'admin',
                'description' => 'Administrator',
                'ruleName' => 'isAdmin',
                'data' => '{"scope":"all"}',
                'createdAt' => 1_700_000_000,
                'updatedAt' => 1_700_000_001,
            ],
        );

        self::assertSame(
            'admin',
            $row->name,
            'Name must be hydrated.',
        );
        self::assertSame(
            'Administrator',
            $row->description,
            'Description must be hydrated.',
        );
        self::assertSame(
            'isAdmin',
            $row->ruleName,
            'Rule name must be hydrated.',
        );
        self::assertSame(
            '{"scope":"all"}',
            $row->data,
            'Data must be hydrated.',
        );
        self::assertSame(
            1_700_000_000,
            $row->createdAt,
            'Integer created-at must pass through unchanged.',
        );
        self::assertSame(
            1_700_000_001,
            $row->updatedAt,
            'Integer updated-at must pass through unchanged.',
        );
    }

    public function testFromArrayRejectsNonNumericTimestamps(): void
    {
        $row = UserRbacRow::fromArray(
            [
                'createdAt' => 'yesterday',
                'updatedAt' => [],
            ],
        );

        self::assertNull(
            $row->createdAt,
            "Non-numeric created-at must collapse to 'null'.",
        );
        self::assertNull(
            $row->updatedAt,
            "Non-numeric updated-at must collapse to 'null'.",
        );
    }

    public function testFromArrayTruncatesFloatTimestampsToInt(): void
    {
        $row = UserRbacRow::fromArray(
            [
                'createdAt' => 1_700_000_000.9,
                'updatedAt' => 1_700_000_001.9,
            ],
        );

        self::assertSame(
            1_700_000_000,
            $row->createdAt,
            "Float created-at must be truncated to 'int'.",
        );
        self::assertSame(
            1_700_000_001,
            $row->updatedAt,
            "Float updated-at must be truncated to 'int'.",
        );
    }
}
