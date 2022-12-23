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

        // Check the parsing of the first location.
        $curentLocation = reset($this->jsCalendar->getLocations());
        $this->assertEquals("Location", $curentLocation->getType());
        $this->assertEquals("Conference Room 101", $curentLocation->getName());
        $this->assertEquals("Biggest conference room in the upper level of the main building", $curentLocation->getDescription());
        $this->assertEquals("Europe/Amsterdam", $curentLocation->getTimeZone());
        $this->assertEquals("geo:49.00937,8.40444", $curentLocation->getCoordinates());
        
        // Check the parsing of the second location.
        $curentLocation = next($this->jsCalendar->getLocations());
        $this->assertEquals("Location", $curentLocation->getType());
        $this->assertEquals("Flight to New York", $curentLocation->getName());
        $this->assertEquals("Starting point of a flight from Stuttgart Airport to New York JFK", $curentLocation->getDescription());
        $this->assertEquals("Europe/Amsterdam", $curentLocation->getTimeZone());
        $this->assertEquals("start", $curentLocation->getRelativeTo());
        $this->assertEquals("geo:48.687330584,9.219832454", $curentLocation->getCoordinates());
    }

    public function testParseEventWithLinks()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_links.json")
        );

        $currentLink = current($this->jsCalendar->getLinks());
        $this->assertEquals("Link", $currentLink->getType());
        $this->assertEquals("some://example.com:1234/some/dir?name=foo#bar", $currentLink->getHref());
        $this->assertEquals("image", $currentLink->getContentType());
        $this->assertEquals(512, $currentLink->getSize());
        $this->assertEquals("current", $currentLink->getRel());
        $this->assertEquals("fullsize", $currentLink->getDisplay());
        // Currently not active due to conflicts in the property naming conventions.
        //$this->assertEquals("foo.png", $currentLink->getTitle());
    }

    public function testParseEventWithAlerts()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_alerts.json")
        );

        $currentAlert = $this->jsCalendar->getAlerts()["1"];
        $this->assertEquals("Alert", $currentAlert->getType());
        $this->assertEquals("display", $currentAlert->getAction());
        $this->assertEquals("AbsoluteTrigger", $currentAlert->getTrigger()->getType());
        $this->assertEquals("2022-12-05T18:00:00", $currentAlert->getTrigger()->getWhen());

        $currentAlert = $this->jsCalendar->getAlerts()["2"];
        $this->assertEquals("Alert", $currentAlert->getType());
        $this->assertEquals("email", $currentAlert->getAction());
        $this->assertEquals("OffsetTrigger", $currentAlert->getTrigger()->getType());
        $this->assertEquals("-PT30M", $currentAlert->getTrigger()->getOffset());
        $this->assertEquals("end", $currentAlert->getTrigger()->getRelativeTo());


        $currentAlert = $this->jsCalendar->getAlerts()["3"];
        $this->assertEquals("Alert", $currentAlert->getType());
        $this->assertEquals("display", $currentAlert->getAction());
        $this->assertEquals("CompletionTrigger", $currentAlert->getTrigger()->getType());
    }

    public function testParseEventWithRecurrenceRules()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_recurrence_rule.json")
        );

        var_dump(json_decode(file_get_contents(__DIR__ . "/../resources/jscalendar_with_recurrence_rule.json")));

        var_dump($this->jsCalendar);
    }
}
