<?php
require_once(dirname(__FILE__) . '/../../../zentyal_lib/OpenchangeDebug.php');

/**
 * Convert data Openchange <-> Roundcube for tasklists
 *
 * This class decides which properties we are goint to ask to the OChange server.
 * How to parse them? (in both directions Openchange <-> Roundcube).
 * And some other arrangents or "fixes".
 * The parsing is done depending of the components of the array associated to a given
 * property. The parsing functions are at the end of the file (parsingFunc property
 * array component plus Oc2Rc/Rc2Oc).
 *
 * @version @package_version@
 * @author Miguel Julian <mjulian@zentyal.com>
 *
 * Copyright (C) 2013, Zentyal
 */

// Properties notes and doc
/*
The PidTagOrdinalMost property ([MS-OXPROPS] section 2.810) contains a positive number
whose negative is less than or equal to the value of the PidLidTaskOrdinal property (section
2.2.2.2.26) of all Task objects in the folder. This property MUST be updated to maintain this
condition whenever the PidLidTaskOrdinal property of any Task object in the folder changes in a
way that would violate the condition.
*/

/*
We might have also to set PidLidCommonStart PropertyPidLidCommonEnd properties as we set (start/due date
*/

require_once(dirname(__FILE__) . '/../../../zentyal_lib/OpenchangeDebug.php');

class TasksOCParsing
{
    public static $taskProperties = array(
        PidTagSubject, PidTagNormalizedSubject,
        PidTagLastModificationTime,
        PidTagSensitivity,
        PidTagBody, PidTagPriority,
        PidLidTaskStartDate, PidLidTaskDueDate,
        PidLidTaskState,
        PidLidTaskStatus,
        PidLidTaskComplete, PidLidTaskStatusOnComplete,
        PidLidTaskLastUpdate,
        PidTagImportance,
        PidLidToDoTitle, // using for tags
        //PidNameKeywords, //for tags? not present in contansts.c | not defined multiple-string-type
        //PidLidPercentComplete, //need to add support for float properties
    );

    public static $taskTranslationTable = array(
        PidTagNormalizedSubject     => array('field' => 'title'),
        PidTagLastModificationTime  => array('field' => 'changed', 'parsingFunc' => 'parseDate'),
        PidTagSensitivity           => array('field' => 'sensitivity', 'parsingFunc' => 'parseSensitivity'),
        PidTagBody                  => array('field' => 'description'),
        PidLidTaskStartDate         => array('field' => 'startdate', 'parsingFunc' => 'parseDate'),
        PidLidTaskDueDate           => array('field' => 'date', 'parsingFunc' => 'parseDate'),
        PidLidTaskStatus            => array('field' => 'status'), //extra no-roundcube needed
        PidLidTaskComplete          => array('field' => 'complete'), //extra no-roundcube needed
        PidTagImportance            => array('field' => 'flagged', 'parsingFunc' => 'parseFlag'), //must be int
        PidLidToDoTitle             => array('field' => 'tags', 'parsingFunc' => 'parseTags'),
        //PidLidTaskState             => array('field' => '
        //PidLidPercentComplete       => array('field' => 'complete', 'parsingFunc' => 'parseProgress'),
        //PidTagPriority              => array('field' => '
        //PidLidTaskStatusOnComplete  => array('field' => '
        //PidLidTaskLastUpdate        => array('field' => '
        //PidLidTaskEstimatedEffort   => array('field' => '
        //PidLidBusyStatus            => array('field' => 'free_busy', 'parsingFunc' => 'parseBusy'),
    );


    /*******************************************/
    /*        Openchange ---> Roundcube        */
    /*******************************************/
    public static function getFullTask($taskFolder, $taskMessage)
    {
        $task = array();

        $task = call_user_func_array(array($taskMessage, 'get'), self::$taskProperties);

        $taskId = $taskMessage->getID();
        $listId = $taskFolder->getID();
        $task['id'] = $taskId;
        $task['uid'] = $listId . '/' . $taskId;
        $task['list'] = $listId;

        return $task;
    }

    public static function parseTaskOc2Rc($ocTask)
    {
        $rcTask = array();

        foreach ($ocTask as $prop => $value) {
            $key = self::parseOc2RcKey($prop);
            $value = self::parseOc2RcValue($prop, $value);

            $rcTask[$key] = $value;
        }

        $rcTask = self::parseTaskDatesOc2Rc($rcTask);

        return $rcTask;
    }

    private static function parseOc2RcKey($ocProp)
    {
        if (array_key_exists($ocProp, self::$taskTranslationTable)) {
            $rcubeTaskProps = self::$taskTranslationTable[$ocProp];
            return $rcubeTaskProps['field'];
        }

        return $ocProp;
    }

    private static function parseOc2RcValue($ocProp, $value)
    {
        if (array_key_exists($ocProp, self::$taskTranslationTable)) {
            $rcubeTaskProps = self::$taskTranslationTable[$ocProp];
            if (array_key_exists('parsingFunc', $rcubeTaskProps)) {
                $func = $rcubeTaskProps['parsingFunc'] . "Oc2Rc";
                $value = call_user_func_array(array(self, $func), array($value));
            }
        }

        return $value;
    }

    private static function parseTaskDatesOc2Rc($task)
    {
        if (array_key_exists('date', $task)) {
            $task['time'] = $task['date']->format('H:i');
            $task['date'] = $task['date']->format('Y-m-d');
        }

        if (array_key_exists('startdate', $task)) {
            $task['starttime'] = $task['startdate']->format('H:i');
            $task['startdate'] = $task['startdate']->format('Y-m-d');
        }

        return $task;
    }



    /*******************************************/
    /*        Roundcube ---> Openchange        */
    /*******************************************/
    public static function createWithProperties($list, $properties)
    {
        return call_user_func_array(array($list, 'createMessage'), $properties);
    }

    public static function setProperties($fetchedTask, $properties)
    {
        $setResult = call_user_func_array(array($fetchedTask, 'set'), $properties);

        return $setResult;
    }

    public static function parseRc2OcTask($task)
    {
        $props = array();

        $task = self::prepareDateForParsingRc2Oc($task, 'startdate', 'starttime');
        $task = self::prepareDateForParsingRc2Oc($task, 'date', 'time');

        foreach ($task as $field => $value) {
            $ocProp = self::parseKeyRc2Oc($field);
            if ($ocProp) {
                $ocValue = self::parseValueRc2Oc($ocProp, $value);

                if (is_bool($ocValue) || $ocValue || $ocValue == 0){
                    $props = array_merge($props, array($ocProp, $ocValue));
                }
            }
        }

        return $props;
    }

    private static function parseKeyRc2Oc($rcField)
    {
        foreach (self::$taskTranslationTable as $ocProp => $rcProps) {
            if ($rcField == $rcProps['field'])
                return $ocProp;
        }

        return False;
    }

    private static function parseValueRc2Oc($ocProp, $value)
    {
        if (array_key_exists($ocProp, self::$taskTranslationTable)) {
            $rcubeTaskProps = self::$taskTranslationTable[$ocProp];
            if (array_key_exists('parsingFunc', $rcubeTaskProps)) {
                $func = $rcubeTaskProps['parsingFunc'] . "Rc2Oc";
                $value = call_user_func_array(array(self, $func), array($value));
            }

            return $value;
        }

        return False;
    }

    private static function prepareDateForParsingRc2Oc($task, $date, $time)
    {
        $debug = new OpenchangeDebug();

        if (isset($task[$date]) && $task[$date]){
            $debug->writeMessage($task[$date], 1, "DATE");
            $debug->writeMessage($task[$time], 1, "TIME");

            $newDate = new DateTime(NULL, $task['_timezone']);

            $explodedDate = explode('-', $task[$date]);
            $newDate->setDate($explodedDate[0], $explodedDate[1], $explodedDate[2]);

            if (isset($task[$time])) {
                $explodedTime = explode(':', $task[$time]);
                $newDate->setTime($explodedTime[0], $explodedTime[1]);
            } else
                $newDate->setTime(0,0);

            $task[$date] = $newDate;

            $debug->dumpVariable($task[$date], "The date from the task");
        } else
            unset($task[$date]);


        return $task;
    }


    /*******************************************/
    /*            Parsing functions            */
    /*******************************************/

    // OC => unix timestamp
    // RC => DateTime
    private static function parseDateOc2Rc($unixTimestampDate)
    {
        $parsedDate = new DateTime();
        $parsedDate->setTimestamp($unixTimestampDate);

        return $parsedDate;
    }

    private static function parseDateRc2Oc($date)
    {
        if ($date)
            return $date->getTimestamp();
    }


    // OC => 0|1|2|3  //0 = normal, 1 = personal, 2 = private , 3 = confidential
    // RC => 0|1|2,   // Event sensitivity (0=public, 1=private, 2=confidential)
    private static function parseSensitivityOc2Rc($ocSensitivity)
    {
        if ($ocSensitivity) return $ocSensitivity - 1;
        else return $ocSensitivity;
    }

    private static function parseSensitivityRc2Oc($rcSensitivity)
    {
        if ($rcSensitivity) return $rcSensitivity + 1;
        else return $rcSensitivity;
    }


    // OC => Integer (no-flag = 0, flagged = 1)
    // RC => Boolean (flagged ? true : false)
    private static function parseFlagOc2Rc($ocFlag)
    {
        return ($ocFlag ? TRUE : FALSE);
    }

    private static function parseFlagRc2Oc($rcFlag)
    {
        return ($rcFlag ? 1 : 0);
    }


    // OC => a string "comma" separated
    // RC => a array of strings
    private static function parseTagsOc2Rc($tagsString)
    {
        return explode(",", $tagsString);
    }

    private static function parseTagsRc2Oc($tagsArray)
    {
        return implode($tagsArray, ",");
    }
}
?>
