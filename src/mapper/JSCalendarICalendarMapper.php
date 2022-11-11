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
            $this->mapAllJmapPropertiesToICal($jsCalendarEvent, $adapter);

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
                $this->mapAllJmapPropertiesToICal($recurrenceOverride, $adapter, $jsCalendarEvent);

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

    private function mapAllJmapPropertiesToICal($jsEvent, $adapter, $masterEvent = null)
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

        $adapter->setAlerts($jsEvent->alerts);

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

            // Set the @type property here in order for the event to be recognised as a master event.
            $jsEvent->setType("Event");

            $this->mapAllICalPropertiesToJmap($jsEvent, $adapter);

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
                    // JMAP properties. They are than added into the recurrenceOverride property.
                    $this->mapAllICalPropertiesToJmap($jmapModifiedException, $adapter);

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

    private function mapAllICalPropertiesToJmap($jmapEvent, $adapter)
    {
        if (is_null($jmapEvent) || is_null($adapter)) {
            return;
        }

        // All properties that are set in both a master event and a
        // recurrence override are set.
        $jmapEvent->setTitle($adapter->getSummary());
        $jmapEvent->setDescription($adapter->getDescription());
        $jmapEvent->setCreated($adapter->getCreated());
        $jmapEvent->setUpdated($adapter->getUpdated());

        $jmapEvent->setSequence($adapter->getSequence());

        $jmapEvent->setStart($adapter->getDTStart());
        $jmapEvent->setDuration($adapter->getDuration());
        $jmapEvent->setTimezone($adapter->getTimezone());

        $jmapEvent->setKeywords($adapter->getCategories());
        $jmapEvent->setLocations($adapter->getLocation());

        $jmapEvent->setFreeBusyStatus($adapter->getFreeBusy());
        $jmapEvent->setStatus($adapter->getStatus());

        $jmapEvent->setAlerts($adapter->getAlerts());
        $jmapEvent->setParticipants($adapter->getParticipants());

        // Map the properties that are strictly set in master event.
        if (strcmp($jmapEvent->getType(), "Event") === 0) {
            $jmapEvent->setUid($adapter->getUid());
            $jmapEvent->setProdId($adapter->getProdId());

            $jmapEvent->setPrivacy($adapter->getClass());

            $jmapEvent->setRecurrenceRule($adapter->getRRule());
        }
    }
}
