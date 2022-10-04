<?php

namespace OpenXPort\Mapper;

use Sabre\VObject;
use OpenXPort\Jmap\Calendar\CalendarEvent;

class JSCalendarICalendarMapper extends AbstractMapper
{
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsCalendarEvent) {
            $adapter->setSummary($jsCalendarEvent->title);
            $adapter->setDescription($jsCalendarEvent->description);
            $adapter->setCreated($jsCalendarEvent->created);
            $adapter->setUpdated($jsCalendarEvent->updated);

            $adapter->setUid($jsCalendarEvent->uid);

            $adapter->setDTStart($jsCalendarEvent->start, $jsCalendarEvent->timeZone);
            $adapter->setDTEnd($jsCalendarEvent->start, $jsCalendarEvent->duration, $jsCalendarEvent->timeZone);

            $adapter->setSequence($jsCalendarEvent->sequence);
            $adapter->setStatus($jsCalendarEvent->status);
            $adapter->setFreeBusy($jsCalendarEvent->freeBusyStatus);
            $adapter->setClass($jsCalendarEvent->privacy);

            array_push($map, array($creationId => $adapter->getICalEvent()));

            // Reset the current iCalEvent to allow for multiple events in one calendar
            $adapter->resetICalEvent();
        }
        return $map;
    }

    public function mapToJmap($data, $adapter)
    {
        $list = [];

        foreach ($data as $calendarFolderId => $iCalEvents) {
            $adapter->setICalEvent($iCalEvents);

            $jsEvent = new CalendarEvent();
            $jsEvent->setType("Event");
            $jsEvent->setTitle($adapter->getSummary());
            $jsEvent->setDescription($adapter->getDescription());
            $jsEvent->setCreated($adapter->getCreated());
            $jsEvent->setUpdated($adapter->getUpdated());

            $jsEvent->setUid($adapter->getUid());
            $jsEvent->setProdId($adapter->getProdId());

            $jsEvent->setStart($adapter->getDTStart());
            $jsEvent->setDuration($adapter->getDuration());
            $jsEvent->setTimezone($adapter->getTimezone());

            $jsEvent->setSequence($adapter->getSequence());
            $jsEvent->setStatus($adapter->getStatus());
            $jsEvent->setKeywords($adapter->getCategories());
            $jsEvent->setLocations($adapter->getLocation());
            $jsEvent->setFreeBusyStatus($adapter->getFreeBusy());
            $jsEvent->setPrivacy($adapter->getClass());

            array_push($list, $jsEvent);
        }
        return $list;
    }
}
