<?php

namespace OpenXPort\Adapter;

use DateTime;
use Sabre\VObject\Component\VCalendar;
use OpenXPort\Util\Logger;
use Sabre\VObject;
use Sabre\VObject\Component\VAlarm;
use OpenXPort\Jmap\Calendar\Location;
use OpenXPort\Jmap\Calendar\OffsetTrigger;
use OpenXPort\Jmap\Calendar\AbsoluteTrigger;
use OpenXPort\Jmap\Calendar\Alert;
use OpenXPort\Jmap\Calendar\Link;
use OpenXPort\Jmap\Calendar\RecurrenceRule;
use OpenXPort\Jmap\Calendar\Participant;
use OpenXPort\Jmap\Calendar\VirtualLocation;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\JSCalendarICalendarAdapterUtil;

/**
 * Generic adapter to convert between ICalendar <-> JSCalendar.
 * https://datatracker.ietf.org/doc/draft-ietf-calext-jscalendar-icalendar/
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

    public function getAsHash()
    {
        return array(
            "iCalendar" => $this->iCalEvent->serialize(),
            "oxpProperties" => $this->oxpProperties
        );
    }

    public function setAsHash($hash)
    {
        $this->setICalEvent($hash["iCalendar"]);

        if (!array_key_exists("oxpProperties", $hash)) {
            return;
        }

        if (array_key_exists("calendarId", $hash["oxpProperties"])) {
            $this->oxpProperties["calendarId"] = $hash["oxpProperties"]["calendarId"];
        }
    }

    /*
     * @return VCalendar The adapter's calendar
     */
    public function getICalEvent()
    {
        return $this->iCalEvent;
    }

    /*
     * Extract the VEVENT component of the adapter's calendar.
     * The VCalendar object contains multiple components, VEVENT is one of them.
     * @return Component The event component of the of the adapter's calendar
     */
    public function getEventComponent()
    {
        $components = $this->iCalEvent->getComponents();

        foreach ($components as $component) {
            if ($component->name == "VEVENT") {
                return $component;
            }
        }
    }

    public function setICalEvent($iCalEvent)
    {
        $this->iCalEvent = VObject\Reader::read($iCalEvent);
    }

    public function getOXPProperties()
    {
        return $this->oxpProperties;
    }

    public function setOXPProperties(array $oxpProperties)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($oxpProperties)) {
            return;
        }

        $this->oxpProperties = $oxpProperties;
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
        $this->oxpProperties = [];
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

    public function setDTStart($start, $timeZone, $showWithoutTime = null)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($start)) {
            return;
        }


        // The following checks for the right DateTime Format and creates a new DateTime in the jmap format..
        // JSCal start properties should not be UTC Date Time values and instead have the timeZone property
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

        // If the underlying JSCalendar event is a full day event, it contains the "showWithoutTime" property,
        // which can either be null, false, or true. The iCalendar counterpart for this is an event for which the
        // DTSTART and DTEND properties are DATE values instead of DATETIME values. An example of this would be
        //
        // [...]
        // DTSTART;VALUE=DATE:20211202
        // DTEND;VALUE=DATE:20211203
        // [...]
        //
        // References:
        // https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.2.4
        // https://www.rfc-editor.org/rfc/rfc8984.html#name-showwithouttime
        // https://www.ietf.org/archive/id/draft-ietf-calext-jscalendar-icalendar-07.html
        if ($showWithoutTime) {
            $this->iCalEvent->VEVENT->DTSTART["VALUE"] = "DATE";
        }
    }

    public function setShowWithoutTime($showWithoutTime)
    {
        if ($showWithoutTime !== true) {
            return;
        }

        $dtStart = $this->iCalEvent->VEVENT->DTSTART;
        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return;
        }

    // If DTSTART already has VALUE=DATE, don't add SHOW-WITHOUT-TIME
        if (isset($dtStart['VALUE']) && strtoupper((string) $dtStart['VALUE']) === 'DATE') {
            return;
        }

    // Remove existing SHOW-WITHOUT-TIME property if present
        $existing = $this->iCalEvent->VEVENT->{'SHOW-WITHOUT-TIME'};
        if ($existing) {
            $this->iCalEvent->VEVENT->remove($existing);
        }

    // Add SHOW-WITHOUT-TIME property
        $this->iCalEvent->VEVENT->add('SHOW-WITHOUT-TIME', 'TRUE');

    // Set the VALUE=BOOLEAN parameter
        $this->iCalEvent->VEVENT->{'SHOW-WITHOUT-TIME'}['VALUE'] = 'BOOLEAN';
    }

    public function getShowWithoutTime()
    {
        $dtStart = $this->iCalEvent->VEVENT->DTSTART;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return null;
        }

        // DATE values convert to showWithoutTime=true
        if (isset($dtStart['VALUE']) && strtoupper((string) $dtStart['VALUE']) === 'DATE') {
            return true;
        }

        // Check for SHOW-WITHOUT-TIME property on timed events
        $showWithoutTime = $this->iCalEvent->VEVENT->{'SHOW-WITHOUT-TIME'};
        if (!AdapterUtil::isSetNotNullAndNotEmpty($showWithoutTime)) {
            return false; // Default is false for timed events without the property
        }

        $value = strtoupper(trim((string) $showWithoutTime));

        if ($value === 'TRUE') {
            return true;
        } elseif ($value === 'FALSE') {
            return false;
        }

        $this->logger->warning("Invalid SHOW-WITHOUT-TIME value: " . $value);
        return false;
    }

    public function setDTEnd($start, $duration, $timeZone, $showWithoutTime = null)
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

        // See setDTStart for an explanation.
        if ($showWithoutTime) {
            $this->iCalEvent->VEVENT->DTEND["VALUE"] = "DATE";
        }
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

        if (!$iCalendarUpdatedDateTime) {
            return;
        }

        // DTSTAMP
        if (isset($this->iCalEvent->VEVENT->DTSTAMP)) {
            $this->iCalEvent->VEVENT->DTSTAMP = $iCalendarUpdatedDateTime;
        } else {
            $this->iCalEvent->VEVENT->add('DTSTAMP', $iCalendarUpdatedDateTime);
        }

        // LAST-MODIFIED
        if (isset($this->iCalEvent->VEVENT->{'LAST-MODIFIED'})) {
            $this->iCalEvent->VEVENT->{'LAST-MODIFIED'} = $iCalendarUpdatedDateTime;
        } else {
            $this->iCalEvent->VEVENT->add('LAST-MODIFIED', $iCalendarUpdatedDateTime);
        }
    }
    public function getUid()
    {
        $uid = $this->iCalEvent->VEVENT->UID;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($uid)) {
            $uid =  uniqid("", true) . ".OpenXPort";
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

        foreach ($categories as $cat) {
            foreach ($cat->getParts() as $value) {
                $value = trim($value);
                if (AdapterUtil::isSetNotNullAndNotEmpty($value)) {
                    $jmapKeyWords[$value] = true;
                }
            }
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

        $this->iCalEvent->VEVENT->add("CATEGORIES", $categories);
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

        if (sizeof($locations) > 1) {
            throw new \Exception(
                "Event contains more than one location. This is not supported and the mapping will be aborted."
            );
        }

        // Only use the first location and add iCal escaping to it.
        $locationICalEscaped = addcslashes(stripslashes(reset($locations)->getName()), "[,;]");

        $this->iCalEvent->VEVENT->add("LOCATION", $locationICalEscaped);
    }

    public function getFreeBusy()
    {
        $freeBusy = $this->iCalEvent->VEVENT->TRANSP;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($freeBusy)) {
            return null;
        }

        // "free" is supposed to be the default value.
        return $freeBusy->getValue() == 'OPAQUE' ? 'busy' : 'free';
    }

    public function setFreeBusy($freeBusy)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($freeBusy)) {
            return;
        }

        // "OPAQUE" is supposed to be the default value.
        $iCalFreeBusy = $freeBusy == 'free' ? 'TRANSPARENT' : 'OPAQUE';

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

            // Only DISPLAY and EMAIL have direct JSCalendar action values.
            if (strcmp($action, "DISPLAY") === 0) {
                $alert->setAction("display");
            } elseif (strcmp($action, "EMAIL") === 0) {
                $alert->setAction("email");
            }

            $acknowledged = $alarm->ACKNOWLEDGED;
            if (AdapterUtil::isSetNotNullAndNotEmpty($acknowledged)) {
                $acknowledgedDateTime = $acknowledged->getDateTime();
                $alert->setAcknowledged(date_format($acknowledgedDateTime, "Y-m-d\TH:i:s\Z"));
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

            $jsCalAction = is_object($alert)
                ? $alert->getAction()
                : "display";

            // Set the ACTION property. Only "EMAIL" and "DISPLAY" are directly mapped.
            if (strcmp($jsCalAction, "email") === 0) {
                $iCalAction = "EMAIL";
            } elseif (strcmp($jsCalAction, "display") === 0 || is_null($jsCalAction)) {
                $iCalAction = "DISPLAY";
            } else {
                $iCalAction = "DISPLAY";
            }

            $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("ACTION", $iCalAction);

            $jsCalTrigger = is_object($alert)
                ? $alert->getTrigger()
                : null;

            if (is_null($jsCalTrigger)) {
                $alarmIndex++;
                continue;
            }

            $triggerType = is_object($jsCalTrigger)
                ? $jsCalTrigger->getType()
                : null;

            // Set the TRIGGER property.
            if (strcmp($triggerType, "OffsetTrigger") === 0) {
                $triggerValue = is_object($jsCalTrigger)
                    ? $jsCalTrigger->getOffset()
                    : null;

                // An offset trigger can contain a relativeTo parameter, which needs to be mapped as well.
                $relativeTo = is_object($jsCalTrigger)
                    ? $jsCalTrigger->getRelativeTo()
                    : null;

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
                $whenValue = is_object($jsCalTrigger)
                    ? $jsCalTrigger->getWhen()
                    : null;

                $triggerValue = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $whenValue);

                // If the date time is false, it was probably not in UTC, which is the standard for both formats.
                // Log and skip to the next alert.
                if (!$triggerValue) {
                    // TODO: Add the alert to the event as some sort of custom alert if this happens.
                    $this->logger->error(
                        "Unable to create date time for absolute trigger from value: "
                        . $whenValue
                    );

                    continue;
                }

                $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("TRIGGER", $triggerValue, ["VALUE" => "DATE-TIME"]);
            } else {
                // The trigger type is not one of the two known ones.
                $this->logger->error("Unable to created trigger value from trigger type: " . $triggerType);

                continue;
            }

            $acknowledged = is_object($alert)
                ? $alert->getAcknowledged()
                : null;

            if (AdapterUtil::isSetNotNullAndNotEmpty($acknowledged)) {
                $acknowledgedDateTime = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $acknowledged);
                if ($acknowledgedDateTime) {
                    $this->iCalEvent->VEVENT->VALARM[$alarmIndex]->add("ACKNOWLEDGED", $acknowledgedDateTime);
                }
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
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue) && $jsCalValue != 1) {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapIntervalToICalInterval($jsCalValue);

                array_push($iCalRRule, "INTERVAL=" . $iCalValue);
            }

            $jsCalValue = $rec->getRscale();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue) && strtolower($jsCalValue) != 'gregorian') {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapRScaleToICalRScale($jsCalValue);

                array_push($iCalRRule, "RSCALE=" . $iCalValue);
            }

            $jsCalValue = $rec->getSkip();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue) && strtolower($jsCalValue) != 'omit') {
                $iCalValue = JSCalendarICalendarAdapterUtil::
                    convertFromJmapSkipToICalSkip($jsCalValue);

                array_push($iCalRRule, "SKIP=" . $iCalValue);
            }

            $jsCalValue = $rec->getFirstDayOfWeek();
            if (AdapterUtil::isSetNotNullAndNotEmpty($jsCalValue) && strtoupper($jsCalValue) != 'MO') {
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

    public function setRecurrenceId($recurrenceId, $timeZone, $showWithoutTime)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceId)) {
            return;
        }

        $jmapFormat = "Y-m-d\TH:i:s";
        $iCalFormat = $timeZone == "Etc/UTC" ? "Ymd\THis\Z" : "Ymd\THis";

        // Checks whether the event also has a timezone connected to it.
        if (is_null($timeZone) || $timeZone == "Etc/UTC") {
            $recurrenceIdDateTime = new \DateTime($recurrenceId);
        } else {
            $recurrenceIdDateTime = new \DateTime($recurrenceId, new \DateTimeZone($timeZone));
        }


        $this->iCalEvent->VEVENT->add("RECURRENCE-ID", $recurrenceIdDateTime);

        if ($showWithoutTime) {
            $this->iCalEvent->VEVENT->{"RECURRENCE-ID"}["VALUE"] = "DATE";
        }
    }

    public function getExDates()
    {
        $exDates = $this->iCalEvent->VEVENT->EXDATE;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($exDates)) {
            return null;
        }

        $excludedRecurrenceIds = [];

        foreach ($exDates as $exDateProperty) {
            foreach ($exDateProperty->getDateTimes() as $dateTime) {
                $excludedRecurrenceIds[] = $dateTime->format("Y-m-d\TH:i:s");
            }
        }

        return $excludedRecurrenceIds;
    }

    public function setExDate($recurrenceId)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceId)) {
            return;
        }

        $dtStart = $this->iCalEvent->VEVENT->DTSTART;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return;
        }

        $exDateFormat = null;
        $timeZone = null;

        $dtStartString = $dtStart->getValue();

        if (isset($dtStart['VALUE']) && strtoupper((string) $dtStart['VALUE']) === 'DATE') {
            $exDateFormat = "Ymd";
        } elseif (strpos($dtStartString, "Z") !== false) {
            $exDateFormat = "Ymd\THis\Z";
        } else {
            $exDateFormat = "Ymd\THis";
            $timeZone = new \DateTimeZone($this->getTimeZone());
        }

        $exDateString = AdapterUtil::parseDateTime($recurrenceId, "Y-m-d\TH:i:s", $exDateFormat);

        $exDate = new \DateTimeImmutable($exDateString, $timeZone);

        if (!AdapterUtil::isSetNotNullAndNotEmpty($this->iCalEvent->VEVENT->EXDATE)) {
            $this->iCalEvent->VEVENT->add("EXDATE", $exDate);

            if ($exDateFormat === "Ymd") {
                $this->iCalEvent->VEVENT->EXDATE["VALUE"] = "DATE";
            }

            return;
        }

        $setExDates = [];

        foreach ($this->iCalEvent->VEVENT->EXDATE->getDateTimes() as $setExDate) {
            $setExDates[] = $setExDate;
        }

        array_push($setExDates, $exDate);

        $this->iCalEvent->VEVENT->EXDATE = $setExDates;
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

                case "DELEGATED-TO":
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
            $parameters["CUTYPE"] = JSCalendarICalendarAdapterUtil
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

    public function getPriority()
    {
        $priority = $this->iCalEvent->VEVENT->PRIORITY;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($priority)) {
            return null;
        }

        return $priority->getValue();
    }

    public function setPriority($priority)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($priority)) {
            return;
        }

        $this->iCalEvent->VEVENT->add("PRIORITY", $priority);
    }

    public function getAttachments()
    {
        $attachments = $this->iCalEvent->VEVENT->ATTACH;


        if (!AdapterUtil::isSetNotNullAndNotEmpty($attachments)) {
            return null;
        }

        $links = [];

        foreach ($attachments as $attach) {
            $link = new Link();

            $link->setType("Link");
            $link->setRel("enclosure");

            // Check if the ATTACH property has a binary or uri (= non-binary) value.
            // Currently done by checking the "VALUE" parameter.
            if (
                array_key_exists("VALUE", $attach->parameters) &&
                $attach->parameters["VALUE"] == "BINARY"
            ) {
                $this->fillLinkWithBinaryValue($link, $attach);
            } else {
                $this->fillLinkWithUriValue($link, $attach);
            }

            if (
                array_key_exists("FMTTYPE", $attach->parameters) &&
                AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["FMTTYPE"])
            ) {
                $link->setContentType($attach->parameters["FMTTYPE"]->getValue());
            }

            if (
                array_key_exists("LABEL", $attach->parameters) &&
                AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["LABEL"])
            ) {
                $link->setTitle($attach->parameters["LABEL"]->getValue());
            } elseif (
                array_key_exists("FILENAME", $attach->parameters) &&
                AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["FILENAME"])
            ) {
                // Backwards compatibility for older ATTACH usage in existing fixtures/tests.
                $link->setTitle($attach->parameters["FILENAME"]->getValue());
            }

            if (
                array_key_exists("SIZE", $attach->parameters) &&
                AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["SIZE"])
            ) {
                $link->setSize((int) $attach->parameters["SIZE"]->getValue());
            }

            array_push($links, $link);
        }

        return $links;
    }

    private function fillLinkWithBinaryValue($link, $attach)
    {
        // Currently expect all binary attachments to be encoded in base64.
        if (
            !AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["ENCODING"]) ||
            $attach->parameters["ENCODING"]->getValue() != "BASE64"
        ) {
            throw new \Exception(sprintf(
                "ATTACH encoding is not recognized as base64 for event: %s. Mapping will be aborted.",
                $this->iCalEvent->VEVENT->UID->getValue()
            ));
        }
        // Use getRawMimeDirValue() instead of getValue()
        // in order to not get the decoded value for binary
        // attachments.
        $binaryValue = $attach->getRawMimeDirValue();

        $mediaType = "";

        // The mediatype for Data URLs is supposed to default to
        // "text/plain;charset=US-ASCII" if not set.
        // https://www.rfc-editor.org/rfc/inline-errata/rfc2397.html
        if (
            array_key_exists("FMTTYPE", $attach->parameters) &&
            AdapterUtil::isSetNotNullAndNotEmpty($attach->parameters["FMTTYPE"])
        ) {
            $mediaType = $attach->parameters["FMTTYPE"]->getValue();
        } else {
            $mediaType = "text/plain;charset=US-ASCII";
        }

        $dataUrl = "data:$mediaType;base64,$binaryValue";

        $link->setHref($dataUrl);
    }

    private function fillLinkWithUriValue($link, $attach)
    {
        $link->setHref($attach->getValue());
    }

    public function setAttachments($links)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($links)) {
            return;
        }

        // Loop through each Link, decide whether it's binary or a uri
        // and add it to the VEVENT accordingly.
        foreach ($links as $link) {
            $value = $link->getHref();

            if ($value == "") {
                $this->logger->notice(sprintf(
                    "Link contains no HREF value and cannot be mapped to an attachment: %s",
                    json_encode($link)
                ));

                continue;
            }

            $data = [];

            // If the value for this prop starts with "data", it should be a data URL,
            // which we can translate to a binary ATTACH value. Otherwise assume that
            // it is a regular URI.
            if (substr($value, 0, 5) == "data:") {
                // "," is not a part of the base64 charset and not for
                // the meta-data part ahead of the binary part  either.
                // So this should not cause any issues with the string
                // being split into more than two parts.
                $splitValue = explode(",", $value);

                // Value needs to be decoded first, since it will be encoded when adding
                // it to the event and there is no way of changing this in behavior in
                // sabre/vobject.
                $data["value"] = base64_decode($splitValue[1]);
                #fwrite(STDERR, print_r($splitValue[1], true));
                #fwrite(STDERR, print_r($data["value"], true));
                #fwrite(STDERR, print_r("\n", true));


                // Any info like mediatype or other parameters which we might need to
                // create the iCal attachment.
                $data["metaData"] = substr($splitValue[0], 5);

                $data["parameters"] = [
                    "ENCODING" => "BASE64",
                    "VALUE" => "BINARY"
                ];
            } else {
                $data["value"] = $value;

                $data["parameters"] = [];
            }

            if (AdapterUtil::isSetNotNullAndNotEmpty($link->getContentType())) {
                $data["parameters"]["FMTTYPE"] = $link->getContentType();
            } elseif (isset($data["metaData"])) {
                // Try to manually extract the mediatype from the href string.
                $this->logger->notice(sprintf(
                    "Link object does not contain content-type. Extracting media-type from data URL. Link %s:",
                    json_encode($link)
                ));

                $mediaType = JSCalendarICalendarAdapterUtil::extractMediaTypeFromDataUrlMetaDataString(
                    $data["metaData"]
                );

                if ($mediaType) {
                    $data["parameters"]["FMTTYPE"] = $mediaType;

                    $this->logger->notice(sprintf(
                        "Succesfully extracted the mediatype %s from data URL.",
                        $mediaType
                    ));
                }
            }

            if (AdapterUtil::isSetNotNullAndNotEmpty($link->getTitle())) {
                $data["parameters"]["LABEL"] = $link->getTitle();
            }

            if (AdapterUtil::isSetNotNullAndNotEmpty($link->getSize())) {
                $data["parameters"]["SIZE"] = (string) $link->getSize();
            }

            $this->iCalEvent->VEVENT->add(
                "ATTACH",
                $data["value"],
                $data["parameters"]
            );

            /* TODO: check if necessary v v v
            if (empty($data["parameters"])) {
                $this->iCalEvent->VEVENT->add(
                    "ATTACH",
                    $data["value"]
                );

            } else {
                $this->iCalEvent->VEVENT->add(
                    "ATTACH",
                    $data["value"],
                    $data["parameters"]
                );
            }
            */
        }
    }
    public function getVirtualLocations()
    {
        $conferences = $this->iCalEvent->VEVENT->CONFERENCE;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($conferences)) {
            return null;
        }

        $virtualLocations = [];
        $key = 1;

        foreach ($conferences as $conference) {
            $virtualLocation = new VirtualLocation();
            $virtualLocation->setType("VirtualLocation");

            $uri = $conference->getValue();
            if (AdapterUtil::isSetNotNullAndNotEmpty($uri)) {
                $virtualLocation->setUri($uri);
            }

            if (isset($conference['LABEL'])) {
                $virtualLocation->setName($conference['LABEL']->getValue());
            }

            if (isset($conference['FEATURE'])) {
                $features = [];
                foreach ($conference['FEATURE']->getParts() as $feature) {
                    $features[strtolower($feature)] = true;
                }
                $virtualLocation->setFeatures($features);
            }

            $virtualLocations[$key] = $virtualLocation;
            $key++;
        }

        return $virtualLocations;
    }

    public function setVirtualLocations($virtualLocations)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($virtualLocations)) {
            return;
        }

        foreach ($virtualLocations as $virtualLocation) {
            $uri = $virtualLocation->getUri();

            if (!AdapterUtil::isSetNotNullAndNotEmpty($uri)) {
                continue;
            }

            $parameters = [];

            $name = $virtualLocation->getName();
            if (AdapterUtil::isSetNotNullAndNotEmpty($name)) {
                $parameters['LABEL'] = $name;
            }

            $features = $virtualLocation->getFeatures();
            if (AdapterUtil::isSetNotNullAndNotEmpty($features)) {
                $featureList = [];
                foreach ($features as $feature => $enabled) {
                    if ($enabled) {
                        $featureList[] = strtoupper($feature);
                    }
                }
                if (!empty($featureList)) {
                    $parameters['FEATURE'] = $featureList;
                }
            }

            $this->iCalEvent->VEVENT->add('CONFERENCE', $uri, $parameters);
        }
    }

    /**
     * Get all RDATE values of the current VEVENT as JSCalendar recurrence override keys.
     *
     * An RDATE property with value type DATE or DATE-TIME converts to an empty PatchObject
     * entry in "recurrenceOverrides". This method returns the converted recurrence ids.
     *
     * DATE values are returned in "Y-m-d" format.
     * DATE-TIME values are returned in "Y-m-d\TH:i:s" format.
     *
     * @return array<int,string>|null
     */
    public function getRDates()
    {
        $rDates = $this->iCalEvent->VEVENT->RDATE;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($rDates)) {
            return null;
        }

        $includedRecurrenceIds = [];

        foreach ($rDates as $rDateProperty) {
            $isDateValue = isset($rDateProperty['VALUE'])
                && strtoupper((string) $rDateProperty['VALUE']) === 'DATE';

            foreach ($rDateProperty->getDateTimes() as $dateTime) {
                $includedRecurrenceIds[] = $isDateValue
                    ? $dateTime->format("Y-m-d")
                    : $dateTime->format("Y-m-d\TH:i:s");
            }
        }

        return $includedRecurrenceIds;
    }

    /**
     * Add a recurrence instance as an RDATE property to the current VEVENT.
     *
     * Per the RFC5545, an empty PatchObject in
     * "recurrenceOverrides" converts to an RDATE property.
     *
     * @param string $recurrenceId
     *
     * @return void
     */
    public function setRDate($recurrenceId)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($recurrenceId)) {
            return;
        }

        $dtStart = $this->iCalEvent->VEVENT->DTSTART;

        if (!AdapterUtil::isSetNotNullAndNotEmpty($dtStart)) {
            return;
        }

        $rDateFormat = null;
        $inputFormat = null;
        $timeZone = null;

        $dtStartString = $dtStart->getValue();

        if (isset($dtStart['VALUE']) && strtoupper((string) $dtStart['VALUE']) === 'DATE') {
            $rDateFormat = "Ymd";
            $inputFormat = "Y-m-d";
        } elseif (strpos($dtStartString, "Z") !== false) {
            $rDateFormat = "Ymd\THis\Z";
            $inputFormat = "Y-m-d\TH:i:s";
        } else {
            $rDateFormat = "Ymd\THis";
            $inputFormat = "Y-m-d\TH:i:s";

            $timeZoneName = $this->getTimeZone();
            if (!is_null($timeZoneName)) {
                $timeZone = new \DateTimeZone($timeZoneName);
            }
        }

        $rDateString = AdapterUtil::parseDateTime($recurrenceId, $inputFormat, $rDateFormat);
        $rDate = new \DateTimeImmutable($rDateString, $timeZone);

        if (!AdapterUtil::isSetNotNullAndNotEmpty($this->iCalEvent->VEVENT->RDATE)) {
            $this->iCalEvent->VEVENT->add("RDATE", $rDate);

            if ($rDateFormat === "Ymd") {
                $this->iCalEvent->VEVENT->RDATE["VALUE"] = "DATE";
            }

            return;
        }

        $setRDates = [];

        foreach ($this->iCalEvent->VEVENT->RDATE->getDateTimes() as $setRDate) {
            $setRDates[] = $setRDate;
        }

        $setRDates[] = $rDate;

        $this->iCalEvent->VEVENT->RDATE = $setRDates;
    }
}
