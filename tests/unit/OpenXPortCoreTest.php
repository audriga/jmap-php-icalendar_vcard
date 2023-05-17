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

        $locations = $this->jsCalendar->getLocations();

        // Check the parsing of the first location.
        $currentLocation = reset($locations);
        $this->assertEquals("Location", $currentLocation->getType());
        $this->assertEquals("Conference Room 101", $currentLocation->getName());
        $this->assertEquals("Biggest conference room in the upper level of the main building", $currentLocation->getDescription());
        $this->assertEquals("Europe/Amsterdam", $currentLocation->getTimeZone());
        $this->assertEquals("geo:49.00937,8.40444", $currentLocation->getCoordinates());
        
        // Check the parsing of the second location.
        $curentLocation = next($locations);
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

        $links = $this->jsCalendar->getLinks();

        $currentLink = current($links);
        $this->assertEquals("Link", $currentLink->getType());
        $this->assertEquals("some://example.com:1234/some/dir?name=foo#bar", $currentLink->getHref());
        $this->assertEquals("image", $currentLink->getContentType());
        $this->assertEquals(512, $currentLink->getSize());
        $this->assertEquals("current", $currentLink->getRel());
        $this->assertEquals("fullsize", $currentLink->getDisplay());
        $this->assertEquals("foo.png", $currentLink->getTitle());
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
        $this->assertEquals("2022-12-05T18:00:00Z", $currentAlert->getTrigger()->getWhen());

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

        // Check the recurrence rules.
        $recurrenceRule = $this->jsCalendar->getRecurrenceRules()[0];

        $this->assertEquals("RecurrenceRule", $recurrenceRule->getType());
        $this->assertEquals("weekly", $recurrenceRule->getFrequency());
        $this->assertEquals(6, $recurrenceRule->getCount());

        $recurrenceRule = $this->jsCalendar->getRecurrenceRules()[1];

        $this->assertEquals("RecurrenceRule", $recurrenceRule->getType());
        $this->assertEquals("NDay", $recurrenceRule->getByDay()[0]->getType());
        $this->assertEquals("su", $recurrenceRule->getByDay()[0]->getDay());
        $this->assertEquals("1", $recurrenceRule->getCount());
        
        // Check the recurrence overrides.
        $this->assertEquals(
            array_keys($this->jsCalendar->getRecurrenceOverrides()),
            array("2023-01-23T17:00:00", "2022-12-18T17:00:00", "2022-12-26T17:00:00")
        );

        $recurrenceOverrides = $this->jsCalendar->getRecurrenceOverrides();

        $recurrenceOverride = current($recurrenceOverrides);

        $this->assertTrue($recurrenceOverride instanceof CalendarEvent);
        $this->assertEquals("2023-01-23T15:00:00", $recurrenceOverride->getStart());
        $this->assertEquals("PT2H", $recurrenceOverride->getDuration());
        $this->assertEquals("Some Exam", $recurrenceOverride->getTitle());
        $this->assertEquals("Bring your own paper!", $recurrenceOverride->getDescription());

        $recurrenceOverride = next($recurrenceOverrides);

        $this->assertEquals("Register for exam!", $recurrenceOverride->getDescription());

        $recurrenceOverride = end($recurrenceOverrides);

        $this->assertTrue($recurrenceOverride->getExcluded());
    }

    public function testParseEventWithVirtualLocations()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_virtual_locations.json")
        );

        $virtualLocations = $this->jsCalendar->getVirtualLocations();

        $virtualLocation = current($virtualLocations);
        
        $this->assertEquals("VirtualLocation", $virtualLocation->getType());
        $this->assertEquals("Video Call", $virtualLocation->getName());
        $this->assertEquals("Internal video call", $virtualLocation->getDescription());
        $this->assertEquals("www.some-video-call-service.com/r123", $virtualLocation->getUri());
        $this->assertEquals(
            array("video" => true, "audio" => true),
            $virtualLocation->getFeatures()
        );

        $virtualLocation = next($virtualLocations);
        
        $this->assertEquals("VirtualLocation", $virtualLocation->getType());
        $this->assertEquals("Feature Keynote", $virtualLocation->getName());
        $this->assertEquals("Keynote of our new Feature to be made public directly afterwards.", $virtualLocation->getDescription());
        $this->assertEquals("www.some-streaming-service.com/user#our-company", $virtualLocation->getUri());
        $this->assertEquals(
            array("video" => true, "chat" => true, "moderator" => true, "screen" => true),
            $virtualLocation->getFeatures()
        );
    }

    public function testParseEventWithParticipants()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_participants.json")
        );

        $participants = $this->jsCalendar->getParticipants();

        $participant = current($participants);

        $this->assertEquals("Participant", $participant->getType());
        $this->assertEquals("John Doe", $participant->getName());
        $this->assertEquals("john.doe@some.domain.com", $participant->getEmail());
        $this->assertEquals(array("imip" => "mailto:john.doe@some.domain.com"), $participant->getSendTo());
        $this->assertEquals("accepted", $participant->getParticipationStatus());
        $this->assertEquals(array(
            "attendee" => true,
            "organizer" => true,
            "owner" => true
        ), $participant->getRoles());
        $this->assertEquals("en", $participant->getLanguage());
        $this->assertTrue($participant->getScheduleForceSend());
        $this->assertEquals(1, $participant->getScheduleSequence());

        $participant = next($participants);

        $this->assertEquals("Participant", $participant->getType());
        $this->assertEquals("Jane Smith", $participant->getName());
        $this->assertEquals("Outside Contractor", $participant->getDescription());
        $this->assertEquals("jane.smith@another.domain.net", $participant->getEmail());
        $this->assertEquals(array(
            "imip" => "mailto:jane.smith@another.domain.net"
        ), $participant->getSendTo());
        $this->assertEquals("individual", $participant->getKind());
        $this->assertEquals("tentative", $participant->getParticipationStatus());
        $this->assertEquals(array(
            "attendee" => true,
            "contact" => true
        ), $participant->getRoles());
        $this->assertEquals("en", $participant->getLanguage());
        $this->assertEquals("Will not be attending in person", $participant->getParticipationComment());

        $participant = end($participants);

        $this->assertEquals("Participant", $participant->getType());
        $this->assertEquals("Max Mustermann", $participant->getName());
        $this->assertEquals("max.musterman@different.domain.de", $participant->getEmail());
        $this->assertEquals(array(
            "imip" => "mailto:max.musterman@different.domain.de"
        ), $participant->getSendTo());
        $this->assertEquals("declined", $participant->getParticipationStatus());
        $this->assertEquals(array(
            "attendee" => true,
            "optional" => true
        ), $participant->getRoles());
        $this->assertTrue($participant->getExpectReply());
        $this->assertEquals("mtf1xo-qgxmf5-eut5-jvcb", $participant->getLocationId());
        $this->assertEquals("none", $participant->getScheduleAgent());
        $this->assertEquals("2022-12-30T12:00:00Z", $participant->getScheduleUpdated());
    }
    
    public function testParseEventWithRelations()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_relations.json")
        );

        $this->assertEquals("1234-relation-parent-OpenXPort-TestFiles", $this->jsCalendar[0]->getUid());
        $this->assertEquals("Relation", current($this->jsCalendar[0]->getRelatedTo())->getType());
        $this->assertEquals(array("parent" => true), current($this->jsCalendar[0]->getRelatedTo())->getRelation());


        $this->assertEquals("1234-relation-child-OpenXPort-TestFiles", $this->jsCalendar[1]->getUid());
        $this->assertEquals("Relation", current($this->jsCalendar[0]->getRelatedTo())->getType());
        $this->assertEquals(array("parent" => true), current($this->jsCalendar[0]->getRelatedTo())->getRelation());
    }

    public function testParseEventWithCustomProperties()
    {
        $this->jsCalendar = CalendarEvent::fromJson(
            file_get_contents(__DIR__ . "/../resources/jscalendar_with_custom_properties.json")
        );

        // Check that properties are read correctly.
        $customProperties = $this->jsCalendar->getCustomProperties();
        
        $this->assertEquals("Bar", $customProperties["foo"]);
        $this->assertEquals("SomeObject", $customProperties["someObjects"]->{"abc-123"}->{"@type"});
        $this->assertEquals("1234-someObject-OpenXPort-TestFiles", $customProperties["someObjects"]->{"abc-123"}->{"uid"});

        $location = $this->jsCalendar->getLocations()["mtf1xo-qgxmf5-eut5-jvcb"];
        $this->assertEquals(50, $location->getCustomProperties()["capacity"]);

        $virtualLocation = $this->jsCalendar->getVirtualLocations()["mtf1xo-qgxmf5-eut5-bcvj"];
        $this->assertEquals("public", $virtualLocation->getCustomProperties()["access"]);

        $link = $this->jsCalendar->getLinks()["2j3j5d-6ygpgd-aljx-xup8"];
        $this->assertEquals("2023-01-01T00:00:00Z", $link->getCustomProperties()["until"]);
        
        $relation = $this->jsCalendar->getRelatedTo()["1234-someTask-OpenXPort-TestFiles"];
        $this->assertEquals(true, $relation->getCustomProperties()["requiredFinished"]);

        $alerts = $this->jsCalendar->getAlerts();
        $alert = $alerts["1"];
        $this->assertEquals(3, $alert->getCustomProperties()["reminders"]);
        $this->assertEquals("some info", $alert->getTrigger()->getCustomProperties()["//comment"]);

        $alert = $alerts["2"];
        $this->assertEquals("some other info", $alert->getTrigger()->getCustomProperties()["//comment"]);

        $alert = $alerts["3"];
        $this->assertEquals(90, $alert->getTrigger()->getCustomProperties()["percentComplete"]);
        $this->assertEquals("This is just a made up trigger", $alert->getTrigger()->getCustomProperties()["//comment"]);

        $recurrenceRule = $this->jsCalendar->getRecurrenceRules()[0];
        $this->assertEquals("1234-someTask-OpenXPort-TestFiles", $recurrenceRule->getCustomProperties()["whileNotFinished"]);

        $nDay = $recurrenceRule->getByDay()[0];
        $this->assertEquals("some info about the day", $nDay->getCustomProperties()["//comment"]);

        // Check that properties are added back into the json correctly.
        @$json = json_encode($this->jsCalendar);

        $this->assertStringContainsString('"foo":"Bar', $json);
        $this->assertStringContainsString('"someObjects":{"abc-123":{"@type":"SomeObject","uid":"1234-someObject-OpenXPort-TestFiles"}}', $json);
        $this->assertStringContainsString('"capacity":50', $json);
        $this->assertStringContainsString('"access":"public"', $json);
        $this->assertStringContainsString('"until":"2023-01-01T00:00:00Z"', $json);
        $this->assertStringContainsString('"requiredFinished":true', $json);
        $this->assertStringContainsString('"reminders":3', $json);
        $this->assertStringContainsString('"\/\/comment":"some info"', $json);
        $this->assertStringContainsString('"\/\/comment":"some other info"', $json);
        $this->assertStringContainsString('"percentComplete":90,"\/\/comment":"This is just a made up trigger"', $json);
        $this->assertStringContainsString('"whileNotFinished":"1234-someTask-OpenXPort-TestFiles"', $json);
        $this->assertStringContainsString('"\/\/comment":"some info about the day"', $json);
    }
}
