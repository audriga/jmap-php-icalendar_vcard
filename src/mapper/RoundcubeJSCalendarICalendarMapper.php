<?php

namespace OpenXPort\Mapper;

/**
 * Roundcube-specific mapper for JSCalendar <-> iCalendar conversion.
 * Extends JSCalendarICalendarMapper to handle Roundcube's native event array format.
 */
class RoundcubeJSCalendarICalendarMapper extends JSCalendarICalendarMapper
{
    /**
     * Override mapFromJmap to convert iCalendar output to Roundcube event array.
     */
    public function mapFromJmap($jmapData, $adapter)
    {
        $iCalMap = parent::mapFromJmap($jmapData, $adapter);
        $map = [];

        foreach ($iCalMap as $entry) {
            $creationId = key($entry);
            $eventData = reset($entry);

            if (is_null($eventData)) {
                array_push($map, [$creationId => null]);
                continue;
            }

            $iCalString = $eventData['iCalendar'] ?? null;
            $calendarId = $eventData['oxpProperties']['calendarId'] ?? null;

            if (!$iCalString) {
                array_push($map, [$creationId => null]);
                continue;
            }

            $vObject = \Sabre\VObject\Reader::read($iCalString);
            $vevent = $vObject->VEVENT;

            // Roundcube's calendar plugin checks is_a($value, 'DateTime') before formatting date
            // columns, and DateTimeImmutable fails that check, so convert to DateTime here.
            $start = \DateTime::createFromImmutable($vevent->DTSTART->getDateTime());
            $end = isset($vevent->DTEND)
                ? \DateTime::createFromImmutable($vevent->DTEND->getDateTime())
                : (clone $start)->modify('+1 hour');

            // Extract location from original JSCalendar data
            $location = '';
            $jsEvent = $jmapData[$creationId] ?? null;
            if ($jsEvent && !empty($jsEvent->getLocations())) {
                $locations = $jsEvent->getLocations();
                $firstLoc = reset($locations);
                $location = (string)$firstLoc->getName();
            }
            if ($location === '' && isset($vevent->LOCATION) && (string)$vevent->LOCATION !== '') {
                $location = (string)$vevent->LOCATION;
            }

            // Extract categories as array
            $categories = [];
            if (isset($vevent->CATEGORIES)) {
                foreach ($vevent->CATEGORIES as $cat) {
                    foreach ($cat->getParts() as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $categories[] = $part;
                        }
                    }
                }
            }

            // Extract alarms as valarms array
            $valarms = [];
            if (isset($vevent->VALARM)) {
                foreach ($vevent->VALARM as $valarm) {
                    $action = isset($valarm->ACTION) ? strtolower((string)$valarm->ACTION) : 'display';
                    $trigger = isset($valarm->TRIGGER) ? (string)$valarm->TRIGGER : '';
                    if ($trigger !== '') {
                        $valarms[] = ['action' => $action, 'trigger' => $trigger];
                    }
                }
            }

            // Map iCalendar PRIORITY (1-9) to Roundcube priority (0=none, 1=high, 2=normal, 3=low)
            $iCalPriority = (int)($vevent->PRIORITY ?? 0);
            if ($iCalPriority === 0) {
                $rcPriority = 0;
            } elseif ($iCalPriority <= 4) {
                $rcPriority = 1;
            } elseif ($iCalPriority === 5) {
                $rcPriority = 2;
            } else {
                $rcPriority = 3;
            }

            $rcEvent = [
                'calendar'    => $calendarId,
                'uid'         => (string)$vevent->UID,
                'title'       => (string)($vevent->SUMMARY ?? ''),
                'description' => (string)($vevent->DESCRIPTION ?? ''),
                'location'    => $location,
                'categories'  => $categories,
                'valarms'     => $valarms,
                'start'       => $start,
                'end'         => $end,
                'allday'      => 0,
                'status'      => strtolower((string)($vevent->STATUS ?? '')),
                'priority'    => $rcPriority,
                'free_busy'   => 'busy',
            ];

            array_push($map, [$creationId => $rcEvent]);
        }

        return $map;
    }

    /**
     * Override mapToJmap to convert Roundcube event array to iCalendar using the adapter.
     */
    public function mapToJmap($data, $adapter)
    {
        $iCalData = [];

        foreach ($data as $eventId => $event) {
            $eventId = (string)$eventId;
            if (is_array($event)) {
                $adapter->resetICalEvent();
                $adapter->setFromRcEvent($event);
                $iCalData[$eventId] = $adapter->getAsHash();
            } else {
                $iCalData[$eventId] = $event;
            }
        }

        return parent::mapToJmap($iCalData, $adapter);
    }
}
