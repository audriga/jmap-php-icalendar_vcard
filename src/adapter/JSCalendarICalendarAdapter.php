<?php

namespace OpenXPort\Adapter;

use Sabre\VObject\Component\VCalendar;
use OpenXPort\Util\Logger;
use Sabre\VObject;
use OpenXPort\Jmap\Calendar\Location;
use OpenXPort\Util\AdapterUtil;

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
        return $this->iCalEvent->VEVENT->SUMMARY;
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

        return (string)$description;

        // TODO: implement the unescaping mentioned in the ietf conversion standards.
        // https://www.ietf.org/archive/id/draft-ietf-calext-jscalendar-icalendar-07.html#name-description.
    }

    public function getCreated()
    {
        $created = $this->iCalEvent->VEVENT->CREATED;

        if (is_null($created)) {
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
        $dtStart = $this->iCalEvent->VEVENT->DTSTART->getDateTime();

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

        // Default value in jmap is 'PT0S'.
        if (is_null($end)) {
            return 'PT0S';
        }

        $dtStart = $start->getDateTime();
        $dtEnd = $end->getDateTime();

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
        if ($dtStart == null) {
            return null;
        }

        $timeZone = $dtStart->getDateTime()->getTimezone();

        // Check if there is a time zone connected to the DTSTART property
        if ($timeZone == null) {
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
        if (!is_null($lastModified)) {
            $dateUpdated = $lastModified->getDateTime();
        }

        if (!is_null($dTStamp)) {
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

        if (is_null($uid)) {
            $uid = uniqid("", true) . ".OpenXPort";
        }

        return $uid;
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

        if (is_null($prodId)) {
            return null;
        }

        return (string)$prodId;
    }

    public function getSequence()
    {
        $sequence = $this->iCalEvent->VEVENT->SEQUENCE;

        if (is_null($sequence)) {
            return null;
        }

        return $sequence;
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

    public function getCategories()
    {
        $categories = $this->iCalEvent->VEVENT->CATEGORIES;

        if (is_null($categories)) {
            return null;
        }

        $jmapKeyWords = [];

        $categoryValues = explode(",", $categories);

        foreach ($categoryValues as $cat) {
            $jmapKeyWords[$cat] = true;
        }

        return $jmapKeyWords;
    }


    public function getLocation()
    {
        $location = $this->iCalEvent->VEVENT->LOCATION;

        if (is_null($location)) {
            return null;
        }

        $jmapLocations = [];

        $jmapLocation = new Location();
        $jmapLocation->setType("Location");
        $jmapLocation->setName($location);

        $key = base64_encode($location);
        $jmapLocations["$key"] = $jmapLocation;

        return $jmapLocations;
    }

    public function getFreeBusy()
    {
        $freeBusy = $this->iCalEvent->VEVENT->TRANSP;

        return $freeBusy == 'OPAGUE' ? 'busy' : 'free';
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
}
