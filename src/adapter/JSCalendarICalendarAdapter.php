<?php

namespace OpenXPort\Adapter;

use DateTime;
use Sabre\VObject\Component\VCalendar;
use OpenXPort\Util\Logger;
use Sabre\VObject;
use OpenXPort\Jmap\Calendar\Location;
use OpenXPort\Jmap\Calendar\RecurrenceRule;
use OpenXPort\Mapper\JSCalendarICalendarMapper;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\JSCalendarICalendarAdapterUtil;

/**
 * Generic adapter to convert between ICalendar <-> JSCalendar.
 */

class JSCalendarICalendarAdapter extends AbstractAdapter
{
    // This is an iCal event component (and not an entire iCal object)
    private $iCalEvent;

    protected $logger;

    public function __construct()
    {
        $this->iCalEvent = new VCalendar(['VEVENT' => []]);
        $this->logger = Logger::getInstance();
    }

    public function getICalEvent()
    {
        return $this->iCalEvent->serialize();
    }

    public function setICalEvent($iCalEvent)
    {
        $this->iCalEvent = VObject\Reader::read($iCalEvent);
    }

    /**
     * This method resets the ICalendar event object in the adapter.
     * Doing so is helpful in avoiding overwriting empty fields of an event with properties of previous events.
     *
     * Note that Sabre-VObject creates default values for the UID and DTSTAMP properties of new VEVENTs.
     */
    public function resetICalEvent()
    {
        $this->iCalEvent = new VCalendar(['VEVENT' => []]);
    }

    public function getSummary()
    {
        return $this->iCalEvent->VEVENT->SUMMARY->getValue();
    }

    public function setSummary($summary)
    {
        $this->iCalEvent->VEVENT->add('SUMMARY', $summary);
    }

    public function getDescription()
    {
        $description = $this->iCalEvent->VEVENT->DESCRIPTION;


        if (is_null($description)) {
            return null;
        }

        return $description->getValue();

        // TODO: implement the unescaping mentioned in the ietf conversion standards.
        // https://www.ietf.org/archive/id/draft-ietf-calext-jscalendar-icalendar-07.html#name-description.
    }

    public function setDescription($description)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($description)) {
            return;
        }

        $this->iCalEvent->VEVENT->add('DESCRIPTION', $description);
    }

    public function getCreated()
    {
        $created = $this->iCalEvent->VEVENT->CREATED;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($created)) {
            return null;
        }

        $createdDateTime = $created->getDateTime();

        $jmapFormat = "Y-m-d\TH:i:s\Z";

        $jmapCreated = $createdDateTime->format($jmapFormat);

        return $jmapCreated;
    }

    public function setCreated($created)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($created)) {
            return;
        }

        $jmapFormat = "Y-m-d\TH:i:s\Z";
        $iCalFormat = "Ymd\THis\Z";

        $iCalendarCreated = AdapterUtil::parseDateTime($created, $jmapFormat, $iCalFormat);

        $this->iCalEvent->VEVENT->add('CREATED', new \DateTime($iCalendarCreated));
    }

    public function getDTStart()
    {
        $start = $this->iCalEvent->VEVENT->DTSTART;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($start)) {
            return null;
        }

        $dtStart = $start->getDateTime();

        // Always uses local dateTime in jmap.
        $jmapStart = $dtStart->format("Y-m-d\TH:i:s");
        return $jmapStart;
    }

    public function setDTStart($start, $timeZone)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($start)) {
            return;
        }

        // The following checks for the right DateTime Format and creates a new DateTime in the jmap format.
        $jmapFormat = "Y-m-d\TH:i:s\Z";
        $iCalFormat = "Ymd\THis\Z";

        $jmapStartDatetime = \DateTime::createFromFormat($jmapFormat, $start);

        // Check what pattern was used in the jmap "start" parameter.
        if ($jmapStartDatetime === false) {
            $jmapFormat = "Y-m-d\TH:i:s";
            $iCalFormat = "Ymd\THis";

            $jmapStartDatetime = \DateTime::createFromFormat($jmapFormat, $start);
        }

        if ($jmapStartDatetime === false) {
            $jmapFormat = "Y-m-d";
            $iCalFormat = "Ymd";
        }

        // Parses the jmap formatted DateTime to an iCal formatted string.
        $iCalStart = AdapterUtil::parseDateTime($start, $jmapFormat, $iCalFormat);

        // Checks whether the event also has a timezone connected to it..
        if (is_null($timeZone)) {
            $iCalStartDateTime = \DateTime::createFromFormat($iCalFormat, $iCalStart);
        } else {
            $iCalStartDateTime = \DateTime::createFromFormat($iCalFormat, $iCalStart, new \DateTimeZone($timeZone));
        }

        $this->iCalEvent->VEVENT->add('DTSTART', $iCalStartDateTime);
    }

    public function setDTEnd($start, $duration, $timeZone)
    {
        if (
            !AdapterUtil::isSetNotNullAndNotEmpty($start)
            || !AdapterUtil::isSetNotNullAndNotEmpty($duration)
        ) {
            return;
        }

        $interval = new \DateInterval($duration);

        // 'DTEND' must be strictly greater than 'DTSTART' if it is set.
        if ($interval->format("%y%m%d%h%i%s") == "000000") {
            return;
        }

        // Use the existing 'DTSTART' value.
        $iCalDateTimeStart = $this->iCalEvent->VEVENT->DTSTART->getDateTime();

        $iCalDateTimeEnd = $iCalDateTimeStart->add($interval);

        if (!is_null($timeZone)) {
            $iCalDateTimeEnd->setTimezone(new \DateTimeZone($timeZone));
        }

        $this->iCalEvent->VEVENT->add('DTEND', $iCalDateTimeEnd);
    }


    public function getDuration()
    {
        $start = $this->iCalEvent->VEVENT->DTSTART;
        $end = $this->iCalEvent->VEVENT->DTEND;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($start)) {
            return null;
        }

        // Default value in jmap is 'PT0S'.
        if (!AdapterUtil::isSetNotNullAndNotEmpty($end)) {
            return 'PT0S';
        }

        $dtStart = $start->getDateTime();
        $dtEnd = $end->getDateTime();

        if ($dtStart == $dtEnd) {
            return 'PT0S';
        }

        $interval = $dtStart->diff($dtEnd);

        // Create a pattern to return the duration in the correct format.
        $outputFormat = 'P';
        //TODO: Check whether months/years need to be added.
        if ($interval->format('%d') != 0) {
            $outputFormat .= '%dD';
        }

        // If the duration contains hours/minutes/seconds append 'T' to the format.
        if ($interval->format('%h%i%s') != '000') {
            $outputFormat .= 'T';
        }

        if ($interval->format('%h') != '0') {
            $outputFormat .= '%hH';
        }

        if ($interval->format('%i') != '0') {
            $outputFormat .= '%iM';
        }

        if ($interval->format('%s') != '0') {
            $outputFormat .= "%sS";
        }

        return $interval->format($outputFormat);
    }

    public function getTimeZone()
    {
        $dtStart = $this->iCalEvent->VEVENT->DTSTART;

        // Check if DTSTART exists.
        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return null;
        }

        $timeZone = $dtStart->getDateTime()->getTimezone();

        // Check if there is a time zone connected to the DTSTART property
        if (!AdapterUtil::isSetNotNullAndNotEmpty($timeZone)) {
            return null;
        }

        return $timeZone->getName();
    }

    //TODO: this might need to be revamped to accomodate for scheduling and non-scheduling properties
    public function getUpdated()
    {
        // Get both the "LAST-MODIFIED" and "DTSTAMP" properties, as only one of
        // them is converted into the "updated" jmap property.
        $lastModified = $this->iCalEvent->VEVENT->{'LAST-MODIFIED'};
        $dTStamp = $this->iCalEvent->VEVENT->DTSTAMP;
        $dateUpdated = null;

        // As per IETF standard: Use the latest of the ones that is present,
        // if they are no scheduling entity.
        if (AdapterUtil::isSetNotNullAndNotEmpty($lastModified)) {
            $dateUpdated = $lastModified->getDateTime();
        }

        if (AdapterUtil::isSetNotNullAndNotEmpty($dTStamp)) {
            $dTStampDateTime = $dTStamp->getDateTime();
            $dateUpdated = $dateUpdated < $dTStampDateTime ? $dTStampDateTime : $dateUpdated;
        }

        // This is only the case if neither property is set in the iCal Event.
        if (is_null($dateUpdated)) {
            return null;
        }

        $jmapUpdated = $dateUpdated->format("Y-m-d\TH:i:s\Z");

        return $jmapUpdated;
    }

    public function setUpdated($updated)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($updated)) {
            return;
        }

        $jmapFormat = "Y-m-d\TH:i:s\Z";
        $iCalFormat = "Ymd\THis\Z";

        $iCalendarUpdated = AdapterUtil::parseDateTime($updated, $jmapFormat, $iCalFormat);

        $iCalendarUpdatedDateTime = \DateTime::createFromFormat($iCalFormat, $iCalendarUpdated);

        if (isset($this->iCalEvent->VEVENT->DTSTAMP)) {
            $this->iCalEvent->VEVENT->DTSTAMP = $iCalendarUpdatedDateTime;
        } else {
            $this->iCalEvent->VEVENT->add('DTSTAMP', $iCalendarUpdatedDateTime);
        }
    }

    public function getUid()
    {
        $uid = $this->iCalEvent->VEVENT->UID;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($uid)) {
            $uid = uniqid("", true) . ".OpenXPort";
        }

        return $uid->getValue();
    }

    public function setUid($uid)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($uid)) {
            return;
        }

        // VObject adds a uid to new VEVENT objects which we will overwrite with the existing one.
        if (isset($this->iCalEvent->VEVENT->UID)) {
            $this->iCalEvent->VEVENT->UID = $uid;
        } else {
            $this->iCalEvent->VEVENT->add('UID', $uid);
        }
    }

    public function getProdId()
    {
        $prodId = $this->iCalEvent->PRODID;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($prodId)) {
            return null;
        }

        return (string)$prodId;
    }

    public function setProdId($prodId)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($prodId)) {
            return;
        }

        // Simmilarly to the UID, this is already set by VObject but we will
        // overwrite it since we already have a PRODID, that does not need
        // to be changed.
        if (isset($this->iCalEvent->PRODID)) {
            $this->iCalEvent->PRODID = $prodId;
        } else {
            $this->iCalEvent->add("PRODID", $prodId);
        }
    }

    public function getSequence()
    {
        $sequence = $this->iCalEvent->VEVENT->SEQUENCE;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($sequence)) {
            return null;
        }

        return $sequence->getValue();
    }

    public function setSequence($sequence)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($sequence)) {
            return;
        }

        $this->iCalEvent->VEVENT->add("SEQUENCE", $sequence);
    }

    public function getStatus()
    {
        $status = $this->iCalEvent->VEVENT->STATUS;

        switch ($status) {
            case 'TENTATIVE':
                return "tentative";
                break;

            case 'CONFIRMED':
                return "confirmed";
                break;

            case 'CANCELLED':
                return "cancelled";
                break;

            default:
                return null;
                break;
        }
    }

    public function setStatus($status)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($status)) {
            return;
        }

        $iCalStatus = "";

        switch ($status) {
            case 'tentative':
                $iCalStatus = "TENTATIVE";
                break;

            case 'cancelled':
                $iCalStatus = "CANCELLED";
                break;

            case 'confirmed':
                $iCalStatus = "CONFIRMED";
                break;

            default:
                return;
        }

        $this->iCalEvent->VEVENT->add("STATUS", $iCalStatus);
    }

    public function getCategories()
    {
        $categories = $this->iCalEvent->VEVENT->CATEGORIES;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($categories)) {
            return null;
        }

        $jmapKeyWords = [];

        $categoryValues = explode(",", $categories);

        foreach ($categoryValues as $cat) {
            $jmapKeyWords[$cat] = true;
        }

        return $jmapKeyWords;
    }

    public function setCategories($keywords)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($keywords)) {
            return;
        }

        $categories = [];

        foreach ($keywords as $category => $bool) {
            if ($bool) {
                array_push($categories, $category);
            }
        }

        if (count($categories) == 0) {
            return;
        }

        $iCalCategories = implode(",", $categories);

        $this->iCalEvent->VEVENT->add("CATEGORIES", $iCalCategories);
    }


    public function getLocation()
    {
        // This will not map any VLOCATION properties, they need ot be handled seperately.
        $location = $this->iCalEvent->VEVENT->LOCATION;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($location)) {
            return null;
        }

        $jmapLocations = [];

        // Apply json escaping to the name.
        $locationJmapEscaped = addcslashes(stripslashes($location), '["\]');

        $jmapLocation = new Location();
        $jmapLocation->setType("Location");
        $jmapLocation->setName($locationJmapEscaped);

        $jmapLocations["1"] = $jmapLocation;

        return $jmapLocations;
    }

    public function setLocation($locations)
    {
        // This only converts the first location in the jmap event.
        if (!AdapterUtil::isSetNotNullAndNotEmpty($locations)) {
            return;
        }

        // Turn the jmap object into an array.
        $locationsArray = json_decode(json_encode($locations), true);

        // Only use the first location and add iCal escaping to it.
        $locationICalEscaped = addcslashes(stripslashes($locationsArray["1"]["name"]), "[,;]");

        $this->iCalEvent->VEVENT->add("LOCATION", $locationICalEscaped);
    }

    public function getFreeBusy()
    {
        $freeBusy = $this->iCalEvent->VEVENT->TRANSP;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($freeBusy)) {
            return null;
        }

        // "free" is supposed to be the default value.
        return $freeBusy->getValue() == 'OPAGUE' ? 'busy' : 'free';
    }

    public function setFreeBusy($freeBusy)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($freeBusy)) {
            return;
        }

        // "OPAGUE" is supposed to be the default value.
        $iCalFreeBusy = $freeBusy == 'free' ? 'TRANSPARENT' : 'OPAGUE';

        $this->iCalEvent->VEVENT->add("TRANSP", $iCalFreeBusy);
    }

    public function getClass()
    {
        $class = $this->iCalEvent->VEVENT->CLASS;

        if (is_null($class)) {
            return null;
        }

        switch ($class) {
            case 'CONFIDENTIAL':
                return 'secret';
                break;

            case 'PRIVATE':
                return 'private';
                break;

            case 'PUBLIC':
                return 'public';
                break;

            default:
                return null;
                break;
        }
    }

    public function setClass($privacy)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($privacy)) {
            return;
        }

        $iCalClass = "";

        switch ($privacy) {
            case 'secret':
                $iCalClass = "CONFIDENTIAL";
                break;

            case 'private':
                $iCalClass = "PRIVATE";
                break;

            case 'public':
                $iCalClass = "PUBLIC";
                break;

            default:
                return;
        }

        $this->iCalEvent->VEVENT->add("CLASS", $iCalClass);
    }

    public function getRRule()
    {
        $rRules = $this->iCalEvent->VEVENT->RRULE;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($rRules)) {
            return null;
        }

        $jmapRecurrenceRules = [];

        // One iCal event can have multiple RRULE properties, so every one of them needs to be mapped
        // to it's own object in the recurrenceRules jmap property.
        foreach ($rRules as $rRule) {
            if (!AdapterUtil::isSetNotNullAndNotEmpty($rRule)) {
                return null;
            }

            $rRuleString = $rRule->getValue();

            if (!AdapterUtil::isSetNotNullAndNotEmpty($rRuleString)) {
                return null;
            }

            $jmapRecurrenceRule = new RecurrenceRule();
            $jmapRecurrenceRule->setType("RecurrenceRule");

            foreach (explode(";", $rRuleString) as $rec) {
                // iCal events split recurrencies by naming the type (as in FREQ, etc.) and value related to the type
                // using a "=".
                $splitRule = explode("=", $rec);
                $key = $splitRule[0];
                $value = $splitRule[1];

                switch ($key) {
                    case 'FREQ':
                        $jmapRecurrenceRule->setFrequency(
                            JSCalendarICalendarAdapterUtil::convertFromICalFreqToJmapFrequency($value)
                        );
                        break;

                    case 'INTERVAL':
                        $jmapRecurrenceRule->setInterval(
                            JSCalendarICalendarAdapterUtil::convertFromICalIntervalToJmapInterval($value)
                        );
                        break;

                    case 'RSCALE':
                        $jmapRecurrenceRule->setRscale(
                            JSCalendarICalendarAdapterUtil::convertFromICalRScaleToJmapRScale($value)
                        );
                        break;

                    case 'SKIP':
                        $jmapRecurrenceRule->setSkip(
                            JSCalendarICalendarAdapterUtil::convertFromICalSkipToJmapSkip($value)
                        );
                        break;

                    case 'WKST':
                        $jmapRecurrenceRule->setFirstDayOfWeek(
                            JSCalendarICalendarAdapterUtil::convertFromICalWKSTToJmapFirstDayOfWeek($value)
                        );
                        break;

                    case 'BYDAY':
                        $jmapRecurrenceRule->setByDay(
                            JSCalendarICalendarAdapterUtil::convertFromICalByDayToJmapByDay($value)
                        );
                        break;

                    case 'BYMONTHDAY':
                        $jmapRecurrenceRule->setByMonthDay(
                            JSCalendarICalendarAdapterUtil::convertFromICalByMonthDayToJmapByMonthDay($value)
                        );
                        break;

                    case 'BYMONTH':
                        $jmapRecurrenceRule->setByMonth(
                            JSCalendarICalendarAdapterUtil::convertFromICalByMonthToJmapByMonth($value)
                        );
                        break;

                    case 'BYYEARDAY':
                        $jmapRecurrenceRule->setByYearDay(
                            JSCalendarICalendarAdapterUtil::convertFromICalByYearDayToJmapByYearDay($value)
                        );
                        break;

                    case 'BYWEEKNO':
                        $jmapRecurrenceRule->setByWeekNo(
                            JSCalendarICalendarAdapterUtil::convertFromICalByWeekNoToJmapByWeekNo($value)
                        );
                        break;

                    case 'BYHOUR':
                        $jmapRecurrenceRule->setByHour(
                            JSCalendarICalendarAdapterUtil::convertFromICalByHourToJmapByHour($value)
                        );
                        break;

                    case 'BYMINUTE':
                        $jmapRecurrenceRule->setByMinute(
                            JSCalendarICalendarAdapterUtil::convertFromICalByMinuteToJmapByMinute($value)
                        );
                        break;

                    case 'BYSECOND':
                        $jmapRecurrenceRule->setBySecond(
                            JSCalendarICalendarAdapterUtil::convertFromICalBySecondToJmapBySecond($value)
                        );
                        break;

                    case 'BYSETPOS':
                        $jmapRecurrenceRule->setBySetPosition(
                            JSCalendarICalendarAdapterUtil::convertFromICalBySetPosToJmapBySetPosition($value)
                        );
                        break;

                    case 'COUNT':
                        $jmapRecurrenceRule->setCount(
                            JSCalendarICalendarAdapterUtil::convertFromICalCountToJmapCount($value)
                        );
                        break;

                    case 'UNTIL':
                        $jmapRecurrenceRule->setUntil(
                            JSCalendarICalendarAdapterUtil::convertFromICalUntilToJmapUntil($value)
                        );
                        break;

                    default:
                        // As long as the iCal event follows the rfc, this should not haapen.
                        // Might want to add to the logger in case this happens.
                        break;
                }
            }

            array_push($jmapRecurrenceRules, $jmapRecurrenceRule);
        }

        return $jmapRecurrenceRule;
    }

    public function setRRule($recurrenceRules)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceRules)) {
            return;
        }

        foreach ($recurrenceRules as $rec) {
            $iCalRRule = [];

            foreach ($rec as $key => $value) {
                $iCalKeyword = null;
                $iCalValue = null;

                switch ($key) {
                    case 'frequency':
                        $iCalKeyword = "FREQ";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapFrequencyToICalFreq($value);
                        break;

                    case 'interval':
                        $iCalKeyword = "INTERVAL";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapIntervalToICalInterval($value);
                        break;

                    case 'rscale':
                        $iCalKeyword = "RSCALE";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapRScaleToICalRScale($value);
                        break;

                    case 'skip':
                        $iCalKeyword = "SKIP";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapSkipToICalSkip($value);
                        break;

                    case 'firstDayOfWeek':
                        $iCalKeyword = "WKST";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapFirstDayOfWeekToICalWKST($value);
                        break;

                    case 'byDay':
                        $iCalKeyword = "BYDAY";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByDayToICalByDay($value);
                        break;

                    case 'byMonthDay':
                        $iCalKeyword = "BYMONTHDAY";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByMonthDayToICalByMonthDay($value);
                        break;

                    case 'byMonth':
                        $iCalKeyword = "BYMONTH";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByMonthToICalByMonth($value);
                        break;

                    case 'byYearDay':
                        $iCalKeyword = "BYYEARDAY";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByYearDayToICalByYearDay($value);
                        break;

                    case 'byWeekNo':
                        $iCalKeyword = "BYWEEKNO";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByWeekNoToICalByWeekNo($value);
                        break;

                    case 'byHour':
                        $iCalKeyword = "BYHOUR";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByHourToICalByHour($value);
                        break;

                    case 'byMinute':
                        $iCalKeyword = "BYMINUTE";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapByMinuteToICalByMinute($value);
                        break;

                    case 'bySecond':
                        $iCalKeyword = "BYSECOND";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapBySecondToICalBySecond($value);
                        break;

                    case 'bySetPosition':
                        $iCalKeyword = "BYSETPOS";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapBySetPositionToICalBySetPosition($value);
                        break;

                    case 'count':
                        $iCalKeyword = "COUNT";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapCountToICalCount($value);
                        break;

                    case 'until':
                        $iCalKeyword = "UNTIL";
                        $iCalValue = JSCalendarICalendarAdapterUtil::
                        convertFromJmapUntilToICalUntil($value);
                        break;

                    case '@type':
                        // Ignored in the iCal format.
                        break;

                    default:
                        // TODO: consider logging as this shouldn't happen.
                        break;
                }

                // If a recurrence rule was found and a corresponding value could be converted from
                // the JSCalendar event, add it to the properties mapped to the iCal event.
                if (
                    AdapterUtil::isSetNotNullAndNotEmpty($iCalKeyword) &&
                    AdapterUtil::isSetNotNullAndNotEmpty($iCalValue)
                ) {
                    $newRRule = $iCalKeyword . "=" . $iCalValue;
                    array_push($iCalRRule, $newRRule);
                }
            }

            // Add each RRULE by combining the components. Each recurrence rule in the JSCal event
            // can be transformed into its own RRULE.
            if (AdapterUtil::isSetNotNullAndNotEmpty($iCalRRule)) {
                $this->iCalEvent->VEVENT->add("RRULE", implode(";", $iCalRRule));
            }
        }
    }

    public function setRecurrenceId($recurrenceId)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceId)) {
            return;
        }

        $recurrenceIdDateTime = DateTime::createFromFormat("Y-m-d\TH:i:s", $recurrenceId);

        $this->iCalEvent->VEVENT->add("RECURRENCE-ID", $recurrenceIdDateTime);
    }

    public function getParticipants()
    {
        /*
         * TODO: implement this property like it is done in the horde adapter.
         * /../horde-jmap/src/adapter/HordeCalendarEventAdapter.php:531
         *
         * Strategy:
         * Get the attendee values from the iCal event object.
         *
         * Extract the organizer from the iCal event.
         *
         * Turn the Attendee property into an array so that looping over it is possible.
         *
         * Loop through every attendee and create a new Participant from the OXP class.
         *
         * Get each property of the attendeee and add it to the participant.
         *
         * Also do this for the organizer, adding everyone to an array of particitpants.
         *
         * If there is not organizer, create a generic one.
         *
         * Return the array of attendees.
         */

         return null;
    }
}
