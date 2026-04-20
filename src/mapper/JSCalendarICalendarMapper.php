<?php

namespace OpenXPort\Mapper;

use Exception;
use Sabre\VObject;
use OpenXPort\Jmap\Calendar\CalendarEvent;
use OpenXPort\Jmap\Calendar\PatchObject;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\JSCalendarICalendarAdapterUtil;
use OpenXPort\Adapter\JSCalendarICalendarAdapter;

class JSCalendarICalendarMapper extends AbstractMapper
{
    /**
     * Map from JMAP CalendarEvent objects (RFC 8984)
     * to iCal data.
     * https://datatracker.ietf.org/doc/draft-ietf-calext-jscalendar-icalendar/
     *
     * @param array<string,CalendarEvent> $jmapData
     * @param JSCalendarICalendarAdapter     $adapter
     *
     * @return array<int,array<string,mixed>>
     */
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        $adapter->resetICalEvent();

        foreach ($jmapData as $creationId => $jsCalendarEvent) {
            $adapter->setCalendarId($jsCalendarEvent->getCalendarIds());
            $adapter->setMethod($jsCalendarEvent->getMethod());

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
                if ($recurrenceOverride->getExcluded()) {
                    $masterEvent = $this->mapExcludedToExDate($adapter, $masterEvent, $recurrenceId);
                    continue;
                }

                if ($this->isEmptyRecurrenceOverride($recurrenceOverride)) {
                    $masterEvent = $this->mapIncludedToRDate($adapter, $masterEvent, $recurrenceId);
                    continue;
                }

                $adapter->resetICalEvent();

                $this->mapAllJmapPropertiesToICal($recurrenceOverride, $adapter, $jsCalendarEvent);

                $adapter->setRecurrenceId(
                    $recurrenceId,
                    $jsCalendarEvent->getTimeZone(),
                    $jsCalendarEvent->getShowWithoutTime()
                );

                $masterEvent->add($adapter->getEventComponent());
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

        // To make sure, the recurrence override's DateTime values that can be in any timezone
        // don't get overriden to UTC, since the timeZone value of the override is null, replace
        // it with the master event's time zone.
        if (
            is_null($jsEvent->getTimeZone()) &&
            !is_null($masterEvent) &&
            !is_null($masterEvent->getTimeZone())
        ) {
            $jsEvent->setTimeZone($masterEvent->getTimeZone());
        }

        // Similarly, to make sure that the override's DateTime values have the same format as
        // the master events DateTime values, set the showWithoutTime property of the override
        // to the one of the master event.
        if (
            is_null($jsEvent->getShowWithoutTime()) &&
            !is_null($masterEvent) &&
            !is_null($masterEvent->getShowWithoutTime())
        ) {
            $jsEvent->setShowWithoutTime($masterEvent->getShowWithoutTime());
        }

        // Map any properites that can be set in events and their recurrence overrides.
        $adapter->setSummary($jsEvent->getTitle());
        $adapter->setDescription($jsEvent->getDescription());
        $adapter->setCreated($jsEvent->getCreated());
        $adapter->setUpdated($jsEvent->getUpdated());

        $adapter->setDTStart($jsEvent->getStart(), $jsEvent->getTimeZone(), $jsEvent->getShowWithoutTime());
        $adapter->setDTEnd(
            $jsEvent->getStart(),
            $jsEvent->getDuration(),
            $jsEvent->getTimeZone(),
            $jsEvent->getShowWithoutTime()
        );

        $adapter->setCategories($jsEvent->getKeywords());
        $adapter->setLocation($jsEvent->getLocations());

        $adapter->setFreeBusy($jsEvent->getFreeBusyStatus());
        $adapter->setStatus($jsEvent->getStatus());
        $adapter->setColor($jsEvent->getColor());
        $adapter->setPriority($jsEvent->getPriority());

        $adapter->setAlerts($jsEvent->getAlerts());
        $adapter->setVLocations($jsEvent->getVLocations());
        $adapter->setVirtualLocations($jsEvent->getVirtualLocations());

        $adapter->setParticipants($jsEvent->getParticipants());
        $adapter->setShowWithoutTime($jsEvent->getShowWithoutTime());

        $adapter->setDuration($jsEvent->getDuration());
        $adapter->setGeo($jsEvent->getCoordinates());
        $adapter->setRelatedTo($jsEvent->getRelatedTo());

        // Map any property which is stored as a link object in jsCal. Currently only attachment is supported.
        $splitLinkMap = JSCalendarICalendarAdapterUtil::splitJmapLinkMapIntoICalProperties(
            $jsEvent->getLinks()
        );

        $adapter->setAttachments(
            is_array($splitLinkMap) && array_key_exists("attachments", $splitLinkMap)
                ? $splitLinkMap["attachments"]
                : null
        );

        if (!is_null($splitLinkMap) && array_key_exists("urls", $splitLinkMap)) {
            $urls = $splitLinkMap["urls"];
            if (!empty($urls)) {
                $adapter->setUrl($urls[0]->getHref());
            }
        }
        $url = $jsEvent->getUrl();
        if (AdapterUtil::isSetNotNullAndNotEmpty($url)) {
            $adapter->setUrl($url);
        }
        $adapter->setReplyTo($jsEvent->getReplyTo());

        $adapter->setRequestStatus($jsEvent->getRequestStatus());
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

    protected function mapExcludedToExDate($adapter, $masterEvent, $recurrenceId)
    {
        $adapter->setICalEvent($masterEvent->serialize());

        $adapter->setExDate($recurrenceId);

        $masterEvent = clone($adapter->getICalEvent());

        return $masterEvent;
    }
    /**
     * Add an RDATE property to the master event for an included recurrence instance.
     *
     * @param JSCalendarICalendarAdapter $adapter
     * @param VCalendar $masterEvent
     * @param string $recurrenceId
     * @return VCalendar
     */
    protected function mapIncludedToRDate($adapter, $masterEvent, $recurrenceId)
    {
        $adapter->setICalEvent($masterEvent->serialize());

        $adapter->setRDate($recurrenceId);

        $masterEvent = clone($adapter->getICalEvent());

        return $masterEvent;
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

            $methodProperty = $masterEvent["masterEvents"]["iCalendar"]->METHOD;
            if (AdapterUtil::isSetNotNullAndNotEmpty($methodProperty)) {
                $jsEvent->setMethod(strtolower($methodProperty->getValue()));
            }

            $this->mapAllICalPropertiesToJmap($jsEvent, $adapter);

            if (
                array_key_exists("oxpProperties", $masterEvent["masterEvents"]) &&
                is_array($masterEvent["masterEvents"]["oxpProperties"]) &&
                array_key_exists("calendarId", $masterEvent["masterEvents"]["oxpProperties"])
            ) {
                $jsEvent->setCalendarIds($masterEvent["masterEvents"]["oxpProperties"]["calendarId"]);
            }
            $jsEvent->setId($masterEvent["eventId"]);

            // Each modified VEVENT in a recurrence can be connected to its "master event" by
            // their UID as they are the same.
            $masterEventUid = $masterEvent["masterEvents"]["iCalendar"]->VEVENT->UID->getValue();

            // Set to empty array if no EXDATE property exists in mapAllICalPropertiesToJmap
            $recurrenceOverrides = $jsEvent->getRecurrenceOverrides();

            foreach ($modifiedExceptions as $modEx) {
                $modifiedExceptionUid = $modEx["modifiedExceptions"]->VEVENT->UID->getValue();

                if (strcmp($modifiedExceptionUid, $masterEventUid) === 0) {
                    $adapter->setICalEvent($modEx["modifiedExceptions"]->serialize());

                    $jmapModifiedException = new PatchObject();

                    // Modiified exceptions are are event that exclude the '@type',
                    // 'excludeRecurrenceRules', 'method', 'privacy', 'prodId', 'recurrenceId',
                    // 'recurrenceOverrides', 'recurrenceRules', 'relatedTo', 'replyTo' and 'uid'
                    // JMAP properties. They are than added into the recurrenceOverride property.
                    $this->mapAllICalPropertiesToJmap($jmapModifiedException, $adapter);

                    // If the iCal values for DTSTART in both the master event and the modified
                    // exception are in a specific time zone (e.g. "DTSTART;TZID=Europe/Berlin"),
                    // the time zone for both the event and the override are set in JSCalendar.
                    // Since the override having the same time zone as the master event is not
                    // a change for that occurence, we remove the information here.
                    if ($jsEvent->getTimeZone() === $jmapModifiedException->getTimeZone()) {
                        $jmapModifiedException->setTimeZone(null);
                    }


                    //Add the new modified occurrence to the ones already set in the JSCal event.
                    $recurrenceIdValueDate = $modEx["modifiedExceptions"]->VEVENT->{'RECURRENCE-ID'}->getDateTime();

                    // If showWithoutTime matches the master event, remove it from the override
                    if (
                        $jsEvent->getShowWithoutTime() === $jmapModifiedException->getShowWithoutTime() ||
                        (empty($jsEvent->getShowWithoutTime()) && empty($jmapModifiedException->getShowWithoutTime()))
                    ) {
                        $jmapModifiedException->setShowWithoutTime(null);
                    }
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
        $jmapEvent->setTimeZone($adapter->getTimeZone());
        $jmapEvent->setShowWithoutTime($adapter->getShowWithoutTime());

        $jmapEvent->setKeywords($adapter->getCategories());
        $jmapEvent->setLocations($adapter->getLocation());
        $jmapEvent->setVLocations($adapter->getVLocations());
        $jmapEvent->setCoordinates($adapter->getGeo());
        $jmapEvent->setVirtualLocations($adapter->getVirtualLocations());

        $jmapEvent->setFreeBusyStatus($adapter->getFreeBusy());

        $jmapEvent->setStatus($adapter->getStatus());
        $jmapEvent->setColor($adapter->getColor());
        $jmapEvent->setPriority($adapter->getPriority());

        $jmapEvent->setAlerts($adapter->getAlerts());
        $jmapEvent->setParticipants($adapter->getParticipants());

        // There are multiple iCal properties which are mapped to
        // jsCalendar link properties. Any new properties for which
        // this is the case should be stored in the $jmapLinks array
        // before it is then stored in the jsCal CalendarEvent object.
        $jmapLinks = array();

        // Set attachments. Set it as a variable to make null-handling easier
        // since the null coalescing operator (??) was only added in PHP 7.
        $attachments = $adapter->getAttachments();
        $jmapLinks = array_merge($jmapLinks, $attachments ? $attachments : []);

        $url = $adapter->getUrl();
        if (!is_null($url)) {
            $urlLink = new \OpenXPort\Jmap\Calendar\Link();
            $urlLink->setType("Link");
            $urlLink->setHref($url);
            array_push($jmapLinks, $urlLink);

            $jmapEvent->setUrl($url);
        }
        $jmapEvent->setReplyTo($adapter->getReplyTo());
        $jmapEvent->setRequestStatus($adapter->getRequestStatus());

        // Create indices for the JMAP link map. Otherwise, the objects would be
        // stored in an array.
        $jmapLinkIndices = array_map(
            function ($i) {
                return strval($i + 1);
            },
            array_keys($jmapLinks)
        );

        $jmapLinks = array_combine($jmapLinkIndices, $jmapLinks);

        // Attachments are mapped for both master events and recurrence overrides via the
        // generic links <-> ATTACH conversion path.
        $jmapEvent->setLinks($jmapLinks);

        // Map the properties that are strictly set in master event.
        if ($jmapEvent instanceof CalendarEvent) {
            $jmapEvent->setUid($adapter->getUid());
            $jmapEvent->setProdId($adapter->getProdId());

            $jmapEvent->setPrivacy($adapter->getClass());

            $jmapEvent->setRecurrenceRules($adapter->getRRule());
            $jmapEvent->setRelatedTo($adapter->getRelatedTo());

            $recurrenceOverrides = [];

            if (!is_null($adapter->getExDates())) {
                foreach ($adapter->getExDates() as $exDate) {
                    $excludedOverride = new PatchObject();
                    $excludedOverride->setExcluded(true);

                    $recurrenceOverrides[$exDate] = $excludedOverride;
                }
            }

            if (!is_null($adapter->getRDates())) {
                foreach ($adapter->getRDates() as $rDate) {
                    if (!array_key_exists($rDate, $recurrenceOverrides)) {
                        $recurrenceOverrides[$rDate] = new PatchObject();
                    }
                }
            }

            if (!empty($recurrenceOverrides)) {
                $jmapEvent->setRecurrenceOverrides($recurrenceOverrides);
            }
        }
    }
    /**
     * Check if a recurrence override is empty.
     *
     * @param PatchObject|array|null $recurrenceOverride
     * @return bool True if empty
     */
    protected function isEmptyRecurrenceOverride($recurrenceOverride)
    {
        if (is_null($recurrenceOverride)) {
            return true;
        }

        if ($recurrenceOverride instanceof PatchObject) {
            return $recurrenceOverride->isEmpty();
        }

        return empty((array) $recurrenceOverride);
    }
}
