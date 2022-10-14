<?php

namespace OpenXPort\Mapper;

use Sabre\VObject;
use OpenXPort\Jmap\Calendar\CalendarEvent;
use OpenXPort\Util\AdapterUtil;

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
            $adapter->setProdId($jsCalendarEvent->prodid);
            $adapter->setSequence($jsCalendarEvent->sequence);

            $adapter->setDTStart($jsCalendarEvent->start, $jsCalendarEvent->timeZone);
            $adapter->setDTEnd($jsCalendarEvent->start, $jsCalendarEvent->duration, $jsCalendarEvent->timeZone);

            $adapter->setCategories($jsCalendarEvent->keywords);
            $adapter->setLocation($jsCalendarEvent->locations);

            $adapter->setFreeBusy($jsCalendarEvent->freeBusyStatus);
            $adapter->setClass($jsCalendarEvent->privacy);
            $adapter->setStatus($jsCalendarEvent->status);

            $adapter->setRRule($jsCalendarEvent->recurrenceRules);

            array_push($map, array($creationId => $adapter->getICalEvent()));

            // Reset the current iCalEvent to allow for multiple events in one calendar
            $adapter->resetICalEvent();
        }
        return $map;
    }

    public function mapToJmap($data, $adapter)
    {
        $list = [];

        $masterEvents = [];
        $modifiedExceptions = [];

        foreach ($data as $calendarFolderId => $iCalEvents) {
            $iCalObject = VObject\Reader::read($iCalEvents);
            
            foreach ($iCalObject->VEVENT as $vevent) {
                // Save each vevent as its own iCal object with only it in the VEVENT property.
                $iCalEventObject = $iCalObject;
                $iCalEventObject->VEVENT = $vevent;

                if (AdapterUtil::isSetNotNullAndNotEmpty($vevent->{'RECURRENCE-ID'})) {
                    array_push($modifiedExceptions, array("folderId" => $calendarFolderId, "modifiedExceptions" => $iCalEventObject));
                } else {
                    array_push($masterEvents, array("folderId" => $calendarFolderId, "masterEvents" => $iCalEventObject));
                }
            }
        }

        foreach ($masterEvents as $masterEvent) {
            $adapter->setICalEvent($masterEvent["masterEvents"]->serialize());

            $jsEvent = new CalendarEvent();
            $jsEvent->setType("Event");

            $jsEvent->setTitle($adapter->getSummary());
            $jsEvent->setDescription($adapter->getDescription());
            $jsEvent->setCreated($adapter->getCreated());
            $jsEvent->setUpdated($adapter->getUpdated());

            $jsEvent->setUid($adapter->getUid());
            $jsEvent->setProdId($adapter->getProdId());
            $jsEvent->setSequence($adapter->getSequence());

            $jsEvent->setStart($adapter->getDTStart());
            $jsEvent->setDuration($adapter->getDuration());
            $jsEvent->setTimezone($adapter->getTimezone());

            $jsEvent->setKeywords($adapter->getCategories());
            $jsEvent->setLocations($adapter->getLocation());

            $jsEvent->setFreeBusyStatus($adapter->getFreeBusy());
            $jsEvent->setPrivacy($adapter->getClass());
            $jsEvent->setStatus($adapter->getStatus());

            $jsEvent->setRecurrenceRule($adapter->getRRule());

            $masterEventUid = $masterEvent["masterEvents"]->VEVENT->UID->getValue();

            foreach ($modifiedExceptions as $modEx) {
                $modifiedExceptionUid = $modEx["modifiedExceptions"]->VEVENT->UID->getValue();

                if (strcmp($modifiedExceptionUid, $masterEventUid) === 0) {
                    $adapter->setICalEvent($modEx["modifiedExceptions"]->serialize());

                    $jmapModifiedException = new CalendarEvent();

                    $jmapModifiedException->setTitle($adapter->getSummary());
                    $jmapModifiedException->setDescription($adapter->getDescription());
                    $jmapModifiedException->setCreated($adapter->getCreated());
                    $jmapModifiedException->setUpdated($adapter->getUpdated());
                    
                    $jmapModifiedException->setSequence($adapter->getSequence());

                    $jmapModifiedException->setStart($adapter->getDTStart());
                    $jmapModifiedException->setDuration($adapter->getDuration());
                    $jmapModifiedException->setTimeZone($adapter->getTimezone());

                    $jmapModifiedException->setKeywords($adapter->getCategories());
                    $jmapModifiedException->setLocations($adapter->getLocation());

                    $jmapModifiedException->setFreeBusyStatus($adapter->getFreeBusy());
                    $jmapModifiedException->setPrivacy($adapter->getClass());
                    $jmapModifiedException->setStatus($adapter->getStatus());

                    $jmapModifiedException->setStatus($adapter->getStatus());
                    var_dump($jmapModifiedException);
                }
            }

            array_push($list, $jsEvent);
        }
        return $list;
    }
}
