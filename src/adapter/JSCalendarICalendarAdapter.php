<?php

namespace OpenXPort\Adapter;

use DateTime;
use Sabre\VObject\Component\VCalendar;
use OpenXPort\Util\Logger;
use Sabre\VObject;
use OpenXPort\Jmap\Calendar\Location;
use OpenXPort\Jmap\Calendar\OffsetTrigger;
use OpenXPort\Jmap\Calendar\AbsoluteTrigger;
use OpenXPort\Jmap\Calendar\Alert;
use OpenXPort\Jmap\Calendar\RecurrenceRule;
use OpenXPort\Jmap\Calendar\Participant;
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

    private $oxpProperties = [];

    public function __construct()
    {
        $this->iCalEvent = new VCalendar(['VEVENT' => []]);
        $this->logger = Logger::getInstance();
    }

    public function getICalEvent()
    {
        return $this->iCalEvent;
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

    /**
     * This will return every component set in the VEVENT property of the iCal event set
     * in this adapter as an associative array to be fed into a different iCalendar component.
     */
    public function getVeventComponents()
    {
        $vevent = $this->iCalEvent->VEVENT;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($vevent)) {
            return null;
        }

        $dateTimeProperties = array("RECURRENCE-ID", "DTSTART", "DTEND", "LAST-MODIFIED", "DTSTAMP", "CREATED");

        $veventComponents = [];

        foreach ($vevent->children() as $veventProperty) {
            $propertyName = $veventProperty->name;

            if (in_array($propertyName, $dateTimeProperties)) {
                $propertyValue = $veventProperty->getDateTime();
            } else {
                $propertyValue = $veventProperty->getValue();
            }

            $veventComponents[$propertyName] = $propertyValue;
        }

        return $veventComponents;
    }

    public function getCalendarId()
    {
        if (!array_key_exists("calendarId", $this->oxpProperties)) {
            $this->logger->warning("calendarId does not exist for event " . $this->getUid());
            return;
        }

        return $this->oxpProperties["calendarId"];
    }

    public function setCalendarId($calendarId)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($calendarId)) {
            return;
        }

        $this->oxpProperties["calendarId"] = $calendarId;
    }

    public function getSummary()
    {
        $summary = $this->iCalEvent->VEVENT->SUMMARY;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($summary)) {
            return null;
        }

        return $summary->getValue();
    }

    public function setSummary($summary)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($summary)) {
            return;
        }

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

        // Always uses local dateTime in jmap. UTC values are handled in getTimeZone().
        $jmapStart = $dtStart->format("Y-m-d\TH:i:s");
        return $jmapStart;
    }

    public function setDTStart($start, $timeZone)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($start)) {
            return;
        }


        // The following checks for the right DateTime Format and creates a new DateTime in the jmap format..
        // JSCal start properties are should not be UTC Date Time values and instead have the timeZone property
        // set to "Etc/UTC", in which case we can use this to set the iCal value to UTC.
        $jmapFormat = "Y-m-d\TH:i:s";
        $iCalFormat = $timeZone == "Etc/UTC" ? "Ymd\THis\Z" : "Ymd\THis";


        $jmapStartDatetime = \DateTime::createFromFormat($jmapFormat, $start);

        if ($jmapStartDatetime === false) {
            $jmapFormat = "Y-m-d";
            $iCalFormat = "Ymd";
        }

        // Parses the jmap formatted DateTime to an iCal formatted string.
        $iCalStart = AdapterUtil::parseDateTime($start, $jmapFormat, $iCalFormat);

        // Checks whether the event also has a timezone connected to it..
        if (is_null($timeZone) || $timeZone == "Etc/UTC") {
            $iCalStartDateTime = \DateTime::createFromFormat($iCalFormat, $iCalStart);
        } else {
            $iCalStartDateTime = \DateTime::createFromFormat($iCalFormat, $iCalStart, new \DateTimeZone($timeZone));
        }

        $this->iCalEvent->VEVENT->add('DTSTART', $iCalStartDateTime);
    }

    public function getShowWithoutTime()
    {
        $dtStart = $this->iCalEvent->VEVENT->DTSTART;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return null;
        }

        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart["VALUE"])) {
            return null;
        }

        return $dtStart["VALUE"] == "DATE" ? true : null;
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

        // JSCalendar start properties may not be UTC values ("Z" at the end of the Datetime). Instead, the timeZone
        // property should be set to "Etc/UTC".
        if (str_contains($dtStart->getValue(), "Z")) {
            return "Etc/UTC";
        } else {
            $timeZone = $dtStart->getDateTime()->getTimezone();
        }

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

    public function getColor()
    {
        $color = $this->iCalEvent->VEVENT->COLOR;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($color)) {
            return null;
        }

        return $color;
    }

    public function setColor($color)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($color)) {
            return;
        }

        $this->iCalEvent->VEVENT->add("COLOR", $color);
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

        // Only use the first location and add iCal escaping to it.
        $locationICalEscaped = addcslashes(stripslashes($locations[1]->getName()), "[,;]");

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

    public function getAlerts()
    {
        $alarms = $this->iCalEvent->VEVENT->VALARM;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($alarms)) {
            return null;
        }

        $jmapAlerts = [];
        $key = 1;

        foreach ($alarms as $alarm) {
            $alert = new Alert();

            // The trigger can either be a relative offset or a date time and is mapped to an OffsetTrigger
            // or an AbsoluteTrigger using the connected parameters respectively.
            if (strcmp($alarm->TRIGGER->getValueType(), "DURATION") === 0) {
                $trigger = new OffsetTrigger();
                $trigger->setType("OffsetTrigger");

                $trigger->setOffset($alarm->TRIGGER->getValue());

                // If the TRIGGER property has a "RELATED" parameter, map it to relativeTo;
                if (!is_null($alarm->TRIGGER["RELATED"])) {
                    $trigger->setRelativeTo(strtolower($alarm->TRIGGER["RELATED"]->getValue()));
                }
            } elseif (strcmp($alarm->TRIGGER->getValueType(), "DATE-TIME") === 0) {
                $trigger = new AbsoluteTrigger();
                $trigger->setType("AbsoluteTrigger");

                $triggerDateTime = $alarm->TRIGGER->getDateTime();

                $trigger->setWhen(date_format($triggerDateTime, "Y-m-d\TH:i:s\Z"));
            } else {
                $this->logger->error(
                    "Unable to create iCal trigger for alert from value: "
                    . $alarm->TRIGGER->getValue()
                );

                continue;
            }

            $alert->setTrigger($trigger);

            $action = $alarm->ACTION;

            // Check if a value is set for ACTION
            if (AdapterUtil::isSetNotNullAndNotEmpty($action)) {
                $action = $action->getValue();
            }

            // "DISPLAY" and "AUDIO" are both converted to "display", as there is no direct
            // counterpart for "AUDIO" in JSCalendar.
            if (strcmp($action, "DISPLAY") === 0 || strcmp($action, "AUDIO") === 0) {
                $alert->setAction("display");
            } elseif (strcmp($action, "EMAIL") === 0) {
                $alert->setAction("email");
            }

            $jmapAlerts[$key] = $alert;
            $key++;
        }

        return $jmapAlerts;
    }

    public function setAlerts($alerts)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($alerts)) {
            return;
        }

        // Use a running index to refer to the current-most VALARM added to the event.
        $alarmIndex = 0;


        foreach ($alerts as $id => $alert) {
            $this->iCalEvent->VEVENT->add("VALARM", []);

            $jsCalAction = $alert->getAction();

            // Set the ACTION property. "EMAIL" and "DISPLAY" are the only ones relevant for mapping.
            if (strcmp($jsCalAction, "email") === 0) {
                $iCalAction = "EMAIL";
            } else {
                $iCalAction = "DISPLAY";
            }

            $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("ACTION", $iCalAction);

            $jsCalTrigger = $alert->getTrigger();
            $triggerType = $jsCalTrigger->getType();

            // Set the TRIGGER property.
            if (strcmp($triggerType, "OffsetTrigger") === 0) {
                $triggerValue = $jsCalTrigger->getOffset();

                // An offset trigger can contain a relativeTo parameter, which needs to be mapped as well.
                $relativeTo = $jsCalTrigger->getRelativeTo();

                if (!is_null($relativeTo)) {
                    $iCalRelated = strtoupper($relativeTo);

                    $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add(
                        "TRIGGER",
                        $triggerValue,
                        ["RELATED" => $iCalRelated]
                    );
                } else {
                    $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("TRIGGER", $triggerValue);
                }
            } elseif (strcmp($triggerType, "AbsoluteTrigger") === 0) {
                $triggerValue = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $jsCalTrigger->getWhen());

                // If the date time is false, it was probably not in UTC, which is the standard for both formats.
                // Log and skip to the next alert.
                if (!$triggerValue) {
                    // TODO: Add the alert to the event as some sort of custom alert if this happens.
                    $this->logger->error(
                        "Unable to create date time for absolute trigger from value: "
                        . $jsCalTrigger->getWhen()
                    );

                    continue;
                }

                $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("TRIGGER", $triggerValue, ["VALUE" => "DATE-TIME"]);
            } else {
                // The trigger type is not one of the two known ones.
                $this->logger->error("Unable to created trigger value from trigger type: " . $jsCalTrigger->getType());

                continue;
            }

            $alarmIndex++;
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
                        //TODO: add the timezone of the current event as another property so that if it is something
                        // else than local (i.e. utc) the difference is added to the until value.
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

        return $jmapRecurrenceRules;
    }

    public function setRRule($recurrenceRules)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceRules)) {
            return;
        }

        foreach ($recurrenceRules as $rec) {
            // Read the values of every RecurrenceRule object and convert them to
            // their iCal counterpart if they are not null. Then, add each key-value-
            // pair to an array that is combined into a string and added to the VEVENT
            // after each iteration.
            $iCalRRule = [];

            $frequency = $rec->getFrequency();
            if (AdapterUtil::isSetNotNullAndNotEmpty($frequency)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapFrequencyToICalFreq($frequency);

                array_push($iCalRRule, "FREQ=" . $iCalValue);
            }

            $jsCalValue = $rec->getInterval();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapIntervalToICalInterval($jsCalValue);

                array_push($iCalRRule, "INTERVAL=" . $iCalValue);
            }

            $jsCalValue = $rec->getRscale();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapRScaleToICalRScale($jsCalValue);

                array_push($iCalRRule, "RSCALE=" . $iCalValue);
            }

            $jsCalValue = $rec->getSkip();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapSkipToICalSkip($jsCalValue);

                array_push($iCalRRule, "SKIP=" . $iCalValue);
            }

            $jsCalValue = $rec->getFirstDayOfWeek();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapFirstDayOfWeekToICalWKST($jsCalValue);

                array_push($iCalRRule, "WKST=" . $iCalValue);
            }

            $jsCalValue = $rec->getByDay();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByDayToICalByDay($jsCalValue);

                array_push($iCalRRule, "BYDAY=" . $iCalValue);
            }

            $jsCalValue = $rec->getByMonthDay();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByMonthDayToICalByMonthDay($jsCalValue);

                array_push($iCalRRule, "BYMONTHDAY=" . $iCalValue);
            }

            $jsCalValue = $rec->getByMonth();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByMonthDayToICalByMonthDay($jsCalValue);

                array_push($iCalRRule, "BYMONTH=" . $iCalValue);
            }

            $jsCalValue = $rec->getByYearDay();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByYearDayToICalByYearDay($jsCalValue);

                array_push($iCalRRule, "BYYEARDAY=" . $iCalValue);
            }

            $jsCalValue = $rec->getByWeekNo();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByWeekNoToICalByWeekNo($jsCalValue);

                array_push($iCalRRule, "BYWEEKNO=" . $iCalValue);
            }

            $jsCalValue = $rec->getByHour();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByHourToICalByHour($jsCalValue);

                array_push($iCalRRule, "BYHOUR=" . $iCalValue);
            }

            $jsCalValue = $rec->getByMinute();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapByMinuteToICalByMinute($jsCalValue);

                array_push($iCalRRule, "BYMINUTE=" . $iCalValue);
            }

            $jsCalValue = $rec->getBySecond();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapBySecondToICalBySecond($jsCalValue);

                array_push($iCalRRule, "BYSECOND=" . $iCalValue);
            }

            $jsCalValue = $rec->getBySetPosition();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapBySetPositionToICalBySetPosition($jsCalValue);

                array_push($iCalRRule, "BYSETPOS=" . $iCalValue);
            }

            $jsCalValue = $rec->getCount();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapCountToICalCount($jsCalValue);

                array_push($iCalRRule, "COUNT=" . $iCalValue);
            }

            $jsCalValue = $rec->getUntil();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
                $dtStart = $this->iCalEvent->VEVENT->DTSTART;
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapUntilToICalUntil($jsCalValue, $dtStart);

                array_push($iCalRRule, "UNTIL=" . $iCalValue);
            }

            // Add the RRULEs to the current event.
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
        $organizer = $this->iCalEvent->VEVENT->ORGANIZER;
        $attendees = $this->iCalEvent->VEVENT->ATTENDEE;

        if (
            !AdapterUtil::isSetNotNullAndNotEmpty($organizer)
            && !AdapterUtil::isSetNotNullAndNotEmpty($attendees)
        ) {
                return null;
        }

        $jmapParticipants = [];

        if (AdapterUtil::isSetNotNullAndNotEmpty($attendees)) {
            foreach ($attendees as $attendee) {
                $attendeeValue = $attendee->getValue();

                if (is_null($attendeeValue)) {
                    continue;
                }

                $jmapParticipant = new Participant();

                $jmapParticipant->setType("Participant");

                if (explode(":", $attendeeValue)[0] == "mailto") {
                    $jmapParticipant->setSendTo(array("imip" => $attendeeValue));
                } else {
                    $jmapParticipant->setSendTo(array("other" => $attendeeValue));
                }

                // Set any properties using the helper function.
                $this->addParticipantParameters($attendee->parameters, $jmapParticipant);

                $participantId = md5(print_r($jmapParticipant, true));

                $jmapParticipants["$participantId"] = $jmapParticipant;
            }
        }

        if (AdapterUtil::isSetNotNullAndNotEmpty($organizer)) {
            $oValue = $organizer->getValue();

            $jmapParticipant = null;

            $participantId = null;

            foreach ($jmapParticipants as $id => $participant) {
                $curSendTo = $participant->getSendTo();

                if (
                    explode(":", $oValue)[0] == "mailto" &&
                    array_key_exists("imip", $curSendTo) &&
                    $curSendTo["imip"] == $oValue
                ) {
                    $curRoles = $participant->getRoles();

                    $curRoles["owner"] = true;

                    $participant->setRoles($curRoles);
                    $jmapParticipant = $participant;
                    $participantId = $id;
                } elseif (array_key_exists("other", $curSendTo) && $curSendTo["other"] == $oValue) {
                    $curRoles = $participant->getRoles();

                    $curRoles["owner"] = true;

                    $participant->setRoles($curRoles);
                    $jmapParticipant = $participant;
                    $participantId = $id;
                }
            }

            // If no match was found, the event's organizer is not yet registered as an attendee and
            // is therefore created as a new participant.
            if (is_null($jmapParticipant)) {
                $jmapParticipant = new Participant();
                $jmapParticipant->setType("Participant");

                if (explode(":", $oValue)[0] == "mailto") {
                    $jmapParticipant->setSendTo(array("imip" => $oValue));
                } else {
                    $jmapParticipant->setSendTo(array("other" => $oValue));
                }

                $participantId = md5(print_r($jmapParticipant, true));
            }

            $this->addParticipantParameters($organizer->parameters, $jmapParticipant);

            // Always and only false for organizers.
            $jmapParticipant->setExpectReply(false);

            $curRoles = $jmapParticipant->getRoles();

            // If the Organizer was created as a new participant, this will always be empty and
            // their role must be set to "owner".
            if (empty($curRoles)) {
                $jmapParticipant->setRoles(array("owner" => true));
            }

            $jmapParticipants["$participantId"] = $jmapParticipant;
        }

        return $jmapParticipants;
    }

    private function addParticipantParameters($parameters, $participant)
    {
        // Use the VObject library to loop through each of the parameters of the property by their name.
        foreach ($parameters as $param) {
            // Now map each parameter using their name and value.
            switch ($param->name) {
                case "CN":
                    $participant->setName($param->getValue());
                    break;

                case "CUTYPE":
                    $participant->setKind(
                        JSCalendarICalendarAdapterUtil::convertFromICalCUTypeToJmapKind($param->getValue())
                    );
                    break;

                case "DELEGATED-FROM":
                    $participant->setDelegatedFrom(
                        JSCalendarICalendarAdapterUtil::converFromICalDelegatedFromToJmapDelegatedFrom(
                            $param->getValue()
                        )
                    );
                    break;

                case "DELEAGTED-TO":
                    $participant->setDelegatedTo(
                        JSCalendarICalendarAdapterUtil::converFromICalDelegatedToToJmapDelegatedTo($param->getValue())
                    );
                    break;

                case "DIR":
                    // TODO: implement me
                    break;

                case "LANGUAGE":
                    $participant->setLanguage($param->getValue());
                    break;

                case "MEMBER":
                    // TODO: implement me. The conversion specs suggest to map participants that are groups first
                    // so we would need to do some filtering/sorting ahead of the conversions.
                    break;

                case "PARTSTAT":
                    $participant->setParticipationStatus(
                        JSCalendarICalendarAdapterUtil::convertFromICalPartStatToJmapParticipationStatus(
                            $param->getValue()
                        )
                    );
                    break;

                case "ROLE":
                    $participant->setRoles(
                        JSCalendarICalendarAdapterUtil::convertFromICalRoleToJmapRoles($param->getValue())
                    );
                    break;

                case "RSVP":
                    $participant->setExpectReply(
                        JSCalendarICalendarAdapterUtil::convertFromICalRSVPToJmapExpectReply($param->getValue())
                    );
                    break;

                case "SCHEDULE-AGENT":
                    $participant->setScheduleAgent(
                        JSCalendarICalendarAdapterUtil::convertFromICalScheduleAgentToJmapScheduleAgent(
                            $param->getValue()
                        )
                    );
                    break;

                case "SCHEDULE-FORCE-SEND":
                    $participant->setScheduleForceSend(
                        JSCalendarICalendarAdapterUtil::convertFromICalScheduleForceSendToJmapScheduleForceSend(
                            $param->getValue()
                        )
                    );
                    break;

                case "SCHEDULE-STATUS":
                    $participant->setScheduleStatus(
                        JSCalendarICalendarAdapterUtil::convertFromICalScheduleStatusToJmapScheduleStatus(
                            $param->getValue()
                        )
                    );
                    break;

                case "SENT-BY":
                    $participant->setInvitedBy($param->getValue());
                    break;

                default:
                    break;
            }
        }
    }

    public function setParticipants($participants)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($participants)) {
            return;
        }

        foreach ($participants as $id => $participant) {
            // Define the value (mail address or other) for this property.
            $sendTo = $participant->getSendTo();
            if (AdapterUtil::isSetNotNullAndNotEmpty($sendTo)) {
                $propertyValue = array_key_exists("imip", $sendTo) ? $sendTo["imip"] : $sendTo["other"];
            } else {
                // The property has no value and can't be parsed to iCal, so skip it.
                $this->logger->error("Unable to create ATTENDEE/ORGANIZER property without sendTo data");
                continue;
            }

            $parameters = $this->extractParticipantParameters($participant);

            // Handle roles outside of the helper method to make deciding between ORGANIZER and
            // ATTENDEE easier.
            $jmapRoles = $participant->getRoles();

            if (array_key_exists("owner", $jmapRoles)) {
                $this->iCalEvent->VEVENT->add("ORGANIZER", $propertyValue, $parameters);

                // Unset the role to make sure this participant is only mapped to an ATTENDEE,
                // if it has another eligible role.
                unset($jmapRoles["owner"]);
            }

            // As iCal only supports a single role, this needs to be gathered
            // from the combination of roles connected to a participant.
            if (array_key_exists("attendee", $jmapRoles)) {
                if (array_key_exists("chair", $jmapRoles)) {
                    $parameters["ROLE"] = "CHAIR";
                } elseif (array_key_exists("optional", $jmapRoles)) {
                    $parameters["ROLE"] = "OPT-PARTICIPANT";
                } else {
                    $parameters["ROLE"] = "REQ-PARTICIPANT";
                }
            } elseif (array_key_exists("informational", $jmapRoles)) {
                $parameters["ROLE"] = "NON-PARTICIPANT";
            } elseif (sizeof($jmapRoles) == 1) {
                // A single non-standard role can be parsed.
                $parameters["ROLE"] = strtoupper(array_pop($jmapRoles));
            } elseif (sizeof($jmapRoles) > 1) {
                $this->logger->error("Unable to parse multiple roles: " . implode(", ", $jmapRoles));
                continue;
            }

            if (!is_null($parameters["ROLE"])) {
                $this->iCalEvent->VEVENT->add("ATTENDEE", $propertyValue, $parameters);
            }
        }
    }

    private function extractParticipantParameters($participant)
    {
        // Parse through each property of the participant and add it to an array that is
        // then used to add the parameters to the ATTENDEE.
        $parameters = [];

        $jsCalValue = $participant->getName();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["CN"] = $jsCalValue;
        }

        $jsCalValue = $participant->getKind();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["CN"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapKindToICalCUType($jsCalValue);
        }

        $jsCalValue = $participant->getLanguage();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["LANGUAGE"] = $jsCalValue;
        }

        $jsCalValue = $participant->getParticipationStatus();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["PARTSTAT"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapParticipationStatusToICalPartStat($jsCalValue);
        }

        $jsCalValue = $participant->getExpectReply();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["RSVP"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapExpectReplyToICalRSVP($jsCalValue);
        }

        $jsCalValue = $participant->getDelegatedFrom();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["DELEGATED-FROM"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapDelegatedFromToICalDelegatedFrom($jsCalValue);
        }

        $jsCalValue = $participant->getDelegatedTo();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["DELEGATED-TO"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapDelegatedToToICalDelegatedTo($jsCalValue);
        }

        $jsCalValue = $participant->getScheduleAgent();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["SCHEDULE-AGENT"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapScheduleAgentToICalScheduleAgent($jsCalValue);
        }

        $jsCalValue = $participant->getScheduleForceSend();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["SCHEDULE-FORCE-SEND"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapScheduleForceSendToICaleScheduleForceSend($jsCalValue);
        }

        $jsCalValue = $participant->getScheduleStatus();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["SCHEDULE-STATUS"] = JSCalendarICalendarAdapterUtil
                ::convertFromJmapScheduleStatusToICalScheduleStatus($jsCalValue);
        }

        $jsCalValue = $participant->getInvitedBy();
        if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue)) {
            $parameters["SENT-BY"] = $jsCalValue;
        }

        //TODO: implement "memberOf" and "links".

        return $parameters;
    }
}
