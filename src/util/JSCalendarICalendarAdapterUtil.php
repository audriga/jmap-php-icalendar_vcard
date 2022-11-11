<?php

namespace OpenXPort\Util;

use OpenXPort\Jmap\Calendar\NDay;

/**
 * Utility class used by /../adapter/JSCalendarICalendarAdapter to convert very simple property values
 * between JMAP and iCal format.
 */
class JSCalendarICalendarAdapterUtil
{
    protected static $logger;

    public static function convertFromICalFreqToJmapFrequency($freq)
    {
        $possibleFreqValues = array("YEARLY", "MONTHLY", "WEEKLY", "DAILY", "HOURLY", "MINUTELY", "SECONDLY");

        // The values in jmap and iCal are the same, only in different cases.
        $jmapFrequency = !in_array($freq, $possibleFreqValues) ? null : strtolower($freq);

        return $jmapFrequency;
    }

    public static function convertFromJmapFrequencyToICalFreq($frequency)
    {
        $possibleFrequencyValues = array("yearly", "monthly", "weekly", "daily", "hourly", "minutely", "secondly");

        $iCalFreq = !in_array($frequency, $possibleFrequencyValues) ? null : strtoupper($frequency);

        return $iCalFreq;
    }

    public static function convertFromICalIntervalToJmapInterval($interval)
    {
        // 1 is the default jmap value.
        return !AdapterUtil::isSetNotNullAndNotEmpty($interval) ? 1 : (int)$interval;
    }

    public static function convertFromJmapIntervalToICalInterval($interval)
    {
        return !AdapterUtil::isSetNotNullAndNotEmpty($interval) ? null : (int)$interval;
    }

    public static function convertFromICalRScaleToJmapRScale($rScale)
    {
        // The value is simply converted to lowercase, if it exists.
        return !AdapterUtil::isSetNotNullAndNotEmpty($rScale) ? null : strtolower($rScale);
    }

    public static function convertFromJmapRScaleToICalRScale($rScale)
    {
        return !AdapterUtil::isSetNotNullAndNotEmpty($rScale) ? null : strtoupper($rScale);
    }

    public static function convertFromICalSkipToJmapSkip($skip)
    {
        $possibleSkipValues = array("OMIT", "BACKWARD", "FORWARD");

        $jmapSkip = !in_array($skip, $possibleSkipValues) ? null : strtolower($skip);

        return $jmapSkip;
    }

    public static function convertFromJmapSkipToICalSkip($skip)
    {
        $possibleSkipValues = array("omit", "backward", "forward");

        $iCalSkip = !in_array($skip, $possibleSkipValues) ? null : strtoupper($skip);

        return $iCalSkip;
    }

    public static function convertFromIcalWKSTToJmapFirstDayOfWeek($wkst)
    {
        $possibleWKSTValues = array("MO", "TU", "WE", "TH", "FR", "SA", "SU");

        $jmapFirstDayOfWeek = !in_array($wkst, $possibleWKSTValues) ? null : strtolower($wkst);

        return $jmapFirstDayOfWeek;
    }

    public static function convertFromJmapFirstDayOfWeekToICalWKST($firstDayofWeek)
    {
        $possibleFirstDayOfWeekValues = array("mo", "tu", "we", "th", "fr", "sa", "su");

        $iCalWKST = !in_array($firstDayofWeek, $possibleFirstDayOfWeekValues) ? null : strtoupper($firstDayofWeek);

        return $iCalWKST;
    }

    public static function convertFromICalByDayToJmapByDay($byDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byDay)) {
            return null;
        }

        $splitByDayArray = explode(",", $byDay);

        $jmapByDay = [];

        foreach ($splitByDayArray as $bd) {
            // Extract the info of the day from the iCal event.

            $byDayWeekDayString = null;
            $byDayWeekNumberString = null;

            if (!ctype_alpha($bd)) {
                $splitByDay = str_split($bd);
                $i = 0;

                if (strcmp($splitByDay[$i], "+") === 0) {
                    self::$logger = Logger::getInstance();
                    self::$logger->info("Encountered the character \"+\" at the beginning of the iCalendar BYDAY
                    property during processing of RRULE");

                    array_shift($splitByDay);
                }

                while (is_numeric($splitByDay[$i]) || strcmp($splitByDay[$i], "-") === 0) {
                    $i++;
                }

                $byDayWeekNumberString = substr(implode($splitByDay), 0, $i);
                $byDayWeekDayString = substr(implode($splitByDay), $i);
            } else {
                $byDayWeekDayString = $bd;
            }

            $jmapNDay = new NDay();
            $jmapNDay->setDay($byDayWeekDayString);
            if (!!AdapterUtil::isSetNotNullAndNotEmpty($byDayWeekNumberString) && isset($byDayWeekNumberString)) {
                $jmapNDay->setNthOfPeriod((int)$byDayWeekNumberString);
            }

            array_push($jmapByDay, $jmapNDay);
        }

        return $jmapNDay;
    }

    public static function convertFromJmapByDayToICalByDay($byDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byDay)) {
            return null;
        }

        $iCalByDay = [];

        foreach ($byDay as $bd) {
            $iCalByDayString = null;

            $day = $bd->day;
            $nthOfPeriod = $bd->nthOfPeriod;

            if (AdapterUtil::isSetNotNullAndNotEmpty($nthOfPeriod)) {
                $iCalByDayString = $iCalByDayString . (string)$nthOfPeriod;
            }

            if (AdapterUtil::isSetNotNullAndNotEmpty($day)) {
                $iCalByDayString = $iCalByDayString . strtoupper($day);
            }

            if (AdapterUtil::isSetNotNullAndNotEmpty($iCalByDayString)) {
                array_push($iCalByDay, $iCalByDayString);
            }
        }

        return implode(",", $iCalByDay);
    }

    public static function convertFromICalByMonthDayToJmapByMonthDay($byMonthDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMonthDay)) {
            return null;
        }

        $splitByMonthDay = explode(",", $byMonthDay);

        foreach ($splitByMonthDay as $split) {
            $split = (int)$split;
        }

        return $splitByMonthDay;
    }

    public static function convertFromJmapByMonthDayToICalByMonthDay($byMonthDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMonthDay)) {
            return null;
        }

        $joinedByMonthDay = implode(",", $byMonthDay);

        return $joinedByMonthDay;
    }

    public static function convertFromICalbyMonthToJmapByMonth($byMonth)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMonth)) {
            return null;
        }

        $splitByMonth = explode(",", $byMonth);

        return $splitByMonth;
    }

    public static function convertFromJmapByMonthToICalByMonth($byMonth)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMonth)) {
            return null;
        }

        $joinedByMonth = implode(",", $byMonth);

        return $joinedByMonth;
    }

    public static function convertFromICalByYearDayToJmapByYearDay($byYearDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byYearDay)) {
            return null;
        }

        $splitByYearDay = explode(",", $byYearDay);

        foreach ($splitByYearDay as $split) {
            $split = (int)$split;
        }

        return $splitByYearDay;
    }

    public static function convertFromJmapByYearDayToICalByYearDay($byYearDay)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byYearDay)) {
            return null;
        }

        $joinedByYearDay = implode(",", $byYearDay);

        return $joinedByYearDay;
    }

    public static function convertFromICalByWeekNoToJmapByWeekNo($byWeekNo)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byWeekNo)) {
            return null;
        }

        $splitByWeekNo = explode(",", $byWeekNo);

        foreach ($splitByWeekNo as $split) {
            $split = (int)$split;
        }

        return $splitByWeekNo;
    }

    public static function convertFromJmapByWeekNoToICalByWeekNo($byWeekNo)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byWeekNo)) {
            return null;
        }

        $joinedByWeekNo = implode(",", $byWeekNo);

        return $joinedByWeekNo;
    }

    public static function convertFromICalByHourToJmapByHour($byHour)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byHour)) {
            return null;
        }

        $splitByHour = explode(",", $byHour);

        foreach ($splitByHour as $split) {
            $split = (int)$split;
        }

        return $splitByHour;
    }

    public static function convertFromJmapByHourToICalByHour($byHour)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byHour)) {
            return null;
        }

        $joinedByHour = implode(",", $byHour);

        return $joinedByHour;
    }

    public static function convertFromICalByMinuteToJmapByMinute($byMinute)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMinute)) {
            return null;
        }

        $splitByMinute = explode(",", $byMinute);

        foreach ($splitByMinute as $split) {
            $split = (int)$split;
        }

        return $splitByMinute;
    }

    public static function convertFromJmapByMinuteToICalByMinute($byMinute)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($byMinute)) {
            return null;
        }

        $joinedByMinute = implode(",", $byMinute);

        return $joinedByMinute;
    }

    public static function convertFromICalBySecondToJmapBySecond($bySecond)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($bySecond)) {
            return null;
        }

        $splitBySecond = explode(",", $bySecond);

        foreach ($splitBySecond as $split) {
            $split = (int)$split;
        }

        return $splitBySecond;
    }

    public static function convertFromJmapBySecondToICalBySecond($bySecond)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($bySecond)) {
            return null;
        }

        $joinedBySecond = implode(",", $bySecond);

        return $joinedBySecond;
    }

    public static function convertFromICalBySetPosToJmapBySetPosition($bySetPos)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($bySetPos)) {
            return null;
        }

        $splitBySetPosition = explode(",", $bySetPos);

        foreach ($splitBySetPosition as $split) {
            $split = (int)$split;
        }

        return $splitBySetPosition;
    }

    public static function convertFromJmapBySetPositionToICalBySetPosition($bySetPosition)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($bySetPosition)) {
            return null;
        }

        $joinedBySetPos = implode(",", $bySetPosition);

        return $joinedBySetPos;
    }

    public static function convertFromICalCountToJmapCount($count)
    {
        return !AdapterUtil::isSetNotNullAndNotEmpty($count) ? null : (int)$count;
    }

    public static function convertFromJmapCountToICalCount($count)
    {
        return !AdapterUtil::isSetNotNullAndNotEmpty($count) ? null : (string)$count;
    }

    public static function convertFromICalUntilToJmapUntil($until)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($until)) {
            return null;
        }

        $iCalUntilDate = \DateTime::createFromFormat("Ymd\THis\Z", $until);

        if ($iCalUntilDate === false) {
            if (self::$logger == null) {
                self::$logger == Logger::getInstance();
            }

            self::$logger->error("Unable to create date from iCal until: ", $until);

            return null;
        }

        $jmapUntil = date_format($iCalUntilDate, "Y-m-d\TH:i:s");

        return $jmapUntil;
    }

    public static function convertFromJmapUntilToICalUntil($until)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($until)) {
            return null;
        }

        $jmapUntilDate = \DateTime::createFromFormat("Y-m-d\TH:i:s", $until);

        if ($jmapUntilDate === false) {
            if (self::$logger == null) {
                self::$logger == Logger::getInstance();
            }

            self::$logger->error("Unable to create date from JMAP until: ", $until);

            return null;
        }

        $iCalUntil = date_format($jmapUntilDate, "Ymd\THis\Z");

        return $iCalUntil;
    }

    public static function convertFromICalCUTypeToJmapKind($cutype)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($cutype)) {
            return null;
        }

        $values = [
            "INDIVIDUAL" => "individual",
            "GROUP" => "group",
            "RESOURCE" => "resource",
            "ROOM" => "location",
            "UNKNOWN" => null
        ];

        if (array_key_exists($cutype, $values)) {
            return $values[$cutype];
        }

        return strtolower($cutype);
    }

    public static function converFromICalDelegatedFromToJmapDelegatedFrom($delegatedFrom)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($delegatedFrom)) {
            return null;
        }

        $jmapDelegatedFrom = explode(",", $delegatedFrom);

        return $jmapDelegatedFrom;
    }

    public static function converFromICalDelegatedToToJmapDelegatedTo($delegatedTo)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($delegatedTo)) {
            return null;
        }
        $jmapDelegatedTo = explode(",", $delegatedTo);

        return $jmapDelegatedTo;
    }

    public static function convertFromICalPartStatToJmapParticipationStatus($partStat)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($partStat) || $partStat == "NEEDS-ACTION") {
            return null;
        }

        return strtolower($partStat);
    }

    public static function convertFormICalRoleToJmapRoles($role)
    {
        if (!AdapterUtil::isSetNotNullAndNotEmpty($role)) {
            return null;
        }

        $values = [
            "CHAIR" => array("attendee", "chair"),
            "REQ-PARTICIPANT" => array("attendee"),
            "OPT-PARTICIPANT" => array("attendee", "optional"),
            "NON-PARTICIPANT" => array("informational")
        ];

        if (array_key_exists($role, $values)) {
            return $values[$role];
        }

        return array(strtolower($role));
    }

    public static function convertFromICalRSVPToJmapExpectReply($rsvp)
    {
        return $rsvp == "TRUE" ? true : null;
    }
}
