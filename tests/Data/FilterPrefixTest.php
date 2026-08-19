<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Data;

use PHPForge\Debug\Data\FilterPrefix;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see FilterPrefix} covering the cross-adapter query-group vocabulary.
 */
#[Group('data')]
#[Group('filter')]
final class FilterPrefixTest extends TestCase
{
    public function testConstantsExposeTheSharedFilterGroups(): void
    {
        self::assertSame(
            [
                'Asset',
                'Db',
                'Debug',
                'Event',
                'Log',
                'Mail',
                'Profile',
                'Queue',
                'Router',
                'Timeline',
                'User',
            ],
            [
                FilterPrefix::ASSET,
                FilterPrefix::DB,
                FilterPrefix::DEBUG,
                FilterPrefix::EVENT,
                FilterPrefix::LOG,
                FilterPrefix::MAIL,
                FilterPrefix::PROFILE,
                FilterPrefix::QUEUE,
                FilterPrefix::ROUTER,
                FilterPrefix::TIMELINE,
                FilterPrefix::USER,
            ],
            'Adapters must share one stable query-group vocabulary.',
        );
    }
}
