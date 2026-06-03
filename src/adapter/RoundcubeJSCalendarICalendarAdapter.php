<?php

namespace OpenXPort\Adapter;

/**
 * Roundcube-specific adapter for JSCalendar <-> iCalendar conversion.
 *
 * Extends JSCalendarICalendarAdapter to handle Roundcube's native
 * event array format from the calendar plugin database driver.
 */
class RoundcubeJSCalendarICalendarAdapter extends JSCalendarICalendarAdapter
{
    /** @var array Valarms keyed by event UID, persists across resetICalEvent calls */
    private $storedValarmsMap = [];

    /**
     * Override setCalendarId to store as JMAP calendarIds map {"id": true}.
     * The parent mapper passes oxpProperties["calendarId"] directly to
     * $jsEvent->setCalendarIds(), so it must already be in map format.
     */
    public function setCalendarId($calendarId)
    {
        $map = new \stdClass();
        $map->$calendarId = true;
        $oxp = $this->getOXPProperties();
        $oxp["calendarId"] = $map;
        $this->setOXPProperties($oxp);
    }

    /**
     * Get Roundcube event as hash (iCalendar string + oxpProperties).
     * Overrides parent to also include calendarId from Roundcube event.
     */
    public function getAsHash()
    {
        return array(
            "iCalendar" => $this->getICalEvent()->serialize(),
            "oxpProperties" => $this->getOXPProperties()
        );
    }

    /**
     * Override getAlerts to look up stored valarms by UID.
     */
    public function getAlerts()
    {
        $uid = null;
        try {
            $uid = $this->getICalEvent()->VEVENT->UID->getValue();
        } catch (\Exception $e) {
        }

        if ($uid && isset($this->storedValarmsMap[$uid])) {
            $valarms = $this->storedValarmsMap[$uid];
            $alerts = [];
            foreach ($valarms as $i => $valarm) {
                $trigger = $valarm['trigger'] ?? null;
                $action = $valarm['action'] ?? 'display';
                if (!$trigger) {
                    continue;
                }
                $alerts['a' . ($i + 1)] = [
                    '@type'   => 'Alert',
                    'action'  => $action,
                    'trigger' => [
                        '@type'      => 'OffsetTrigger',
                        'offset'     => $trigger,
                        'relativeTo' => 'start',
                    ],
                ];
            }
            return !empty($alerts) ? $alerts : null;
        }

        return parent::getAlerts();
    }

    /**
     * Set adapter from Roundcube event array.
     * Converts Roundcube's native format to iCalendar for processing.
     *
     * @param array $rcEvent Roundcube event array with keys: title, start, end, uid, etc.
     */
    public function setFromRcEvent(array $rcEvent)
    {
        // Store calendarId in oxpProperties as map
        if (isset($rcEvent['calendar'])) {
            $this->setCalendarId((string)$rcEvent['calendar']);
        }

        // Set UID
        if (!empty($rcEvent['uid'])) {
            $this->setUid($rcEvent['uid']);
        }

        // Set title as SUMMARY
        if (!empty($rcEvent['title'])) {
            $this->setSummary($rcEvent['title']);
        }

        // Set description
        if (!empty($rcEvent['description'])) {
            $this->setDescription($rcEvent['description']);
        }

        // Set start
        if (!empty($rcEvent['start'])) {
            $start = $rcEvent['start'] instanceof \DateTime
                ? $rcEvent['start']->format('Y-m-d\TH:i:s')
                : date('Y-m-d\TH:i:s', strtotime($rcEvent['start']));

            $timeZone = $rcEvent['start'] instanceof \DateTime
                ? $rcEvent['start']->getTimezone()->getName()
                : 'Etc/UTC';

            $this->setDTStart($start, $timeZone);
        }

        // Set end and compute duration
        if (!empty($rcEvent['end'])) {
            $start = $rcEvent['start'] instanceof \DateTime
                ? $rcEvent['start']->format('Y-m-d\TH:i:s')
                : date('Y-m-d\TH:i:s', strtotime($rcEvent['start']));

            $end = $rcEvent['end'] instanceof \DateTime
                ? $rcEvent['end']
                : new \DateTime($rcEvent['end']);

            $start_dt = $rcEvent['start'] instanceof \DateTime
                ? $rcEvent['start']
                : new \DateTime($rcEvent['start']);

            $interval = $start_dt->diff($end);
            $duration = $interval->format('P%dDT%hH%iM%sS');
            $duration = preg_replace('/T0H0M0S$/', '', $duration);
            $duration = preg_replace('/^P0D/', 'P', $duration);
            $duration = preg_replace('/(\d+H)0M0S/', '$1', $duration);
            $duration = preg_replace('/(\d+M)0S/', '$1', $duration);

            $timeZone = $rcEvent['start'] instanceof \DateTime
                ? $rcEvent['start']->getTimezone()->getName()
                : 'Etc/UTC';

            $this->setDTEnd($start, $duration, $timeZone);
        }

        // Set status
        if (!empty($rcEvent['status'])) {
            $this->setStatus($rcEvent['status']);
        }

        // Set location
        if (!empty($rcEvent['location'])) {
            $location = new \OpenXPort\Jmap\Calendar\Location();
            $location->setName($rcEvent['location']);
            $this->setLocation(['1' => $location]);
        }

        // Set priority
        if (!empty($rcEvent['priority'])) {
            $this->setPriority($rcEvent['priority']);
        }

        // Set categories/keywords
        if (!empty($rcEvent['categories'])) {
            $categoriesRaw = $rcEvent['categories'];
            if (is_array($categoriesRaw)) {
                $cats = array_fill_keys($categoriesRaw, true);
            } else {
                $cats = array_fill_keys(
                    array_map('trim', explode(',', $categoriesRaw)),
                    true
                );
            }
            $cats = array_filter($cats, function ($k) {
                return $k !== '';
            }, ARRAY_FILTER_USE_KEY);
            $this->setCategories($cats);
        }

        // Set alarms
        $valarms = null;
        if (!empty($rcEvent['valarms'])) {
            $valarms = $rcEvent['valarms'];
        } elseif (!empty($rcEvent['alarms']) && is_string($rcEvent['alarms'])) {
            $valarms = json_decode($rcEvent['alarms'], true);
        }
        if (!empty($valarms) && !empty($rcEvent['uid'])) {
            $this->storedValarmsMap[$rcEvent['uid']] = $valarms;
            $this->setAlerts($valarms);
        }
    }
}
