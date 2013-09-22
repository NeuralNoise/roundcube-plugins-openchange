<?php
class OCParsing
{
    public static $fullEventProperties = array(
        PidTagOriginalSubject, PidTagHasAttachments, PidLidBusyStatus, PidLidLocation,
        PidLidAppointmentStartWhole, PidLidAppointmentEndWhole, PidNameKeywords,
        PidLidAppointmentSubType, PidTagLastModificationTime, PidLidAllAttendeesString,
        PidTagBody, PidTagSensitivity, PidTagPriority,
        //PidLidAppointmentRecur,
    );

    //TODO: Qué hacer con PidLidCommoni{Start/End} & PidTag{Start/End}Date
    public static $eventTranslationTable = array(
        //PidLidResponseStatus,PidLidRecurring
        PidTagOriginalSubject       => array('field' => 'title'),
        PidTagHasAttachments        => array('field' => False),
        PidLidBusyStatus            => array('field' => 'free_busy', 'parsingFunc' => 'parseBusy'),
        PidLidLocation              => array('field' => 'location'),
        PidLidAppointmentStartWhole => array('field' => 'start', 'parsingFunc' => 'parseDate'),
        PidLidAppointmentEndWhole   => array('field' => 'end', 'parsingFunc' => 'parseDate'),
        PidNameKeywords             => array('field' => 'categories'), //ptypmultiplestring
        PidLidAppointmentSubType    => array('field' => 'allday'),
        PidTagLastModificationTime  => array('field' => 'changed', 'parsingFunc' => 'parseDate'),
        PidLidAllAttendeesString    => array('field' => 'attendees'), //this will need some parsing
        PidLidAppointmentRecur      => array('field' => 'recurrence'), //binary, parsing needed, PidLidRecurrenceType, PidLidRecurrencePattern
        PidTagBody                  => array('field' => 'description', 'parsingFunc' => 'removeBrackets'),
        PidTagSensitivity           => array('field' => 'sensivity'),
        PidTagPriority              => array('field' => 'priority'),
    );

    private static $busyTranslation = array(
        0 => 'Free',
        1 => 'Tentative',
        2 => 'Busy',
        3 => 'Out of Office',
    );

    /**
     * Returns a simple message ID from a composed one
     *
     * @param string $complexId The id with the form "folderID/messageID"
     * @param bool $convertToHexString If we want to return a "0xhexnumber" string
     *
     * @return string The hex string of the messageID
     */
    public static function complex_id_to_single($complexId, $convertToHexString=true)
    {
        $ids = explode("/", $complexId);

        if ($convertToHexString)
            $ids[1] = "0x" . $ids[1];

        return $ids[1];
    }

    public static function parseEventOc2Rc($event)
    {
        $result = array();

        foreach ($event as $prop => $value) {
            $key = self::parseOc2RcKey($prop);
            $value = self::parseOc2RcValue($prop, $value);

            $result[$key] = $value;
        }

        $result['end'] = self::correctAllDayEndDate($result);

        return $result;
    }

    private static function parseOc2RcKey($ocProp)
    {
        if (array_key_exists($ocProp, self::$eventTranslationTable)) {
            $rcubeEventProps = self::$eventTranslationTable[$ocProp];
            return $rcubeEventProps['field'];
        }

        return $ocProp;
    }

    private static function parseOc2RcValue($ocProp, $value)
    {
        if (array_key_exists($ocProp, self::$eventTranslationTable)) {
            $rcubeEventProps = self::$eventTranslationTable[$ocProp];
            if (array_key_exists('parsingFunc', $rcubeEventProps)) {
                $func = $rcubeEventProps['parsingFunc'] . "Oc2Rc";
                $value = call_user_func_array(array(self, $func), array($value));
            }
        }

        return $value;
    }

    /**
     * Converts a given date obtained from OChange stores to a DateTime object
     * used by RCube
     *
     * @param string $date The date we want to convert
     *
     * @return DateTime The converted date
     */
    private static function parseDateOc2Rc($date)
    {
        return new DateTime($date);
    }

    private static function parseBusyOc2Rc($state)
    {
        return self::$busyTranslation[$state];
    }

    private static function removeBracketsOc2Rc($description)
    {
        return ltrim($description, ')');
    }

    private static function correctAllDayEndDate($event)
    {
        if ($event['allday']) {
            return $event['end']->sub(date_interval_create_from_date_string('1 day'));
        }

        return $event['end'];
    }
}
?>
