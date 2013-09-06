<?php
class OCParsing
{
    public static $ocDate = "Mon Jun  1 18:00:00 2071 UTC";


    /**
     * Converts a given date obtained from OChange stores to a DateTime object
     * used by RCube
     *
     * @param string $date The date we want to convert
     *
     * @return DateTime The converted date
     */
    public static function parse_oc_date($date){
        return new DateTime($date);
    }

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

    public static $event_dict = array(
        'location'          => PidLidLocation,
        'categories'        => PidNameKeywords,
        'allday'            => PidLidAppointmentSubType,
        //TODO 'recurrence'        => PidLidIsRecurring,
        'start'             => array(PidLidClipStart,array('OCParsing', 'parse_oc_date')),
        'end'               => array(PidLidClipEnd,array('OCParsing', 'parse_oc_date')),
    );
}
var_dump(call_user_func_array(OCParsing::$event_dict['start'][1], array(OCParsing::$ocDate)));
?>
