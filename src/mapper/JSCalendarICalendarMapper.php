<?php

namespace OpenXPort\Mapper;

use Sabre\VObject;
use OpenXPort\Jmap\Calendar\CalendarEvent;

class JSCalendarICalendarMapper extends AbstractMapper
{
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsCalendarEvent)
        {
            $adapter->setSummary($jsCalendarEvent->title);
            $adapter->setCreated($jsCalendarEvent->created);
            $adapter->setUpdated($jsCalendarEvent->updated);

            $adapter->setUid($jsCalendarEvent->uid);

            $adapter->setDTStart($jsCalendarEvent->start, $jsCalendarEvent->timeZone);
            $adapter->setDTEnd($jsCalendarEvent->start, $jsCalendarEvent->duration, $jsCalendarEvent->timeZone);
            

            array_push($map, array($creationId => $adapter->getICalEvent()));
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
            $jsEvent->setCreated($adapter->getCreated());
            $jsEvent->setUpdated($adapter->getUpdated());

            $jsEvent->setUid($adapter->getUid());
        

            // TODO: implement time zone
            $jsEvent->setStart($adapter->getDTStart());
            $jsEvent->setDuration($adapter->getDuration());
            //$jsEvent->setTimezone($adapter->getTimezone());

            

            array_push($list, $jsEvent);
        }
        return $list;
    }
}