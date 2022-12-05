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

    /**
     * Parse a JSCalendar file containing every basic property an event can contain.
     */
    public function testParseAllBasicPropertiesEvent()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_all_basic_properties.json")
        );

        $this->assertEquals("Event", $this->jsCalendar->getType());
        $this->assertEquals("1234-5678-90-OpenXPort-TestFiles", $this->jsCalendar->getUid());
        $this->assertEquals("-//audriga//OpenXPort", $this->jsCalendar->getProdId());
        $this->assertEquals("2022-12-05T12:00:00", $this->jsCalendar->getStart());
        $this->assertEquals("Europe/Berlin", $this->jsCalendar->getTimeZone());
        $this->assertEquals("P1DT5H3M14S", $this->jsCalendar->getDuration());
        $this->assertEquals("confirmed", $this->jsCalendar->getStatus());
        $this->assertEquals("2022-12-02T13:14:15Z", $this->jsCalendar->getCreated());
        $this->assertEquals("2022-12-02T16:17:18Z", $this->jsCalendar->getUpdated());
        $this->assertEquals(2, $this->jsCalendar->getSequence());
        $this->assertEquals("Some other event", $this->jsCalendar->getTitle());
        $this->assertEquals("This is just some other event!", $this->jsCalendar->getDescription());
        $this->assertEquals("text/plain", $this->jsCalendar->getDescriptionContentType());
        $this->assertFalse($this->jsCalendar->getShowWithoutTime());
        $this->assertEquals(array("Test Events" => true, "Work" => true), $this->jsCalendar->getKeywords());
        $this->assertFalse($this->jsCalendar->getExcluded());
        $this->assertEquals(1, $this->jsCalendar->getPriority());
        $this->assertEquals("free", $this->jsCalendar->getFreeBusyStatus());
        $this->assertEquals("public", $this->jsCalendar->getPrivacy());
        $this->assertEquals("mailto:some.user@somedomain.com", $this->jsCalendar->getReplyTo()["imip"]);
    }

    public function testParseEventWithLocations()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_locations.json")
        );

        var_dump($this->jsCalendar);
    }
}
