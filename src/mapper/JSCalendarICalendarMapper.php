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

        $adapter->resetICalEvent();

        foreach ($jmapData as $creationId => $jsCalendarEvent) {
            $adapter->setCalendarId($jsCalendarEvent->getCalendarId());

            // Map any properties of the event using the helper fucntion.
            $this->mapAllJmapPropertiesToICal($jsCalendarEvent, $adapter);

            // If the event has no overrides, simply skip the next steps and just add the event
            // to the array that is returned.
            if (!AdapterUtil::isSetNotNullAndNotEmpty($jsCalendarEvent->getRecurrenceOverrides())) {
                array_push($map, array($creationId => $adapter->getAsHash()));

                // Reset the current iCalEvent to allow for multiple events in one calendar
                $adapter->resetICalEvent();
                continue;
            }

            // Extracts the master event and makes sure it does not get overwritten.
            $masterEvent = clone($adapter->getICalEvent());
            $oxpProperties = $adapter->getOXPProperties();

            // If the master event does not contain a uid, we need to make sure that the recurrence overrides we
            // generate get the same UID as the corresponding master event.
            if (is_null($jsCalendarEvent->getUid())) {
                $jsCalendarEvent->setUid($masterEvent->VEVENT->UID->getValue());
            }

            // Use any recurrenceOverrides saved in the JSCal event to create new VEVENTs for each
            // one.
            foreach ($jsCalendarEvent->getRecurrenceOverrides() as $recurrenceId => $recurrenceOverride) {
                $adapter->resetICalEvent();
                $adapter->setRecurrenceId($recurrenceId);

                // Map the properties of the recurrenceOverride to its corresponding VEVENT.
                $this->mapAllJmapPropertiesToICal($recurrenceOverride, $adapter, $jsCalendarEvent);


                // The following will extract the VEVENT components of the modified exception currently
                // set in the adapter as an associative array (property => value). The array will then
                // be added to the master event as a new VEVENT component using the VObject libary
                $modifiedExceptionEvent = $adapter->getVeventComponents();

                $masterEvent->add("VEVENT", $modifiedExceptionEvent);
            }

            $adapter->setICalEvent($masterEvent->serialize());
            $adapter->setOXPProperties($oxpProperties);
            array_push($map, array($creationId => $adapter->getAsHash()));

            $adapter->resetICalEvent();
        }

        return $map;
    }

    protected function mapAllJmapPropertiesToICal($jsEvent, $adapter, $masterEvent = null)
    {
        if (is_null($jsEvent) || is_null($adapter)) {
            // TODO: consider logging an error.
            return;
        }
        // Map any properites that can be set in events and their recurrence overrides.
        $adapter->setSummary($jsEvent->getTitle());
        $adapter->setDescription($jsEvent->getDescription());
        $adapter->setCreated($jsEvent->getCreated());
        $adapter->setUpdated($jsEvent->getUpdated());

        $adapter->setDTStart($jsEvent->getStart(), $jsEvent->getTimeZone());
        $adapter->setDTEnd($jsEvent->getStart(), $jsEvent->getDuration(), $jsEvent->getTimeZone());

        $adapter->setCategories($jsEvent->getKeywords());
        $adapter->setLocation($jsEvent->getLocations());

        $adapter->setFreeBusy($jsEvent->getFreeBusyStatus());
        $adapter->setStatus($jsEvent->getStatus());
        $adapter->setColor($jsEvent->getColor());
        $adapter->setPriority($jsEvent->getPriority());

        $adapter->setAlerts($jsEvent->getAlerts());

        $adapter->setParticipants($jsEvent->getParticipants());

        // Map any properties that are only found in the event itself.
        if (is_null($masterEvent)) {
            $adapter->setUid($jsEvent->getUid());
            $adapter->setProdId($jsEvent->getProdId());

            $adapter->setSequence($jsEvent->getSequence());
            $adapter->setClass($jsEvent->getPrivacy());

            $adapter->setRRule($jsEvent->getRecurrenceRules());
        } else {
            $adapter->setUid($masterEvent->getUid());

            $adapter->setSequence($masterEvent->getSequence());
            $adapter->setClass($masterEvent->getPrivacy());
        }
    }

    public function mapToJmap($data, $adapter)
    {
        $list = [];

        $masterEvents = [];
        $modifiedExceptions = [];

        foreach ($data as $eventId => $iCalEvents) {
            $iCalObject = VObject\Reader::read($iCalEvents["iCalendar"]);

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
                        array("eventId" => $eventId, "modifiedExceptions" => $iCalEventObject)
                    );
                } else {
                    array_push(
                        $masterEvents,
                        array("eventId" => $eventId, "masterEvents" => array(
                            "iCalendar" => $iCalEventObject,
                            "oxpProperties" => $iCalEvents["oxpProperties"]
                            )
                        )
                    );
                }
            }
        }

        foreach ($masterEvents as $masterEvent) {
            $adapter->setICalEvent($masterEvent["masterEvents"]["iCalendar"]->serialize());

            $jsEvent = new CalendarEvent();

            // Set the @type property here in order for the event to be recognised as a master event.
            $jsEvent->setType("Event");

            $this->mapAllICalPropertiesToJmap($jsEvent, $adapter);

            $jsEvent->setCalendarId($masterEvent["masterEvents"]["oxpProperties"]["calendarId"]);
            $jsEvent->setId($masterEvent["eventId"]);

            // Each modified VEVENT in a recurrence can be connected to its "master event" by
            // their UID as they are the same.
            $masterEventUid = $masterEvent["masterEvents"]["iCalendar"]->VEVENT->UID->getValue();

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
        $jmapEvent->setShowWithoutTime($adapter->getShowWithoutTime());
        $jmapEvent->setDuration($adapter->getDuration());
        $jmapEvent->setTimezone($adapter->getTimezone());

        $jmapEvent->setKeywords($adapter->getCategories());
        $jmapEvent->setLocations($adapter->getLocation());

        $jmapEvent->setFreeBusyStatus($adapter->getFreeBusy());
        $jmapEvent->setStatus($adapter->getStatus());
        $jmapEvent->setColor($adapter->getColor());
        $jmapEvent->setPriority($adapter->getPriority());

        $jmapEvent->setAlerts($adapter->getAlerts());
        $jmapEvent->setParticipants($adapter->getParticipants());

        // Map the properties that are strictly set in master event.
        if (strcmp($jmapEvent->getType(), "Event") === 0) {
            $jmapEvent->setUid($adapter->getUid());
            $jmapEvent->setProdId($adapter->getProdId());

            $jmapEvent->setPrivacy($adapter->getClass());

            $jmapEvent->setRecurrenceRules($adapter->getRRule());
        }
    }
}
