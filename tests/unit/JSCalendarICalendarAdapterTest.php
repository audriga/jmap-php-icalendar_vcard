<?php

namespace OpenXPort\Test\ICalendar;

use OpenXPort\Adapter\JSCalendarICalendarAdapter;
use OpenXPort\Mapper\JSCalendarICalendarMapper;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

/**
 * Generic converting between iCalendar <-> JSContact
 */
final class JSCalendarICalendarAdapterTest extends TestCase
{
    /** @var \Sabre\VObject\Component\VCalendar */
    protected $iCalendar = null;

    /** @var \OpenXPort\Adapter\JSCalendarICalendarAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\JSCalendarICalendarMapper */
    protected $mapper = null;

    /** @var array */
    protected $iCalendarData = null;

    /** @var \OpenXPort\Jmap\Calendar\CalendarEvent */
    protected $jsCalendarEvent = null;

    public function setUp(): void
    {
        $this->adapter = new JSCalendarICalendarAdapter();
        $this->mapper = new JSCalendarICalendarMapper();
    }

    public function tearDown(): void
    {
        $this->iCalendar = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->iCalendarData = null;
        $this->jsCalendarEvent = null;
    }

    /* *
     * Map iCalendar -> JSCalendar
     */
    public function testMapICalendar()
    {
        $this->iCalendar = Reader::read(
            fopen(__DIR__ . '/../resources/test_icalendar.ics', 'r')
        );

        $this->iCalendarData = array("1" => $this->iCalendar->serialize());
        $this->jsCalendarEvent = $this->mapper->mapToJmap($this->iCalendarData, $this->adapter)[0];

        $this->assertEquals($this->jsCalendarEvent->getTitle(), "Just a Test");
        $this->assertEquals($this->jsCalendarEvent->getUid(), "20f78720-d755-4de7-92e5-e41af487e4db");
        $this->assertEquals($this->jsCalendarEvent->getCreated(), "2014-01-07T09:20:11Z");
        $this->assertEquals($this->jsCalendarEvent->getStart(), "2014-01-02T11:00:00");
        $this->assertEquals($this->jsCalendarEvent->getDuration(), "PT1H");
        $this->assertEquals($this->jsCalendarEvent->getUpdated(), "2014-01-07T12:15:03Z");
        $this->assertEquals($this->jsCalendarEvent->getTimezone(), "Europe/Berlin");
    }
    
    /* *
     * Map JSCalendar -> iCalendar -> JSCalendar
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects, not
     *      just single properties.
     */
    public function testRoundtrip()
    {
        $jsCalendarData = json_decode(file_get_contents(__DIR__ . '/../resources/jscalendar_basic.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];


        // Assert that the value of the properties is still the same
        $this->assertEquals($jsCalendarData->title, $jsCalendarDataAfter->getTitle());
        $this->assertEquals($jsCalendarData->updated, $jsCalendarDataAfter->getUpdated());
        $this->assertEquals($jsCalendarData->uid, $jsCalendarDataAfter->getUid());
        $this->assertEquals($jsCalendarData->start, $jsCalendarDataAfter->getStart());
        $this->assertEquals($jsCalendarData->duration, $jsCalendarDataAfter->getDuration());
        $this->assertEquals($jsCalendarData->timeZone, $jsCalendarDataAfter->getTimezone());
    }

    /**
     * Map iCalendar -> JSCalendar using Nextcloud generated data
     */
    public function testMapICalendarExtended()
    {
        $this->iCalendar = Reader::read(
            fopen(__DIR__ . '/../resources/nextcloud_conversion_event_1.ics', 'r')
        );
        
        $this->iCalendarData = array("1" => $this->iCalendar->serialize());
        $this->jsCalendarEvent = $this->mapper->mapToJmap($this->iCalendarData, $this->adapter)[0];

        $this->assertEquals($this->jsCalendarEvent->getDescription(), "Event with a tag, a notification\nand a recurrence.");
        $this->assertEquals($this->jsCalendarEvent->getSequence(), "3");
        $this->assertEquals($this->jsCalendarEvent->getStatus(), "confirmed");
        $this->assertEquals($this->jsCalendarEvent->getKeywords(), array("Holiday" => true));
        $this->assertEquals(array_values($this->jsCalendarEvent->getLocations())[0]->getName(), "Some Hotel, Some Country");
        $this->assertEquals($this->jsCalendarEvent->getProdId(), "-//IDN nextcloud.com//Calendar app 3.4.3//EN");
        $this->assertEquals($this->jsCalendarEvent->getPrivacy(), "private");
        $this->assertEquals($this->jsCalendarEvent->getRecurrenceRule()->getFrequency(), "yearly");
        $this->assertEquals($this->jsCalendarEvent->getRecurrenceRule()->getbyMonth(), array("9"));
    }

    /**
     * Map JSCalendar -> iCalendar -> JSCalendar using an extended set of properties.
     */
    public function testRoundtripExtended()
    {
        $jsCalendarData = json_decode(file_get_contents(__DIR__ . '/../resources/jscalendar_extended.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);
    
        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Makes sure that the objects are created correctly.
        $this->assertEquals($jsCalendarData->title, $jsCalendarDataAfter->getTitle());


        $this->assertEquals($jsCalendarData->description, $jsCalendarDataAfter->getDescription());
        $this->assertEquals($jsCalendarData->sequence, $jsCalendarDataAfter->getSequence());
        $this->assertEquals($jsCalendarData->status, $jsCalendarDataAfter->getStatus());
        $this->assertEquals($jsCalendarData->freeBusyStatus, $jsCalendarDataAfter->getFreeBusyStatus());
        $this->assertEquals($jsCalendarData->privacy, $jsCalendarDataAfter->getPrivacy());
        $this->assertEquals(json_encode($jsCalendarData->keywords), json_encode($jsCalendarDataAfter->getKeywords()));
        $this->assertEquals($jsCalendarData->locations->{'1'}->{'name'},
            $jsCalendarDataAfter->getLocations()["1"]->getName());
        $this->assertEquals($jsCalendarData->prodid, $jsCalendarDataAfter->getProdId());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"frequency"}, $jsCalendarDataAfter->getRecurrenceRule()->getFrequency());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byMonth"}, $jsCalendarDataAfter->getRecurrenceRule()->getByMonth());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byDay"}[0]->{"day"},
            $jsCalendarDataAfter->getRecurrenceRule()->getByDay()->getDay());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byDay"}[0]->{"nthOfPeriod"},
            $jsCalendarDataAfter->getRecurrenceRule()->getByDay()->getNthOfPeriod());
    }

    /**
     * Map multiple ICal events from a single file to jmap.
     */
    public function testRecurringICalEvent()
    {
        $this->iCalendar = Reader::read(
            fopen(__DIR__ . '/../resources/recurring_event_with_changed_occurrence.ics', 'r')
        );

        $this->iCalendarData = array("1" => $this->iCalendar->serialize());
        $this->jsCalendarEvent = $this->mapper->mapToJmap($this->iCalendarData, $this->adapter)[0];

        // Check whether the key has been set correctly and the overrides were mapped successfully.
        $this->assertTrue(in_array("2022-10-15T00:00:00", array_keys($this->jsCalendarEvent->getRecurrenceOverrides())));
        $this->assertEquals($this->jsCalendarEvent->getRecurrenceOverrides()["2022-10-15T00:00:00"]->getDescription(), "added description");
    }

    public function testRecurrenceOverrideRoundtrip()
    {
        $jsCalendarData = json_decode(file_get_contents(__DIR__ . '/../resources/jscalendar_with_recurrence_overrides.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Check that the recurrence ids were mapped correctly.
        $this->assertEquals(array_keys(get_object_vars($jsCalendarData->recurrenceOverrides)),
            array_keys($jsCalendarDataAfter->getRecurrenceOverrides())
        );

        // Check that the title was changed and does not equal the one set for the master event.
        $this->assertNotEquals($jsCalendarData->title,
            $jsCalendarDataAfter->getRecurrenceOverrides()["2020-01-08T14:00:00"]->getTitle()
        );

        // Check that the title of a single override matches
        $this->assertEquals($jsCalendarData->recurrenceOverrides->{"2020-06-26T09:00:00"}->title,
            $jsCalendarDataAfter->getRecurrenceOverrides()["2020-06-26T09:00:00"]->getTitle()
        );

    }

    public function testMultipleICalEvents()
    {
        $this->iCalendar = Reader::read(
            fopen(__DIR__ . '/../resources/calendar_with_two_events.ics', 'r')
        );

        $this->iCalendarData = array("1" => $this->iCalendar->serialize());

        $jsCalendarData = $this->mapper->mapToJmap($this->iCalendarData, $this->adapter);

        // Check that no null value is overwritten with values from the first event.
        $this->assertEquals($jsCalendarData[0]->getStatus(), "confirmed");
        $this->assertNull($jsCalendarData[1]->getStatus());

        // Make sure that all the properties that should be different are different.
        $this->assertNotEquals($jsCalendarData[0]->getTitle(), $jsCalendarData[1]->getTitle());
        $this->assertNotEquals($jsCalendarData[0]->getDescription(), $jsCalendarData[1]->getDescription());
        $this->assertNotEquals($jsCalendarData[0]->getDuration(), $jsCalendarData[1]->getDuration());
        $this->assertNotEquals($jsCalendarData[0]->getUpdated(), $jsCalendarData[1]->getUpdated());
        $this->assertNotEquals($jsCalendarData[0]->getUid(), $jsCalendarData[1]->getUid());
        
        // Make sure that all the properties that should match do so.
        $this->assertEquals($jsCalendarData[0]->getProdId(), $jsCalendarData[1]->getProdId());
        $this->assertEquals($jsCalendarData[0]->getSequence(), $jsCalendarData[1]->getSequence());
        $this->assertEquals($jsCalendarData[0]->getTimezone(), $jsCalendarData[1]->getTimezone());
    }

    public function testMultipleEventsRoundtrip()
    {
        $jsCalendarData = json_decode(file_get_contents(__DIR__ . '/../resources/jscalendar_two_events.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData[0], "c2" => $jsCalendarData[1]), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(array("c1" => reset($iCalendarData[0]), "c2" => reset($iCalendarData[1])), $this->adapter);

        // Check that properties were mapped correctly to their counterpart.
        $this->assertEquals($jsCalendarData[0]->title, $jsCalendarDataAfter[0]->getTitle());
        $this->assertEquals($jsCalendarData[1]->title, $jsCalendarDataAfter[1]->getTitle());

        $this->assertEquals($jsCalendarData[0]->start, $jsCalendarDataAfter[0]->getStart());
        $this->assertEquals($jsCalendarData[1]->start, $jsCalendarDataAfter[1]->getStart());

        $this->assertEquals($jsCalendarData[0]->timeZone, $jsCalendarDataAfter[0]->getTimezone());
        $this->assertEquals($jsCalendarData[1]->timeZone, $jsCalendarDataAfter[1]->getTimezone());

        $this->assertEquals($jsCalendarData[0]->uid, $jsCalendarDataAfter[0]->getUid());
        $this->assertEquals($jsCalendarData[1]->uid, $jsCalendarDataAfter[1]->getUid());

        $this->assertEquals($jsCalendarData[0]->duration, $jsCalendarDataAfter[0]->getDuration());
        $this->assertEquals($jsCalendarData[1]->duration, $jsCalendarDataAfter[1]->getDuration());

        // Test whether properties are overwirtten by previous events.
        $this->assertEquals($jsCalendarData[0]->description, $jsCalendarDataAfter[0]->getDescription());
        $this->assertNotNull($jsCalendarDataAfter[0]->getDescription());
        $this->assertNull($jsCalendarDataAfter[1]->getDescription());

        // Make sure that none of the properties were overwritten incorrectly.
        $this->assertNotEquals($jsCalendarDataAfter[0]->getTitle(), $jsCalendarDataAfter[1]->getTitle());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getTimezone(), $jsCalendarDataAfter[1]->getTimezone());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getUid(), $jsCalendarDataAfter[1]->getUid());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getStart(), $jsCalendarDataAfter[1]->getStart());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getDuration(), $jsCalendarDataAfter[1]->getDuration());

        // Check that both of the events are saved with @type Event.
        $this->assertEquals($jsCalendarDataAfter[0]->getType(), $jsCalendarDataAfter[1]->getType());
    }
}
