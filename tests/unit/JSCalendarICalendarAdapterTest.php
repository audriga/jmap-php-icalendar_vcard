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
    protected $jsCalendarBefore = null;

    /** @var \OpenXPort\Jmap\Calendar\CalendarEvent */
    protected $jsCalendarAfter = null;

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
        $this->jsCalendarBefore = null;
        $this->jsCalendarAfter = null;
    }

    /**
     * Map any iCalendar file to a JSCalendarEvent object by providing the relative path.
     */
    private function mapICalendar($path)
    {
        $this->iCalendar = Reader::read(
            fopen(__DIR__ . $path, 'r')
        );

        $this->iCalendarData = array("1" => array("iCalendar" => $this->iCalendar->serialize()));
        $jsCalendarAfter = $this->mapper->mapToJmap($this->iCalendarData, $this->adapter);

        // A bit dirty, but this is a test class.. meh
        if (sizeof($jsCalendarAfter) > 1) {
            $this->jsCalendarAfter = $jsCalendarAfter;
        } else {
            $this->jsCalendarAfter = $jsCalendarAfter[0];
        }
    }

    /* *
     * Map JSCalendar -> JSCalendar
     */
    public function mapJSCalendar($path)
    {
        $this->jsCalendarBefore = CalendarEvent::fromJson(file_get_contents($path));

        $this->iCalendarData = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarBefore), $this->adapter);
        $this->iCalendar = Reader::read($this->iCalendarData[0]["c1"]["iCalendar"]);

        $jsCalendarAfter = $this->mapper->mapToJmap(reset($this->iCalendarData), $this->adapter);

        // A bit dirty, but this is a test class.. meh
        if (sizeof($jsCalendarAfter) > 1) {
            $this->jsCalendarAfter = $jsCalendarAfter;
        } else {
            $this->jsCalendarAfter = $jsCalendarAfter[0];
        }
    }

    /* *
     * Map iCalendar -> JSCalendar
     */
    public function testMapICalendar()
    {
        $this->mapICalendar('/../resources/test_icalendar.ics');

        $this->assertEquals($this->jsCalendarAfter->getTitle(), "Just a Test");
        $this->assertEquals($this->jsCalendarAfter->getUid(), "20f78720-d755-4de7-92e5-e41af487e4db");
        $this->assertEquals($this->jsCalendarAfter->getCreated(), "2014-01-07T09:20:11Z");
        $this->assertEquals($this->jsCalendarAfter->getStart(), "2014-01-02T11:00:00");
        $this->assertEquals($this->jsCalendarAfter->getDuration(), "PT1H");
        $this->assertEquals($this->jsCalendarAfter->getUpdated(), "2014-01-07T12:15:03Z");
        $this->assertEquals($this->jsCalendarAfter->getTimezone(), "Europe/Berlin");
    }

    /**
     * Map iCalendar -> JSCalendar using Nextcloud generated data
     */
    public function testMapICalendarExtended()
    {
        $this->mapICalendar('/../resources/nextcloud_conversion_event_1.ics');

        // Check for most basic properties.
        $this->assertEquals(
            $this->jsCalendarAfter->getDescription(),
            "Event with a tag, a notification\nand a recurrence."
        );
        $this->assertEquals($this->jsCalendarAfter->getSequence(), "3");
        $this->assertEquals($this->jsCalendarAfter->getStatus(), "confirmed");
        $this->assertEquals($this->jsCalendarAfter->getColor(), "palevioletred");
        $this->assertEquals($this->jsCalendarAfter->getKeywords(), array("Holiday" => true));
        $this->assertEquals(
            array_values($this->jsCalendarAfter->getLocations())[0]->getName(),
            "Some Hotel, Some Country"
        );
        $this->assertEquals($this->jsCalendarAfter->getProdId(), "-//IDN nextcloud.com//Calendar app 3.4.3//EN");
        $this->assertEquals($this->jsCalendarAfter->getPrivacy(), "private");
        $this->assertTrue($this->jsCalendarAfter->getShowWithoutTime());

        // Check for reucrrenceRules.
        $this->assertEquals($this->jsCalendarAfter->getRecurrenceRules()[0]->getFrequency(), "yearly");
        $this->assertEquals($this->jsCalendarAfter->getRecurrenceRules()[0]->getbyMonth(), array("9"));

        // Check for alerts.
        $this->assertEquals(sizeof($this->jsCalendarAfter->getAlerts()), 3);
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["2"]->getTrigger()->getType(), "OffsetTrigger");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["2"]->getTrigger()->getOffset(), "-PT5M");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["2"]->getTrigger()->getRelativeTo(), "start");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["3"]->getTrigger()->getRelativeTo(), "end");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["2"]->getAction(), "display");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["1"]->getTrigger()->getType(), "AbsoluteTrigger");
        $this->assertEquals($this->jsCalendarAfter->getAlerts()["1"]->getTrigger()->getWhen(), "2022-05-08T12:00:00Z");
        // Check that no value is accidentaly overwritten if it was set in a previous alert.
        $this->assertNotEquals(
            $this->jsCalendarAfter->getAlerts()["2"]->getTrigger()->getOffset(),
            $this->jsCalendarAfter->getAlerts()["3"]->getTrigger()->getOffset()
        );
        $this->assertNotEquals(
            $this->jsCalendarAfter->getAlerts()["1"]->getTrigger()->getType(),
            $this->jsCalendarAfter->getAlerts()["3"]->getTrigger()->getType()
        );

        //Check for participants.
        $participants = $this->jsCalendarAfter->getParticipants();
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
     * Map multiple ICal events from a single file to jmap.
     */
    public function testRecurringICalEvent()
    {
        $this->mapICalendar('/../resources/recurring_event_with_changed_occurrence.ics');

        $overrides = $this->jsCalendarAfter->getRecurrenceOverrides();
        // Check whether the key has been set correctly and the overrides were mapped successfully.
        $this->assertTrue(in_array("2022-10-15T00:00:00", array_keys($overrides)));
        $this->assertEquals($overrides["2022-10-15T00:00:00"]->getDescription(), "added description");
    }

    public function testMultipleICalEvents()
    {
        $this->mapICalendar('/../resources/calendar_with_two_events.ics');

        // Check that no null value is overwritten with values from the first event.
        $this->assertEquals($this->jsCalendarAfter[0]->getStatus(), "confirmed");
        $this->assertNull($this->jsCalendarAfter[1]->getStatus());

        // Make sure that all the properties that should be different are different.
        $this->assertNotEquals($this->jsCalendarAfter[0]->getTitle(), $this->jsCalendarAfter[1]->getTitle());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getDescription(), $this->jsCalendarAfter[1]->getDescription());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getDuration(), $this->jsCalendarAfter[1]->getDuration());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getUpdated(), $this->jsCalendarAfter[1]->getUpdated());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getUid(), $this->jsCalendarAfter[1]->getUid());

        // Make sure that all the properties that should match do so.
        $this->assertEquals($this->jsCalendarAfter[0]->getProdId(), $this->jsCalendarAfter[1]->getProdId());
        $this->assertEquals($this->jsCalendarAfter[0]->getSequence(), $this->jsCalendarAfter[1]->getSequence());
        $this->assertEquals($this->jsCalendarAfter[0]->getTimezone(), $this->jsCalendarAfter[1]->getTimezone());
    }


    /* *
     * Map JSCalendar -> iCalendar -> JSCalendar
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects, not
     *      just single properties.
     */
    public function testRoundtrip()
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_basic.json');

        // Assert that the value of the properties is still the same
        $this->assertEquals($this->jsCalendarBefore->getTitle(), $this->jsCalendarAfter->getTitle());
        $this->assertEquals($this->jsCalendarBefore->getUpdated(), $this->jsCalendarAfter->getUpdated());
        $this->assertEquals($this->jsCalendarBefore->getUid(), $this->jsCalendarAfter->getUid());
        $this->assertEquals($this->jsCalendarBefore->getStart(), $this->jsCalendarAfter->getStart());
        $this->assertEquals($this->jsCalendarBefore->getDuration(), $this->jsCalendarAfter->getDuration());
        $this->assertEquals($this->jsCalendarBefore->getTimeZone(), $this->jsCalendarAfter->getTimezone());
    }

    /**
     * Map JSCalendar -> iCalendar -> JSCalendar using an extended set of properties.
     */
    public function testRoundtripExtended()
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_extended.json');

        // Makes sure that the objects are created correctly.
        $this->assertEquals($this->jsCalendarBefore->getTitle(), $this->jsCalendarAfter->getTitle());
        $this->assertEquals($this->jsCalendarBefore->getDescription(), $this->jsCalendarAfter->getDescription());
        $this->assertEquals($this->jsCalendarBefore->getSequence(), $this->jsCalendarAfter->getSequence());
        $this->assertEquals($this->jsCalendarBefore->getStatus(), $this->jsCalendarAfter->getStatus());
        $this->assertEquals($this->jsCalendarBefore->getFreeBusyStatus(), $this->jsCalendarAfter->getFreeBusyStatus());
        $this->assertEquals($this->jsCalendarBefore->getPriority(), $this->jsCalendarAfter->getPriority());
        $this->assertEquals($this->jsCalendarBefore->getPrivacy(), $this->jsCalendarAfter->getPrivacy());
        $this->assertEquals(
            json_encode($this->jsCalendarBefore->getKeywords()),
            json_encode($this->jsCalendarAfter->getKeywords())
        );
        $this->assertEquals(
            $this->jsCalendarBefore->getLocations()["1"]->getName(),
            $this->jsCalendarAfter->getLocations()["1"]->getName()
        );
        $this->assertEquals($this->jsCalendarBefore->getProdId(), $this->jsCalendarAfter->getProdId());
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceRules()[0]->getFrequency(),
            $this->jsCalendarAfter->getRecurrenceRules()[0]->getFrequency()
        );
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceRules()[0]->getByMonth(),
            $this->jsCalendarAfter->getRecurrenceRules()[0]->getByMonth()
        );
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceRules()[0]->getByDay()[0]->getDay(),
            $this->jsCalendarAfter->getRecurrenceRules()[0]->getByDay()[0]->getDay()
        );
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceRules()[0]->getByDay()[0]->getNthOfPeriod(),
            $this->jsCalendarAfter->getRecurrenceRules()[0]->getByDay()[0]->getNthOfPeriod()
        );

        // Check for correct mapping of alerts.
        $this->assertEquals(sizeof($this->jsCalendarAfter->getAlerts()), 2);

        $firstAlertBefore = $this->jsCalendarBefore->getAlerts()[1];
        $firstAlertAfter = $this->jsCalendarAfter->getAlerts()[1];
        $this->assertEquals($firstAlertBefore->getTrigger()->getOffset(), $firstAlertAfter->getTrigger()->getOffset());
        $this->assertEquals(
            $firstAlertBefore->getTrigger()->getRelativeTo(),
            $firstAlertAfter->getTrigger()->getRelativeTo()
        );
        $this->assertEquals($firstAlertBefore->getAction(), $firstAlertAfter->getAction());

        $firstAlertBefore = $this->jsCalendarBefore->getAlerts()[2];
        $firstAlertAfter = $this->jsCalendarAfter->getAlerts()[2];
        $this->assertEquals($firstAlertBefore->getTrigger()->getWhen(), $firstAlertBefore->getTrigger()->getwhen());
        $this->assertEquals($firstAlertAfter->getAction(), $firstAlertAfter->getAction());

        //Ceck for mapping of participants.
        $mappedParticipants = $this->jsCalendarAfter->getParticipants();
        $this->assertEquals(sizeof($this->jsCalendarBefore->getParticipants()), sizeof($mappedParticipants));
        // Check first participant.
        $currentParticipant = $this->jsCalendarBefore->getParticipants()["dG9tQGZvb2Jhci5xlLmNvbQ"];
        $currentMappedParticipant = reset($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals($currentParticipant->getLanguage(), $currentMappedParticipant->getLanguage());
        $this->assertEquals(
            $currentParticipant->getParticipationStatus(),
            $currentMappedParticipant->getParticipationStatus()
        );
        $this->assertEquals(
            $currentParticipant->getRoles()["attendee"],
            $currentMappedParticipant->getRoles()["attendee"]
        );
        $this->assertEquals(
            $currentParticipant->getScheduleAgent(),
            $currentMappedParticipant->getScheduleAgent()
        );
        $this->assertEquals(
            $currentParticipant->getScheduleForceSend(),
            $currentMappedParticipant->getScheduleForceSend()
        );
        $this->assertEquals($currentParticipant->getScheduleStatus(), $currentMappedParticipant->getScheduleStatus());
        // Check second participant and owner.
        $currentParticipant = $this->jsCalendarBefore->getParticipants()["em9lQGZvb2GFtcGxlLmNvbQ"];
        $currentMappedParticipant = next($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getSendTo()["imip"], $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals(
            $currentParticipant->getParticipationStatus(),
            $currentMappedParticipant->getParticipationStatus()
        );
        $this->assertEquals(
            $currentParticipant->getRoles()["owner"],
            $currentMappedParticipant->getRoles()["owner"]
        );
        $this->assertEquals(
            $currentParticipant->getRoles()["attendee"],
            $currentMappedParticipant->getRoles()["attendee"]
        );
        $this->assertEquals($currentParticipant->getRoles()["chair"], $currentMappedParticipant->getRoles()["chair"]);
        $this->assertNotEquals($currentParticipant->getScheduleAgent(), $currentMappedParticipant->getScheduleAgent());
        // Check third participant and owner.
        $currentParticipant = $this->jsCalendarBefore->getParticipants()["ajksdgasjgjgdleqwueqwe"];
        $currentMappedParticipant = end($mappedParticipants);
        $this->assertEquals($currentParticipant->getName(), $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->getExpectReply(), $currentMappedParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->getSendTo()["other"], $currentMappedParticipant->getSendTo()["other"]);
        $this->assertEquals(
            $currentParticipant->getRoles()["attendee"],
            $currentMappedParticipant->getRoles()["attendee"]
        );
        $this->assertEquals(
            $currentParticipant->getRoles()["optional"],
            $currentMappedParticipant->getRoles()["optional"]
        );
    }

    public function testRecurrenceOverrideRoundtrip()
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_with_recurrence_overrides.json');

        // Check that the recurrence ids were mapped correctly.
        $this->assertSameSize(
            array_keys($this->jsCalendarBefore->getRecurrenceOverrides()),
            array_keys($this->jsCalendarAfter->getRecurrenceOverrides())
        );

        // Check that the excluded ocurrences are still contained.
        $this->assertTrue($this->jsCalendarAfter->getRecurrenceOverrides()["2020-04-02T13:00:00"]->getExcluded());
        $this->assertStringContainsString(
            "EXDATE;TZID=America/New_York:20200402T130000,20200209T130000",
            $this->iCalendarData[0]["c1"]["iCalendar"]
        );
        // Check that the title was changed and does not equal the one set for the master event.
        $this->assertNotEquals(
            $this->jsCalendarBefore->getTitle(),
            $this->jsCalendarAfter->getRecurrenceOverrides()["2020-01-08T13:00:00"]->getTitle()
        );

        // Check that the title of a single override matches
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceOverrides()["2020-06-26T13:00:00"]->getTitle(),
            $this->jsCalendarAfter->getRecurrenceOverrides()["2020-06-26T13:00:00"]->getTitle()
        );

        // Check if the overrides have the same UID as the master event even though it is not set in the json file.
        $this->assertEquals($this->iCalendar->VEVENT[0]->UID->getValue(), $this->iCalendar->VEVENT[1]->UID->getValue());
    }

    public function testRecurrenceRuleRoundtrip()
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_with_recurrence_rule.json');

        $this->assertIsArray($this->jsCalendarBefore->getRecurrenceRules()[1]->getByDay());
        $this->assertIsArray($this->jsCalendarAfter->getRecurrenceRules()[1]->getByDay());
    }

    public function testMultipleEventsRoundtrip()
    {
        $PATH = __DIR__ . '/../resources/jscalendar_two_events.json';
        $this->jsCalendarBefore = CalendarEvent::fromJson(file_get_contents($PATH));

        $iCalendarData = $this->mapper->mapFromJmap(
            array("c1" => $this->jsCalendarBefore[0], "c2" => $this->jsCalendarBefore[1]),
            $this->adapter
        );

        $this->jsCalendarAfter = $this->mapper->mapToJmap(
            array(reset($iCalendarData[0]), reset($iCalendarData[1])),
            $this->adapter
        );

        // Check that properties were mapped correctly to their counterpart.
        $this->assertEquals($this->jsCalendarBefore[0]->getTitle(), $this->jsCalendarAfter[0]->getTitle());
        $this->assertEquals($this->jsCalendarBefore[1]->getTitle(), $this->jsCalendarAfter[1]->getTitle());

        $this->assertEquals($this->jsCalendarBefore[0]->getStart(), $this->jsCalendarAfter[0]->getStart());
        $this->assertEquals($this->jsCalendarBefore[1]->getStart(), $this->jsCalendarAfter[1]->getStart());

        $this->assertEquals($this->jsCalendarBefore[0]->getTimeZone(), $this->jsCalendarAfter[0]->getTimezone());
        $this->assertEquals($this->jsCalendarBefore[1]->getTimeZone(), $this->jsCalendarAfter[1]->getTimezone());

        $this->assertEquals($this->jsCalendarBefore[0]->getUid(), $this->jsCalendarAfter[0]->getUid());
        $this->assertEquals($this->jsCalendarBefore[1]->getUid(), $this->jsCalendarAfter[1]->getUid());

        $this->assertEquals($this->jsCalendarBefore[0]->getDuration(), $this->jsCalendarAfter[0]->getDuration());
        $this->assertEquals($this->jsCalendarBefore[1]->getDuration(), $this->jsCalendarAfter[1]->getDuration());

        // Test whether properties are overwirtten by previous events.
        $this->assertEquals($this->jsCalendarBefore[0]->getDescription(), $this->jsCalendarAfter[0]->getDescription());
        $this->assertNotNull($this->jsCalendarAfter[0]->getDescription());
        $this->assertNull($this->jsCalendarAfter[1]->getDescription());

        $this->assertNotEmpty($this->jsCalendarAfter[0]->getRecurrenceRules());
        $this->assertEmpty($this->jsCalendarAfter[1]->getRecurrenceRules());

        $this->assertNotEmpty($this->jsCalendarAfter[0]->getRecurrenceOverrides());
        $this->assertEmpty($this->jsCalendarAfter[1]->getRecurrenceOverrides());

        // Make sure that none of the properties were overwritten incorrectly.
        $this->assertNotEquals($this->jsCalendarAfter[0]->getTitle(), $this->jsCalendarAfter[1]->getTitle());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getTimezone(), $this->jsCalendarAfter[1]->getTimezone());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getUid(), $this->jsCalendarAfter[1]->getUid());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getStart(), $this->jsCalendarAfter[1]->getStart());
        $this->assertNotEquals($this->jsCalendarAfter[0]->getDuration(), $this->jsCalendarAfter[1]->getDuration());

        // Check that both of the events are saved with @type Event.
        $this->assertEquals($this->jsCalendarAfter[0]->getType(), $this->jsCalendarAfter[1]->getType());
    }

    /**
     * Test whether time zones are converted correctly.
     */
    public function testTimeZoneParsing(): void
    {
        $this->mapICalendar('/../resources/icalendar_in_utc.ics');
        $iCalendarDataAfter = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarAfter), $this->adapter)[0];

        $iCalendarAfter = Reader::read($iCalendarDataAfter["c1"]["iCalendar"]);

        $this->assertEquals(
            $this->iCalendar->VEVENT->DTSTART->getValue(),
            $iCalendarAfter->VEVENT->DTSTART->getValue()
        );
        $this->assertEquals("2023-02-07T12:00:00", $this->jsCalendarAfter->getStart());
        $this->assertEquals("Etc/UTC", $this->jsCalendarAfter->getTimeZone());
        // Make sure the UTC time zone info is not lost.
        $this->assertEquals("20230207T120000Z", $iCalendarAfter->VEVENT->DTSTART->getValue());

        $this->assertEquals($this->iCalendar->VEVENT->DTEND->getValue(), $iCalendarAfter->VEVENT->DTEND->getValue());
        $this->assertEquals("PT1H", $this->jsCalendarAfter->getDuration());

        $this->assertEquals($this->iCalendar->VEVENT->RRULE->getValue(), $iCalendarAfter->VEVENT->RRULE->getValue());
        $this->assertEquals("2023-02-10T18:00:00", $this->jsCalendarAfter->getRecurrenceRules()[0]->getUntil());
        //Make sure the UTC is also not lost here.
        $this->assertEquals("FREQ=DAILY;UNTIL=20230210T180000Z", $iCalendarAfter->VEVENT->RRULE->getValue());
    }

    public function testFullDayRoundtrip(): void
    {
        $PATH = __DIR__ . '/../resources/jscalendar_full_day_event.json';
        $this->jsCalendarBefore = CalendarEvent::fromJson(file_get_contents($PATH));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarBefore), $this->adapter);

        $this->jsCalendarAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Make sure that the full day event information is carried all the way through.
        $this->assertTrue($this->jsCalendarBefore->getShowWithoutTime());
        $this->assertEquals($this->jsCalendarBefore->getShowWithoutTime(), $this->jsCalendarAfter->getShowWithoutTime());
        $this->assertStringContainsString("DTSTART;VALUE=DATE:19000401", $iCalendarData[0]["c1"]["iCalendar"]);
        $this->assertStringContainsString("DTEND;VALUE=DATE:19000402", $iCalendarData[0]["c1"]["iCalendar"]);
    }

    /**
     * Test protocol-specific code
     */
    public function testProtocolProperties(): void
    {
        $PATH = __DIR__ . '/../resources/jscalendar_extended.json';
        $this->jsCalendarBefore = CalendarEvent::fromJson(file_get_contents($PATH));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarBefore), $this->adapter);
        $this->jsCalendarAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter)[0];

        // Makes sure that the objects are created correctly.
        $this->assertEquals("c1", $this->jsCalendarAfter->getId());
    }

    public function testRecurrenceIdTimeZone(): void
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_with_localdt_recurrenceid.json');

        $this->assertEquals(
            "RECURRENCE-ID;TZID=America/Los_Angeles:20230503T050000",
            str_replace("\r\n", "", $this->iCalendar->VEVENT[1]->{"RECURRENCE-ID"}->serialize())
        );

        $this->assertNull($this->jsCalendarAfter->getRecurrenceOverrides()["2023-05-03T05:00:00"]->getTimeZone());
        $this->assertEquals(
            $this->jsCalendarBefore->getRecurrenceOverrides()["2023-05-03T05:00:00"]->getStart(),
            $this->jsCalendarAfter->getRecurrenceOverrides()["2023-05-03T05:00:00"]->getStart()
        );
    }

    /**
     * An event coming from Google Calendar that caused trouble
     */
    public function testGoogleEventRoundtrip(): void
    {

        $FILE_PATH = __DIR__ . '/../resources/jscalendar_google_modex.json';
        $this->jsCalendarBefore = CalendarEvent::fromJson(file_get_contents($FILE_PATH));

        $iCalendarData = $this->mapper->mapFromJmap(array("c1" => $this->jsCalendarBefore), $this->adapter);

        $this->jsCalendarAfter = $this->mapper->mapToJmap(reset($iCalendarData), $this->adapter);

        // Check that properties were mapped correctly to their counterpart.
        $this->assertEquals($this->jsCalendarBefore->getTitle(), $this->jsCalendarAfter[0]->getTitle());

        $this->assertEquals($this->jsCalendarBefore->getStart(), $this->jsCalendarAfter[0]->getStart());

        $this->assertEquals($this->jsCalendarBefore->getTimeZone(), $this->jsCalendarAfter[0]->getTimezone());

        $this->assertEquals($this->jsCalendarBefore->getUid(), $this->jsCalendarAfter[0]->getUid());

        $this->assertEquals($this->jsCalendarBefore->getDuration(), $this->jsCalendarAfter[0]->getDuration());

        // Test whether properties are overwirtten by previous events.
        $this->assertEquals($this->jsCalendarBefore->getDescription(), $this->jsCalendarAfter[0]->getDescription());
        $this->assertEquals(
            array_values($this->jsCalendarBefore->getRecurrenceOverrides()["2022-02-11T11:00:00"]->getAlerts())[0]->getType(),
            array_values($this->jsCalendarAfter[0]->getRecurrenceOverrides()["2022-02-11T11:00:00"]->getAlerts())[0]->getType()
        );
        $this->assertNotEmpty($this->jsCalendarAfter[0]->getRecurrenceRules());

        $this->assertNotEmpty($this->jsCalendarAfter[0]->getRecurrenceOverrides());
    }

    /**
     * An event with showWithoutTime set tot true in master and false in override
     */
    public function testShowWithoutTimeOverride(): void
    {
        $this->mapJSCalendar(__DIR__ . '/../resources/jscalendar_overrides_and_fullday.json');

        // Should be a date value. If not, showWIthoutTime was not applied in the adapter.
        $this->assertEquals(
            "DTSTART;VALUE=DATE:20230501",
            str_replace("\r\n", "", $this->iCalendar->VEVENT[0]->DTSTART->serialize())
        );

        // Should be a date value. If not, the showWithoutTime property was not applied to the recurrence id
        $this->assertEquals(
            "RECURRENCE-ID;VALUE=DATE:20230503",
            str_replace("\r\n", "", $this->iCalendar->VEVENT[1]->{"RECURRENCE-ID"}->serialize())
        );

        // Should also be a data value. If not, the showWithoutTime property from the master event was not added to the override.
        $this->assertEquals(
            "DTSTART;VALUE=DATE:20230522",
            str_replace("\r\n", "", $this->iCalendar->VEVENT[1]->DTSTART->serialize())
        );

        $this->assertEquals(
            "DTEND;VALUE=DATE:20230523",
            str_replace("\r\n", "", $this->iCalendar->VEVENT[1]->DTEND->serialize())
        );

        // showWithoutTime should only be set for the master event. Also, the time part of the DateTime in the recurrence id
        // is inevitably lost.
        $this->assertTrue($this->jsCalendarAfter->getShowWithoutTime());
        $this->assertNull($this->jsCalendarAfter->getRecurrenceOverrides()["2023-05-03T00:00:00"]->getShowWithoutTime());
    }

    public function testMapICalendarAttach() {
        $this->mapICalendar(__DIR__ . '/../resources/icalendar_with_attach.ics');

        $this->assertCount(1, $this->jsCalendarAfter[0]->getLinks());
        $this->assertEquals("enclosure", $this->jsCalendarAfter[0]->getLinks()[0]->getRel());
        $this->assertEquals("U0ZMb2dObwlTRkxvYWR
        lZERhdGUNCjkxNzY3NC8xCTI3LzExLzIwMTIgMTg6MzANCjkxMjIwNS8xCTI3LzExLzIwMTIgM
        Tg6MzANCjkxMjI0Ni8xCTI3LzExLzIwMTIgMTg6MzANCjkxMjI1Mi8xCTI3LzExLzIwMTIgMTg
        6MzANCjkxMjQyMS8xCTI3LzExLzIwMTIgMTg6MzANCjkxMjQyMi8xCTI3LzExLzIwMTIgMTg6M
        zANCjkxNTMyMS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNTQzNS8xCTI3LzExLzIwMTIgMTg6MzA
        NCjkxNTU5OS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNjc3NC8xCTI3LzExLzIwMTIgMTg6MzANC
        jkxNjk1OS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNjk2MC8xCTI3LzExLzIwMTIgMTg6MzANCjk
        xNzM2Ny8xCTI3LzExLzIwMTIgMTg6MzANCjkxNzQzNC8xCTI3LzExLzIwMTIgMTg6MzANCjkxN
        DczMS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDczMi8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc
        0My8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc0NC8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc0N
        S8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc0Ni8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc2MS8
        xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc2Mi8xCTI3LzExLzIwMTIgMTg6MzANCjkxNDc2My8xC
        TI3LzExLzIwMTIgMTg6MzANCjkxNTYzNS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNTYzOC8xCTI
        3LzExLzIwMTIgMTg6MzANCjkxNTY0MC8xCTI3LzExLzIwMTIgMTg6MzANCjkxNTY0MS8xCTI3L
        zExLzIwMTIgMTg6MzANCjkxNTY1OS8xCTI3LzExLzIwMTIgMTg6MzANCjkxNTc3Ni8xCTI3LzE
        xLzIwMTIgMTg6MzANCjkxNTc3Ny8xCTI3LzExLzIwMTIgMTg6MzANCjkxNTc3OC8xCTI3LzExL
        zIwMTIgMTg6MzANCg==", $this->jsCalendarAfter->getLinks()[0]->getHref());
    }
}
