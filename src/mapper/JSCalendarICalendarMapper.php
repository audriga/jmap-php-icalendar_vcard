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
            // Map any properties of the event using the helper fucntion.
            $this->mapPropertiesFromJmap($jsCalendarEvent, $adapter);

            // Extracts the master event and makes sure it does not get overwritten.
            $masterEvent = clone($adapter->getICalEvent());

            // Reset the current iCalEvent to allow for multiple events in one calendar
            $adapter->resetICalEvent();

            // If the event has no overrides, simply skip the next steps and just add the event
            // to the array that is returned.
            if (!AdapterUtil::isSetNotNullAndNotEmpty($jsCalendarEvent->recurrenceOverrides)) {
                array_push($map, array($creationId => $masterEvent->serialize()));
                continue;
            }

            // Use any recurrenceOverrides saved in the JSCal event to create new VEVENTs for each
            // one.
            foreach ($jsCalendarEvent->recurrenceOverrides as $recurrenceId => $recurrenceOverride) {
                $adapter->setRecurrenceId($recurrenceId);

                // Map the properties of the recurrenceOverride to its corresponding VEVENT.
                $this->mapPropertiesFromJmap($recurrenceOverride, $adapter, $jsCalendarEvent);

                // The following will extract the VEVENT components of the modified exception currently
                // set in the adapter as an associative array (property => value). The array will then
                // be added to the master event as a new VEVENT component using the VObject libary
                $modifiedExceptionEvent = $adapter->getVeventComponents();

                $masterEvent->add("VEVENT", $modifiedExceptionEvent);

                $adapter->resetICalEvent();
            }

            array_push($map, array($creationId => $masterEvent->serialize()));
        }

        return $map;
    }

    private function mapPropertiesFromJmap($jsEvent, $adapter, $masterEvent = null)
    {
        if (is_null($jsEvent) || is_null($adapter)) {
            // TODO: consider logging an error.
            return;
        }
        // Map any properites that can be set in events and their recurrence overrides.
        $adapter->setSummary($jsEvent->title);
        $adapter->setDescription($jsEvent->description);
        $adapter->setCreated($jsEvent->created);
        $adapter->setUpdated($jsEvent->updated);

        $adapter->setDTStart($jsEvent->start, $jsEvent->timeZone);
        $adapter->setDTEnd($jsEvent->start, $jsEvent->duration, $jsEvent->timeZone);

        $adapter->setCategories($jsEvent->keywords);
        $adapter->setLocation($jsEvent->locations);

        $adapter->setFreeBusy($jsEvent->freeBusyStatus);
        $adapter->setStatus($jsEvent->status);

        // Map any properties that are only found in the event itself.
        if (is_null($masterEvent)) {
            $adapter->setUid($jsEvent->uid);
            $adapter->setProdId($jsEvent->prodid);

            $adapter->setSequence($jsEvent->sequence);
            $adapter->setClass($jsEvent->privacy);

            $adapter->setRRule($jsEvent->recurrenceRules);
        } else {
            $adapter->setUid($masterEvent->uid);

            $adapter->setSequence($masterEvent->sequence);
            $adapter->setClass($masterEvent->privacy);
        }
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
                // This is done to preserve properties like 'PRODID' for multiple master events
                // as these are not specified within the 'VEVENT' property.
                $iCalEventObject = clone($iCalObject);
                $iCalEventObject->VEVENT = $vevent;

                // Changed occurrences can be distingusihed by having a 'RECURRENCE-ID' property.
                if (AdapterUtil::isSetNotNullAndNotEmpty($vevent->{'RECURRENCE-ID'})) {
                    array_push(
                        $modifiedExceptions,
                        array("folderId" => $calendarFolderId, "modifiedExceptions" => $iCalEventObject)
                    );
                } else {
                    array_push(
                        $masterEvents,
                        array("folderId" => $calendarFolderId, "masterEvents" => $iCalEventObject)
                    );
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

            // Each modified VEVENT in a recurrence can be connected to its "master event" by
            // their UID as they are the same.
            $masterEventUid = $masterEvent["masterEvents"]->VEVENT->UID->getValue();

            $recurrenceOverrides = [];

            foreach ($modifiedExceptions as $modEx) {
                $modifiedExceptionUid = $modEx["modifiedExceptions"]->VEVENT->UID->getValue();

                if (strcmp($modifiedExceptionUid, $masterEventUid) === 0) {
                    $adapter->setICalEvent($modEx["modifiedExceptions"]->serialize());

                    $jmapModifiedException = new CalendarEvent();

                    // Modiified exceptions are are event that exclude the '@type',
                    // 'excludeRecurrenceRules', 'method', 'privacy', 'prodId', 'recurrenceId',
                    // 'recurrenceOverrides', 'recurrenceRules', 'relatedTo', 'replyTo' and 'uid'
                    // JMAP properties. They are than added into the recurrenceoverride property.
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

                    //Add the new modified occurrence to the ones already set in the JSCal event.
                    $recurrenceIdValueDate = $modEx["modifiedExceptions"]->VEVENT->{'RECURRENCE-ID'}->getDateTime();

                    $recurrenceIdOfModifiedException = date_format($recurrenceIdValueDate, "Y-m-d\TH:i:s");

                    $recurrenceOverrides[$recurrenceIdOfModifiedException] = $jmapModifiedException;
                }
            }

            $jsEvent->setRecurrenceOverrides($recurrenceOverrides);

            array_push($list, $jsEvent);
        }

        return $list;
    }
}
