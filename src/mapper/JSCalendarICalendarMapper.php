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

            // Use any recurrenceOverrides saved in the JSCal event to create new VEVENTs for each
            // one.
            $i = 1;

            foreach ($jsCalendarEvent->recurrenceOverrides as $recurrenceId => $recurrenceOverride) {
                $adapter->setRecurrenceId($recurrenceId);

                $adapter->setSummary($recurrenceOverride->title);
                $adapter->setDescription($recurrenceOverride->description);
                $adapter->setCreated($recurrenceOverride->created);
                $adapter->setUpdated($recurrenceOverride->updated);
                
                $adapter->setUid($jsCalendarEvent->uid);
                $adapter->setProdId($jsCalendarEvent->prodid);
                $adapter->setSequence($jsCalendarEvent->sequence);

                $adapter->setDTStart($recurrenceOverride->start, $recurrenceOverride->timeZone);
                $adapter->setDTEnd(
                    $recurrenceOverride->start, $recurrenceOverride->duration, $recurrenceOverride->timeZone
                );

                $adapter->setCategories($recurrenceOverride->keywords);
                $adapter->setLocation($recurrenceOverride->locations);
                
                $adapter->setFreeBusy($recurrenceOverride->freeBusyStatus);
                $adapter->setClass($jsCalendarEvent->privacy);
                $adapter->setStatus($recurrenceOverride->status);

                $recurrenceCreationId = $creationId . "." . $i;

                $i++;

                array_push($map, array($recurrenceCreationId => $adapter->getICalEvent()));

                $adapter->resetICalEvent();
            }
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
                // THis is done to preserve properties like 'PRODID' as these are not specified in
                // 'VEVENT' property.
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

            // Each modified VEVENT in a recurrence can be connected to its "master event" by
            // their UID as they are the same.
            foreach ($modifiedExceptions as $modEx) {
                $modifiedExceptionUid = $modEx["modifiedExceptions"]->VEVENT->UID->getValue();

                if (strcmp($modifiedExceptionUid, $masterEventUid) === 0) {
                    $adapter->setICalEvent($modEx["modifiedExceptions"]->serialize());

                    $jmapModifiedException = new CalendarEvent();

                    // Also convert the properties of this event, leaving out the '@type',
                    // 'excludeRecurrenceRules', 'method', 'privacy', 'prodId', 'recurrenceId',
                    // 'recurrenceOverrides', 'recurrenceRules', 'relatedTo', 'replyTo' and 'uid'
                    // JMAP properties.
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

                    // The modified occurence of the event is saved on the 'recurrenceOverrides' property
                    // of a JSCal event.
                    $currentRecurrenceOverrides = $jsEvent->getRecurrenceOverrides();
                    if (!AdapterUtil::isSetNotNullAndNotEmpty($currentRecurrenceOverrides)) {
                        $currentRecurrenceOverrides = [];
                    }

                    //Add the new modified occurrence to the ones already set in the JSCal event.
                    $recurrenceIdValueDate = $modEx["modifiedExceptions"]->VEVENT->{'RECURRENCE-ID'}->getDateTime();

                    $recurrenceIdOfModifiedException = date_format($recurrenceIdValueDate, "Y-m-d\TH:i:s");

                    $currentRecurrenceOverrides[$recurrenceIdOfModifiedException] = $jmapModifiedException;

                    $jsEvent->setRecurrenceOverrides($currentRecurrenceOverrides);
                }
            }

            array_push($list, $jsEvent);
        }

        return $list;
    }
}
