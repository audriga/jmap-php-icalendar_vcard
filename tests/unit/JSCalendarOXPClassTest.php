<?php

use OpenXPort\Jmap\Calendar\CalendarEvent;
use OpenXPort\Jmap\Calendar\Location;
use OpenXPort\Jmap\Calendar\VirtualLocation;
use OpenXPort\Jmap\Calendar\PatchObject;
use PHPUnit\Framework\TestCase;

/**
 * Check implementation of updated OXP Core classes/methods.
 */
final class JSCalendarOXPClassTest extends TestCase
{
    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testChangesInCalendarEvent(): void
    {
        $calendarEvent = new CalendarEvent();

        $recurrenceRule = new \OpenXport\Jmap\Calendar\RecurrenceRule();
        $recurrenceRule->setType("RecurrenceRule");

        $calendarEvent->setRecurrenceRules(array($recurrenceRule));

        $this->assertNotNull($calendarEvent->getRecurrenceRules());

        $this->assertEquals($calendarEvent->getRecurrenceRules()[0]->getType(), "RecurrenceRule");
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\Location.php
     */
    public function testChangesInLocation(): void
    {
        $location = new Location();

        $linkIds = array(12, 34);
        $links = array(56, 78);

        $location->setLinkIds($linkIds);
        $location->setLinks($links);

        $this->assertNotEquals($location->getLinkIds(), $location->getLinks());
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventJMAPProperties(): void
    {
        $event = new CalendarEvent();

        $event->setBaseEventId("base-event-123");
        $this->assertEquals("base-event-123", $event->getBaseEventId());

        $calendarIds = array("cal1" => true, "cal2" => true);
        $event->setCalendarIds($calendarIds);
        $this->assertEquals($calendarIds, $event->getCalendarIds());

        $event->setIsDraft(true);
        $this->assertTrue($event->getIsDraft());

        $event->setMethod("request");
        $this->assertEquals("request", $event->getMethod());

        $event->setUrl("https://example.com/events/123");
        $this->assertEquals("https://example.com/events/123", $event->getUrl());
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventLocations(): void
    {
        $event = new CalendarEvent();

        $event->setCoordinates("geo:40.7128,-74.0060");
        $this->assertEquals("geo:40.7128,-74.0060", $event->getCoordinates());

        $location1 = new Location();
        $location1->setName("Conference Room A");
        $location1->setCoordinates("geo:37.386,-122.082");

        $vLocations = array("1" => $location1);
        $event->setVLocations($vLocations);

        $this->assertNotNull($event->getVLocations());
        $this->assertEquals("Conference Room A", $event->getVLocations()["1"]->getName());
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventVirtualLocations(): void
    {
        $event = new CalendarEvent();

        $virtualLocation = new VirtualLocation();
        $virtualLocation->setType("VirtualLocation");
        $virtualLocation->setName("Zoom Meeting");
        $virtualLocation->setUri("https://zoom.us/j/123456789");

        $event->setVirtualLocations(array("1" => $virtualLocation));

        $this->assertNotNull($event->getVirtualLocations());
        $this->assertEquals("Zoom Meeting", $event->getVirtualLocations()["1"]->getName());
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventRecurrence(): void
    {
        $event = new CalendarEvent();

        $event->setRecurrenceId("2025-03-05T09:00:00");
        $this->assertEquals("2025-03-05T09:00:00", $event->getRecurrenceId());

        $excludedOverride = new PatchObject();
        $excludedOverride->setExcluded(true);

        $modifiedOverride = new PatchObject();
        $modifiedOverride->setTitle("Modified Title");

        $overrides = array(
            "2025-03-05T09:00:00" => $excludedOverride,
            "2025-03-06T09:00:00" => $modifiedOverride,
        );

        $event->setRecurrenceOverrides($overrides);

        $this->assertNotNull($event->getRecurrenceOverrides());
        $this->assertTrue($event->getRecurrenceOverrides()["2025-03-05T09:00:00"]->getExcluded());
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventScheduling(): void
    {
        $event = new CalendarEvent();

        $event->setSentBy("mailto:secretary@example.com");
        $this->assertEquals("mailto:secretary@example.com", $event->getSentBy());

        $event->setMayInviteSelf(true);
        $this->assertTrue($event->getMayInviteSelf());

        $event->setMayInviteOthers(false);
        $this->assertFalse($event->getMayInviteOthers());

        $statuses = array("2.0;Success", "2.1;Sent with success");
        $event->setRequestStatus($statuses);

        $this->assertNotNull($event->getRequestStatus());
        $this->assertEquals("2.0;Success", $event->getRequestStatus()[0]);
    }

    /**
     * Check changes done to OpenXPort\Jmap\Calendar\CalendarEvent.php
     */
    public function testCalendarEventJsonSerialization(): void
    {
        $event = new CalendarEvent();
        $event->setId("event-123");
        $event->setBaseEventId("base-456");
        $event->setCalendarIds(array("cal1" => true));
        $event->setMethod("request");
        $event->setUrl("https://example.com");

        $json = json_encode($event);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey("id", $decoded);
        $this->assertArrayHasKey("baseEventId", $decoded);
        $this->assertArrayHasKey("method", $decoded);
        $this->assertEquals("event-123", $decoded["id"]);
    }
}
