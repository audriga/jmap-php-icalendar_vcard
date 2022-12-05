<?php

namespace OpenXPort\Test\Core;

use PHPUnit\Framework\Testcase;
use OpenXPort\Jmap\Calendar\CalendarEvent;

/**
 * Deserealization of JSCalendar events from JSON files.
 */
final class OpenXPortCoreTest extends Testcase
{
    /** @var \OpenXPort\Jmap\Calendar\CalendarEvent */
    protected $jsCalendarEvent = null;

    public function setUp(): void
    {

    }

    public function tearDown(): void
    {
        $this->jsCalendarEvent = null;
    }

    /**
     * Parse a simple JSCalendar without any custom objects as properties.
     */
    public function testParseBasicEvent()
    {
        $this->jsCalendarEvent = CalendarEvent::fromJson(__DIR__ . '/../resources/jscalendar_basic.json', 'r');
    }
}