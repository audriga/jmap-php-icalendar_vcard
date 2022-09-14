<?php

namespace OpenXPort\Adapter;

use Sabre\VObject\Component\VCalendar;
use OpenXPort\Util\Logger;
use Sabre\VObject;
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

    public function getSummary() 
    {
        return $this->iCalEvent->VEVENT->SUMMARY; 
    }

    public function setSummary($summary)
    {
        $this->iCalEvent->VEVENT->add('SUMMARY', $summary);
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
            return null;
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
        }

        // The following checks for the right DateTime Format and creates a new DAteTime in the jmap format.
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
        if (!AdapterUtil::isSetNotNullAndNotEmpty($start) 
            || !AdapterUtil::isSetNotNullAndNotEmpty($duration)) {
            return null;
        }

        $interval = new \DateInterval($duration);

        // 'DTEND' must be strictly greater than 'DTSTART' if it is set.
        if ($interval->format("%y%m%d%h%i%s") == "000000") {
            return null;
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
        if ($end == null) {
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
        //TODO: implement me.
    }

    //TODO: this might need to be revamped to accomodate for scheduling and non-scheduling properties
    public function getUpdated()
    {
        // Get both the "LAST-MODIFIED" and "DTSTAMP" properties, as only one of them is converted into the "updated" jmap property.
        $lastModified = $this->iCalEvent->VEVENT->{'LAST-MODIFIED'};
        $dTStamp = $this->iCalEvent->VEVENT->DTSTAMP;
        $dateUpdated = null;

        // If one of the properties is set in the ics file, use that one.
        if(!is_null($lastModified)) {
            $lastModifiedDate = $lastModified->getDateTime();
            // If both are set, use the latest one.
            if (!is_null($dTStamp)) {
                $dTStampDate = $dTStamp->getDateTime();

                $dateUpdated = max($lastModifiedDate, $dTStampDate);
            } else {
                $dateUpdated = $lastModifiedDate;
            }
        } elseif (!is_null($dTStamp)) {
            $dateUpdated = $dTStamp->getDateTime();
        } else {
            return null;
        }

        $jmapUpdated = $dateUpdated->format("Y-m-d\TH:i:s\Z");

        return $jmapUpdated;
    }

    public function setUpdated($updated)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($updated)) {
            return null;
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

        if(is_null($uid)){
            return null;
        }

        return $uid;
    }

    public function setUid($uid)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($uid)) {
            return null;
        }

        // VObject adds a uid to new VEVENT objects which we will overwrite with the existing one.
        if (isset($this->iCalEvent->VEVENT->UID)) {
            $this->iCalEvent->VEVENT->UID = $uid;
        } else {
            $this->iCalEvent->VEVENT->add('UID', $uid);
        }

    }
}