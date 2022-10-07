<?php

namespace OpenXPort\Util;

use OpenXPort\Jmap\Calendar\NDay;

class JSCalendarICalendarAdapterUtil
{
    protected static $logger;

    public static function convertFromICalFreqToJmapFrequency($freq)
    {
        if (is_null($freq)) {
            return;
        }

        $possibleFreqValues = array("YEARLY", "MONTHLY", "WEEKLY", "DAILY", "HOURLY", "MINUTELY", "SECONDLY");

        // The values in jmap and iCal are the same, only in different cases.
        $jmapFrequency = !in_array($freq, $possibleFreqValues) ? null : strtolower($freq);

        return $jmapFrequency;
    }

    public static function convertFromICalIntervalToJmapInterval($interval)
    {
        // 1 is the default jmap value.
        return is_null($interval) ? 1 : (int)$interval;
    }

    public static function convertFromIcalRScaleToJmapRScale($rScale)
    {
        // The value is simply converted to lowercase, if it exists.
        return is_null($rScale) ? null : strtolower($rScale);
    }

    public static function convertFromICalSkipToJmapSkip($skip)
    {
        $possibleSkipValues = array("OMIT", "BACKWARD", "FORWARD");

        $jmapSkip = !in_array($skip, $possibleSkipValues) ? null : strtolower($skip);

        return $jmapSkip;
    }

    public static function convertFromIcalWKSTToJmapFirstDayOfWeek($wkst)
    {
        $possibleWKSTValues = array("MO", "TU", "WE", "TH", "FR", "SA", "SU");

        $jmapFirstDayOfWeek = !in_array($wkst, $possibleWKSTValues) ? null : strtolower($wkst);

        return $jmapFirstDayOfWeek;
    }

    public static function convertFromICalByDayToJmapByDay($byDay)
    {
        if (is_null($byDay)) {
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
                $byWeekDayString = $bd;
            }

            $jmapNDay = new NDay();
            $jmapNDay->setDay($byDayWeekDayString);
            if (!is_null($byDayWeekNumberString) && isset($byDayWeekNumberString)) {
                $jmapNDay->setNthOfPeriod((int)$byDayWeekNumberString);
            }

            array_push($jmapByDay, $jmapNDay);
        }

        return $jmapNDay;
    }

    public static function convertFromICalByMonthDayToJmapByMonthDay($byMonthDay)
    {
        if (is_null($byMonthDay)) {
            return null;
        }

        $splitByMonthDay = explode(",", $byMonthDay);

        foreach($splitByMonthDay as $split) {
            $split = (int)$split;
        }

        return $splitByMonthDay;
    }

    public static function convertFromICalbyMonthToJmapByMonth($byMonth)
    {
        if (is_null($byMonth)) {
            return null;
        }

        $splitByMonth = explode(",", $byMonth);

        return $splitByMonth;
    }

    public static function convertFromICalByYearDayToJmapByYearDay($byYearDay)
    {
        if (is_null($byYearDay)) {
            return null;
        }

        $splitByYearDay = explode(",", $byYearDay);

        foreach ($splitByYearDay as $split) {
            $split = (int)$split;
        }

        return $splitByYearDay;
    }

    public static function convertFromICalByWeekNoToJmapByWeekNo($byWeekNo)
    {
        if (is_null($byWeekNo)) {
            return null;
        }

        $splitByWeekNo = explode(",", $byWeekNo);

        foreach ($splitByWeekNo as $split) {
            $split = (int)$split;
        }

        return $splitByWeekNo;
    }

    public static function convertFromICalByHourToJmapByHour($byHour)
    {
        if (is_null($byHour)) {
            return null;
        }

        $splitByHour = explode(",", $byHour);

        foreach ($splitByHour as $split) {
            $split = (int)$split;
        }

        return $splitByHour;
    }

    public static function convertFromICalByMinuteToJmapByMinute($byMinute)
    {
        if (is_null($byMinute)) {
            return null;
        }

        $splitByMinute = explode(",", $byMinute);

        foreach ($splitByMinute as $split) {
            $split = (int)$split;
        }

        return $splitByMinute;
    }

    public static function convertFromICalBySecondToJmapBySecond($bySecond)
    {
        if (is_null($bySecond)) {
            return null;
        }

        $splitBySecond = explode(",", $bySecond);

        foreach ($splitBySecond as $split) {
            $split = (int)$split;
        }

        return $splitBySecond;
    }

    public static function convertFromICalBySetPosToJmapBySetPosition($bySetPos)
    {
        if (is_null($bySetPos)) {
            return null;
        }

        $splitBySetPosition = explode(",", $bySetPos);

        foreach ($splitBySetPosition as $split) {
            $split = (int)$split;
        }

        return $splitBySetPosition;
    }

    public static function convertFromICalCountToJmapCount($count)
    {
        return is_null($count) ? null : (int)$count;
    }

    public static function convertFromICalUntilToJmapUntil($until)
    {
        if (is_null($until)) {
            return null;
        }

        $iCalUntilDate = \DateTime::createFromFormat("Ymd\THis\Z", $until);
        $jmapUntil = date_format($iCalUntilDate, "Y-m-d\TH:i:s");

        return $jmapUntil;
    }
}