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

        // Check for most basic properties.
        $this->assertEquals($this->jsCalendarEvent->getDescription(), "Event with a tag, a notification\nand a recurrence.");
        $this->assertEquals($this->jsCalendarEvent->getSequence(), "3");
        $this->assertEquals($this->jsCalendarEvent->getStatus(), "confirmed");
        // color needs to be added to the CalendarEvent objects.
        // $this->assertEquals($this->jsCalendarEvent->getColor(), "palevioletred");
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
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"frequency"}, $jsCalendarDataAfter->getRecurrenceRules()[0]->getFrequency());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byMonth"}, $jsCalendarDataAfter->getRecurrenceRules()[0]->getByMonth());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byDay"}[0]->{"day"},
            $jsCalendarDataAfter->getRecurrenceRules()[0]->getByDay()->getDay());
        $this->assertEquals($jsCalendarData->recurrenceRules[0]->{"byDay"}[0]->{"nthOfPeriod"},
            $jsCalendarDataAfter->getRecurrenceRules()[0]->getByDay()->getNthOfPeriod());

        // Check for correct mapping of alerts.
        $this->assertEquals(sizeof($jsCalendarDataAfter->getAlerts()), 2);
        $this->assertEquals($jsCalendarData->alerts->{"1"}->trigger->offset, $jsCalendarDataAfter->getAlerts()[1]->getTrigger()->getOffset());
        $this->assertEquals($jsCalendarData->alerts->{"1"}->trigger->relativeTo, $jsCalendarDataAfter->getAlerts()[1]->getTrigger()->getRelativeTo()); 
        $this->assertEquals($jsCalendarData->alerts->{"2"}->trigger->when, $jsCalendarDataAfter->getAlerts()[2]->getTrigger()->getwhen());
        $this->assertEquals($jsCalendarData->alerts->{"1"}->action, $jsCalendarDataAfter->getAlerts()[1]->getAction());
        $this->assertEquals($jsCalendarData->alerts->{"2"}->action, $jsCalendarDataAfter->getAlerts()[2]->getAction());

        //Ceck for mapping of participants.
        $mappedParticipants = $jsCalendarDataAfter->getParticipants();
        $this->assertEquals(sizeof(get_object_vars($jsCalendarData->participants)), sizeof($mappedParticipants));
        // Check first participant.
        $currentParticipant = $jsCalendarData->participants->{"dG9tQGZvb2Jhci5xlLmNvbQ"};
        $currentMappedParticipant = reset($mappedParticipants);
        $this->assertEquals($currentParticipant->name, $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->sendTo->imip, $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals($currentParticipant->language, $currentMappedParticipant->getLanguage());
        $this->assertEquals($currentParticipant->participationStatus, $currentMappedParticipant->getParticipationStatus());
        $this->assertEquals($currentParticipant->roles->attendee, $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->scheduleAgent, $currentMappedParticipant->getScheduleAgent());
        $this->assertEquals($currentParticipant->scheduleForceSend, $currentMappedParticipant->getScheduleForceSend());
        $this->assertEquals($currentParticipant->scheduleStatus, $currentMappedParticipant->getScheduleStatus());
        // Check second participant and owner.
        $currentParticipant = $jsCalendarData->participants->{"em9lQGZvb2GFtcGxlLmNvbQ"};
        $currentMappedParticipant = next($mappedParticipants);
        $this->assertEquals($currentParticipant->name, $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->sendTo->imip, $currentMappedParticipant->getSendTo()["imip"]);
        $this->assertEquals($currentParticipant->participationStatus, $currentMappedParticipant->getParticipationStatus());
        $this->assertEquals($currentParticipant->roles->owner, $currentMappedParticipant->getRoles()["owner"]);
        $this->assertEquals($currentParticipant->roles->attendee, $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->roles->chair, $currentMappedParticipant->getRoles()["chair"]);
        $this->assertNotEquals($currentParticipant->scheduleAgent, $currentMappedParticipant->getScheduleAgent());
        // Check third participant and owner.
        $currentParticipant = $jsCalendarData->participants->{"ajksdgasjgjgdleqwueqwe"};
        $currentMappedParticipant = end($mappedParticipants);
        $this->assertEquals($currentParticipant->name, $currentMappedParticipant->getName());
        $this->assertEquals($currentParticipant->expectReply, $currentMappedParticipant->getExpectReply());
        $this->assertEquals($currentParticipant->sendTo->other, $currentMappedParticipant->getSendTo()["other"]);
        $this->assertEquals($currentParticipant->roles->attendee, $currentMappedParticipant->getRoles()["attendee"]);
        $this->assertEquals($currentParticipant->roles->optional, $currentMappedParticipant->getRoles()["optional"]);
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
}
