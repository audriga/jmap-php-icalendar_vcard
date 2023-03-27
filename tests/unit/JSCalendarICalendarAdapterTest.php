<?php

namespace OpenXPort\Test\ICalendar;

use OpenXPort\Adapter\JSCalendarICalendarAdapter;
use OpenXPort\Mapper\JSCalendarICalendarMapper;
use OpenXPort\Jmap\Calendar\CalendarEvent;
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
        $jsCalendarData = CalendarEvent::fromJson(file_get_contents(__DIR__ . '/../resources/jscalendar_basic.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];


        // Assert that the value of the properties is still the same
        $this->assertEquals($jsCalendarData->getTitle(), $jsCalendarDataAfter->getTitle());
        $this->assertEquals($jsCalendarData->getUpdated(), $jsCalendarDataAfter->getUpdated());
        $this->assertEquals($jsCalendarData->getUid(), $jsCalendarDataAfter->getUid());
        $this->assertEquals($jsCalendarData->getStart(), $jsCalendarDataAfter->getStart());
        $this->assertEquals($jsCalendarData->getDuration(), $jsCalendarDataAfter->getDuration());
        $this->assertEquals($jsCalendarData->getTimeZone(), $jsCalendarDataAfter->getTimezone());
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

        // Check for most basic properties.
        $this->assertEquals($this->jsCalendarEvent->getDescription(), "Event with a tag, a notification\nand a recurrence.");
        $this->assertEquals($this->jsCalendarEvent->getSequence(), "3");
        $this->assertEquals($this->jsCalendarEvent->getStatus(), "confirmed");
        $this->assertEquals($this->jsCalendarEvent->getColor(), "palevioletred");
        $this->assertEquals($this->jsCalendarEvent->getKeywords(), array("Holiday" => true));
        $this->assertEquals(array_values($this->jsCalendarEvent->getLocations())[0]->getName(), "Some Hotel, Some Country");
        $this->assertEquals($this->jsCalendarEvent->getProdId(), "-//IDN nextcloud.com//Calendar app 3.4.3//EN");
        $this->assertEquals($this->jsCalendarEvent->getPrivacy(), "private");

        // Check for reucrrenceRules.
        $this->assertEquals($this->jsCalendarEvent->getRecurrenceRules()[0]->getFrequency(), "yearly");
        $this->assertEquals($this->jsCalendarEvent->getRecurrenceRules()[0]->getbyMonth(), array("9"));

        // Check for alerts.
        $this->assertEquals(sizeof($this->jsCalendarEvent->getAlerts()), 3);
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["2"]->getTrigger()->getType(), "OffsetTrigger");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["2"]->getTrigger()->getOffset(), "-PT5M");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["2"]->getTrigger()->getRelativeTo(), "start");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["3"]->getTrigger()->getRelativeTo(), "end");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["2"]->getAction(), "display");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["1"]->getTrigger()->getType(), "AbsoluteTrigger");
        $this->assertEquals($this->jsCalendarEvent->getAlerts()["1"]->getTrigger()->getWhen(), "2022-05-08T12:00:00Z");
        // Check that no value is accidentaly overwritten if it was set in a previous alert.
        $this->assertNotEquals(
            $this->jsCalendarEvent->getAlerts()["2"]->getTrigger()->getOffset(),
            $this->jsCalendarEvent->getAlerts()["3"]->getTrigger()->getOffset()
        );
        $this->assertNotEquals(
            $this->jsCalendarEvent->getAlerts()["1"]->getTrigger()->getType(),
            $this->jsCalendarEvent->getAlerts()["3"]->getTrigger()->getType()
        );

        //Check for participants.
        $participants = $this->jsCalendarEvent->getParticipants();
        $this->assertEquals(sizeof($participants), 3);
        // 1st Attendee mapping.
        $currentParticipant = current($participants);
        $this->assertEquals($currentParticipant->getName(), "File Sharing User 2");
        $this->assertEquals($currentParticipant->getKind(), "individual");
        $this->assertEquals($currentParticipant->getRoles(), array("attendee" => true, "optional" => true));
        $this->assertNull($currentParticipant->getParticipationStatus());
        $this->assertTrue($currentParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->getLanguage(), "en");
        $this->assertNull($currentParticipant->getScheduleAgent());
        $this->assertFalse($currentParticipant->getScheduleForceSend());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], "mailto:user-2@file-sharing-test.com");
        // 2nd ATTENDEE mapping.
        $currentParticipant = next($participants);
        $this->assertEquals($currentParticipant->getName(), "File Sharing User 3");
        $this->assertEquals($currentParticipant->getKind(), "individual");
        $this->assertEquals($currentParticipant->getRoles(), array("informational" => true));
        $this->assertEquals($currentParticipant->getParticipationStatus(), "accepted");
        $this->assertNull($currentParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->getLanguage(), "en");
        $this->assertEquals($currentParticipant->getSCheduleAgent(), "client");
        $this->assertEquals($currentParticipant->getScheduleStatus(), array("1.1"));
        $this->assertTrue($currentParticipant->getScheduleForceSend());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], "mailto:user-3@file-sharing-test.com");
        // ORGANIZER mapping.
        $currentParticipant = end($participants);
        $this->assertEquals($currentParticipant->getName(), "File Sharing User 1");
        $this->assertNull($currentParticipant->getKind());
        $this->assertEquals($currentParticipant->getRoles(), array("owner" => true));
        $this->assertFalse($currentParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], "mailto:user-1@file-sharing-test.com");
    }

    /**
     * Map JSCalendar -> iCalendar -> JSCalendar using an extended set of properties.
     */
    public function testRoundtripExtended()
    {
        $jsCalendarData = CalendarEvent::fromJson(file_get_contents(__DIR__ . '/../resources/jscalendar_extended.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);
    
        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Makes sure that the objects are created correctly.
        $this->assertEquals($jsCalendarData->getTitle(), $jsCalendarDataAfter->getTitle());


        $this->assertEquals($jsCalendarData->getDescription(), $jsCalendarDataAfter->getDescription());
        $this->assertEquals($jsCalendarData->getSequence(), $jsCalendarDataAfter->getSequence());
        $this->assertEquals($jsCalendarData->getStatus(), $jsCalendarDataAfter->getStatus());
        $this->assertEquals($jsCalendarData->getFreeBusyStatus(), $jsCalendarDataAfter->getFreeBusyStatus());
        $this->assertEquals($jsCalendarData->getPrivacy(), $jsCalendarDataAfter->getPrivacy());
        $this->assertEquals(json_encode($jsCalendarData->getKeywords()), json_encode($jsCalendarDataAfter->getKeywords()));
        $this->assertEquals($jsCalendarData->getLocations()["1"]->getName(),
            $jsCalendarDataAfter->getLocations()["1"]->getName());
        $this->assertEquals($jsCalendarData->getProdId(), $jsCalendarDataAfter->getProdId());
        $this->assertEquals($jsCalendarData->getRecurrenceRules()[0]->getFrequency(), $jsCalendarDataAfter->getRecurrenceRules()[0]->getFrequency());
        $this->assertEquals($jsCalendarData->getRecurrenceRules()[0]->getByMonth(), $jsCalendarDataAfter->getRecurrenceRules()[0]->getByMonth());
        $this->assertEquals($jsCalendarData->getRecurrenceRules()[0]->getByDay()[0]->getDay(),
            $jsCalendarDataAfter->getRecurrenceRules()[0]->getByDay()->getDay());
        $this->assertEquals($jsCalendarData->getRecurrenceRules()[0]->getByDay()[0]->getNthOfPeriod(),
            $jsCalendarDataAfter->getRecurrenceRules()[0]->getByDay()->getNthOfPeriod());

        // Check for correct mapping of alerts.
        $this->assertEquals(sizeof($jsCalendarDataAfter->getAlerts()), 2);
        $this->assertEquals($jsCalendarData->getAlerts()[1]->getTrigger()->getOffset(), $jsCalendarDataAfter->getAlerts()[1]->getTrigger()->getOffset());
        $this->assertEquals($jsCalendarData->getAlerts()[1]->getTrigger()->getRelativeTo(), $jsCalendarDataAfter->getAlerts()[1]->getTrigger()->getRelativeTo()); 
        $this->assertEquals($jsCalendarData->getAlerts()[2]->getTrigger()->getWhen(), $jsCalendarDataAfter->getAlerts()[2]->getTrigger()->getwhen());
        $this->assertEquals($jsCalendarData->getAlerts()[1]->getAction(), $jsCalendarDataAfter->getAlerts()[1]->getAction());
        $this->assertEquals($jsCalendarData->getAlerts()[2]->getAction(), $jsCalendarDataAfter->getAlerts()[2]->getAction());

        //Ceck for mapping of participants.
        $mappedParticipants = $jsCalendarDataAfter->getParticipants();
        $this->assertEquals(sizeof($jsCalendarData->getParticipants()), sizeof($mappedParticipants));
        // Check first participant.
        $currentParticipant = $jsCalendarData->getParticipants()["dG9tQGZvb2Jhci5xlLmNvbQ"];
        $currentMappedParticipant = reset($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals($currentParticipant->getLanguage(), $currentMappedParticipant->getLanguage());
        $this->assertEquals($currentParticipant->getParticipationStatus(), $currentMappedParticipant->getParticipationStatus());
        $this->assertEquals($currentParticipant->getRoles()["attendee"], $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->getScheduleAgent(), $currentMappedParticipant->getScheduleAgent());
        $this->assertEquals($currentParticipant->getScheduleForceSend(), $currentMappedParticipant->getScheduleForceSend());
        $this->assertEquals($currentParticipant->getScheduleStatus(), $currentMappedParticipant->getScheduleStatus());
        // Check second participant and owner.
        $currentParticipant = $jsCalendarData->getParticipants()["em9lQGZvb2GFtcGxlLmNvbQ"];
        $currentMappedParticipant = next($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals($currentParticipant->getParticipationStatus(), $currentMappedParticipant->getParticipationStatus());
        $this->assertEquals($currentParticipant->getRoles()["owner"], $currentMappedParticipant->getRoles()["owner"]);
        $this->assertEquals($currentParticipant->getRoles()["attendee"], $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->getRoles()["chair"], $currentMappedParticipant->getRoles()["chair"]);
        $this->assertNotEquals($currentParticipant->getScheduleAgent(), $currentMappedParticipant->getScheduleAgent());
        // Check third participant and owner.
        $currentParticipant = $jsCalendarData->getParticipants()["ajksdgasjgjgdleqwueqwe"];
        $currentMappedParticipant = end($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getExpectReply(), $currentMappedParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->getSendTo()["other"], $currentMappedParticipant->getSendTo()["other"]);
        $this->assertEquals($currentParticipant->getRoles()["attendee"], $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->getRoles()["optional"], $currentMappedParticipant->getRoles()["optional"]);
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
        $jsCalendarData = CalendarEvent::fromJson(file_get_contents(__DIR__ . '/../resources/jscalendar_with_recurrence_overrides.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Check that the recurrence ids were mapped correctly.
        $this->assertEquals(array_keys($jsCalendarData->getRecurrenceOverrides()),
            array_keys($jsCalendarDataAfter->getRecurrenceOverrides())
        );

        // Check that the title was changed and does not equal the one set for the master event.
        $this->assertNotEquals($jsCalendarData->getTitle(),
            $jsCalendarDataAfter->getRecurrenceOverrides()["2020-01-08T14:00:00"]->getTitle()
        );

        // Check that the title of a single override matches
        $this->assertEquals($jsCalendarData->getRecurrenceOverrides()["2020-06-26T09:00:00"]->getTitle(),
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
        $jsCalendarData = CalendarEvent::fromJson(file_get_contents(__DIR__ . '/../resources/jscalendar_two_events.json'));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $jsCalendarData[0], "c2" => $jsCalendarData[1]), $this->adapter);

        $jsCalendarDataAfter = $this->mapper->mapToJmap(array("c1" => reset($iCalendarData[0]), "c2" => reset($iCalendarData[1])), $this->adapter);

        // Check that properties were mapped correctly to their counterpart.
        $this->assertEquals($jsCalendarData[0]->getTitle(), $jsCalendarDataAfter[0]->getTitle());
        $this->assertEquals($jsCalendarData[1]->getTitle(), $jsCalendarDataAfter[1]->getTitle());

        $this->assertEquals($jsCalendarData[0]->getStart(), $jsCalendarDataAfter[0]->getStart());
        $this->assertEquals($jsCalendarData[1]->getStart(), $jsCalendarDataAfter[1]->getStart());

        $this->assertEquals($jsCalendarData[0]->getTimeZone(), $jsCalendarDataAfter[0]->getTimezone());
        $this->assertEquals($jsCalendarData[1]->getTimeZone(), $jsCalendarDataAfter[1]->getTimezone());

        $this->assertEquals($jsCalendarData[0]->getUid(), $jsCalendarDataAfter[0]->getUid());
        $this->assertEquals($jsCalendarData[1]->getUid(), $jsCalendarDataAfter[1]->getUid());

        $this->assertEquals($jsCalendarData[0]->getDuration(), $jsCalendarDataAfter[0]->getDuration());
        $this->assertEquals($jsCalendarData[1]->getDuration(), $jsCalendarDataAfter[1]->getDuration());

        // Test whether properties are overwirtten by previous events.
        $this->assertEquals($jsCalendarData[0]->getDescription(), $jsCalendarDataAfter[0]->getDescription());
        $this->assertNotNull($jsCalendarDataAfter[0]->getDescription());
        $this->assertNull($jsCalendarDataAfter[1]->getDescription());

        $this->assertNotEmpty($jsCalendarDataAfter[0]->getRecurrenceRules());
        $this->assertEmpty($jsCalendarDataAfter[1]->getRecurrenceRules());

        $this->assertNotEmpty($jsCalendarDataAfter[0]->getRecurrenceOverrides());
        $this->assertEmpty($jsCalendarDataAfter[1]->getRecurrenceOverrides());
        
        // Make sure that none of the properties were overwritten incorrectly.
        $this->assertNotEquals($jsCalendarDataAfter[0]->getTitle(), $jsCalendarDataAfter[1]->getTitle());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getTimezone(), $jsCalendarDataAfter[1]->getTimezone());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getUid(), $jsCalendarDataAfter[1]->getUid());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getStart(), $jsCalendarDataAfter[1]->getStart());
        $this->assertNotEquals($jsCalendarDataAfter[0]->getDuration(), $jsCalendarDataAfter[1]->getDuration());

        // Check that both of the events are saved with @type Event.
        $this->assertEquals($jsCalendarDataAfter[0]->getType(), $jsCalendarDataAfter[1]->getType());
    }

    /** 
     * Test whether time zones are converted correctly.
     */
    public function testTimeZoneParsing(): void
    {
        $this->iCalendarData = Reader::read(
            fopen(__DIR__ . '/../resources/icalendar_in_utc.ics', 'r')
        );

        $this->iCalendar = array("1" => $this->iCalendarData->serialize());

        $this->jsCalendarEvent = $this->mapper->mapToJmap($this->iCalendar, $this->adapter)[0];

        $iCalendarAfter = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarEvent), $this->adapter)[0];

        $iCalendarDataAfter = Reader::read($iCalendarAfter["c1"]);

        $this->assertEquals($this->iCalendarData->VEVENT->DTSTART->getValue(), $iCalendarDataAfter->VEVENT->DTSTART->getValue());
        $this->assertEquals("2023-02-07T12:00:00", $this->jsCalendarEvent->getStart());
        $this->assertEquals("Etc/UTC", $this->jsCalendarEvent->getTimeZone());

        $this->assertEquals($this->iCalendarData->VEVENT->DTEND->getValue(), $iCalendarDataAfter->VEVENT->DTEND->getValue());
        $this->assertEquals("PT1H", $this->jsCalendarEvent->getDuration());
        
        $this->assertEquals($this->iCalendarData->VEVENT->RRULE->getValue(), $iCalendarDataAfter->VEVENT->RRULE->getValue());
        $this->assertEquals("2023-02-10T18:00:00", $this->jsCalendarEvent->getRecurrenceRules()[0]->getUntil());
    }
}
