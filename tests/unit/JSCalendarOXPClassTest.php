<?php
use OpenXPort\Jmap\Calendar\CalendarEvent;
use OpenXPort\Jmap\Calendar\Location;
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

        $calendarEvent->setRecurrenceRule(array($recurrenceRule));
        $calendarEvent->setRecurrenceRules(array($recurrenceRule));

        $this->assertNotNull($calendarEvent->getRecurrenceRule());
        $this->assertNotNull($calendarEvent->getRecurrenceRules());

        $this->assertEquals($calendarEvent->getRecurrenceRule(), $calendarEvent->getRecurrenceRules());
        $this->assertEquals($calendarEvent->getRecurrenceRule()[0]->getType(), "RecurrenceRule");
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
}