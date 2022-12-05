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
    protected $jsCalendar = null;

    public function setUp(): void
    {

    }

    public function tearDown(): void
    {
        $this->jsCalendar = null;
    }

    /**
     * Parse a simple JSCalendar without any custom objects as properties.
     */
    public function testParseBasicEvent()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . '/../resources/jscalendar_basic.json')
        );

        $this->assertEquals("Event", $this->jsCalendar->getType());
        $this->assertEquals("a8df6573-0474-496d-8496-033ad45d7fea", $this->jsCalendar->getUid());
        $this->assertEquals("2020-01-02T18:23:04Z", $this->jsCalendar->getUpdated());
        $this->assertEquals("Some event", $this->jsCalendar->getTitle());
        $this->assertEquals("2020-01-15T13:00:00", $this->jsCalendar->getStart());
        $this->assertEquals("America/New_York", $this->jsCalendar->getTimeZone());
        $this->assertEquals("PT1H", $this->jsCalendar->getDuration());
    }
}
