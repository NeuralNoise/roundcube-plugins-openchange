<?php
/**
 * Zentyal Openchange driver for the Calendar plugin
 *
 * @version @package_version@
 * @author Miguel Julián <mjulian@zentyal.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2012, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


require_once(dirname(__FILE__) . '/../../lib/OCParsing.php');

class zentyal_openchange_driver extends calendar_driver
{
    const DB_DATE_FORMAT = 'Y-m-d H:i:s';
    //$event['start']->format(self::DB_DATE_FORMAT),

    // features this backend supports
    public $alarms = false;
    public $attendees = false;
    public $freebusy = true;
    public $attachments = false;
    public $alarm_types = array('DISPLAY');

    private $rc;
    private $cal;
    private $cache = array();
    private $calendars = array();
    private $calendar_ids = '';
    private $free_busy_map = array('free' => 0, 'busy' => 1, 'out-of-office' => 2, 'outofoffice' => 2, 'tentative' => 3);
    private $sensitivity_map = array('public' => 0, 'private' => 1, 'confidential' => 2);
    private $server_timezone;

    private $db_colors = 'colors';

    private $handle;
    private $debug = true;
    private $file = '/var/log/roundcube/my_debug.txt';
    private $oc_enabled = true;

    private $ocCalendar;
    private $mailbox;
    private $session;
    private $mapiProfile;
    private $mapi;
    private $username;

    private $events = array();
    private $createdEventId = false;

    /**
     * Default destructor
     */
    public function __destruct()
    {
        $this->debug_msg("\nError => Starting the destructor\n");
        if ($this->oc_enabled) {
            $this->debug_msg("Destructing MAPI objects\n");
            unset($this->ocCalendar);
            unset($this->mailbox);
            unset($this->session);
            unset($this->mapiProfile);
            unset($this->mapi);
        }
        $this->debug_msg("\nError => Exiting the destructor\n");
        fclose($this->handle);
    }

    /**
     * Default constructor
     */
    public function __construct($cal, $pathDB, $profileName)
    {
        $this->handle = fopen($this->file, 'a');
        $this->debug_msg("\nError => Starting the contructor\n");

        //Creating the OC binding
        /* TODO: Defensive code here */
        $this->debug_msg("Profile path: " . $pathDB . " | profile name: " . $profileName . "\n");

        $this->username = $profileName;
        $this->mapi = new MAPIProfileDB($pathDB);
        $this->mapiProfile = $this->mapi->getProfile($profileName);
        $this->session = $this->mapiProfile->logon();
        $this->mailbox = $this->session->mailbox();
        $this->ocCalendar = $this->mailbox->calendar();

        $this->cal = $cal;
        $this->rc = $cal->rc;
        $this->server_timezone = new DateTimeZone(date_default_timezone_get());

        // load library classes
        require_once($this->cal->home . '/lib/Horde_Date_Recurrence.php');

        // read database config
        $this->db_colors= $this->rc->config->get('db_table_colors', 'colors');

        $this->_read_calendars();
    }

    private function fetchEvents()
    {
        $this->debug_msg("\nStarting FetchEvents\n");
        $table = $this->ocCalendar->getMessageTable();
        $messages = $table->getMessages();

        $this->debug_msg("The number of events in the table is: " . count($messages) . "\n");

        foreach ($messages as $message) {
            $record = OCParsing::getFullEventProps($this->ocCalendar, $message);
            array_push($this->events, $record);
            $this->debug_msg("\nShowing a event:\n");
            $this->debug_msg(serialize($record) . "\n\n");
        }
        unset($message);
        unset($messages);
        unset($table);
    }

    private function debug_msg($message)
    {
        if ($this->debug)
            fwrite($this->handle, $message);
    }


    /**
     * Read available calendars for the current user and store them internally
     */
    private function _read_calendars()
    {
        $this->debug_msg("\nStarting _read_calendars\n");

        $cal_id = $this->ocCalendar->getID();
        $calendar['showalarms'] = false;
        $calendar['active'] = true;
        $calendar['name'] = $this->ocCalendar->getName();
        $calendar['id'] = $cal_id;
        $calendar['calendar_id'] = $cal_id;
        $calendar['user_id'] = $this->rc->user->ID;
        $calendar['readonly'] = false;

        $calendar_ids = array();
        array_push($calendar_ids, $calendar['id']);

        $this->calendars[$calendar['calendar_id']] = $calendar;
        $this->calendar_ids = join(',', $calendar_ids);

        $this->debug_msg("The calendar ids are: " . $this->calendar_ids . "\n");

        /* TODO: hidden calendars from config?
           $hidden = array_filter(explode(',', $this->rc->config->get('hidden_calendars', '')));
         */
    }

    /**
     * Get a list of available calendars from this source
     *
     * @param bool $active   Return only active calendars
     * @param bool $personal Return only personal calendars
     *
     * @return array List of calendars
     */
    public function list_calendars($active = false, $personal = false)
    {
        $this->debug_msg("\nStarting list_calendars\n");
        // attempt to create a default calendar for this user
        if (empty($this->calendars)) {
            $this->_read_calendars();
        }

        $calendars = $this->calendars;

        $this->debug_msg("All the calendars to show are: \n");
        foreach ($this->calendars as $key => $calc) {
            $this->debug_msg("For the key: " . $key . "\n");
            try {
                $this->debug_msg(serialize($calc) . "\n");
            } catch(Exception $e){
            }
        }

        // If there is no color assigned to a calendar, generate it
        $this->checkAndGetCalendarsColor();

        $this->debug_msg("How many cals: " . count($calendars) . "\n");

        // filter active calendars
        if ($active) {
            foreach ($calendars as $idx => $cal) {
                if (!$cal['active']) {
                    unset($calendars[$idx]);
                }
            }
        }
        $this->debug_msg("Ending list_calendars\n");

        return $calendars;
    }

    /**
     * Check each obtained calendars ($this->calendars),
     * if there is no color related with them, generate
     * and save it.
     */
    private function checkAndGetCalendarsColor()
    {
        $this->debug_msg("\nStarting checkAndGetCalendarsColor\n");
        // Look for the colors associated with the given calendars
        // 1st => Build the WHERE SQL clause
        $whereClause = "";
        foreach ($this->calendars as $calendar) {
            if ($whereClause)
                $whereClause .= " OR ";

            $whereClause .= "(calendar_id=\"" . $calendar['id'] . "\"" .
                    " AND user_id=" . $this->rc->user->ID . ")";
        }

        // 2nd => Query and fetch the results
        $query = "SELECT * FROM " . $this->db_colors. "
                WHERE " . $whereClause;
        $calendarColors= $this->rc->db->query($query);
//        $this->debug_msg("The colors query is: " . $query . "\n");
//        $this->debug_msg("The colors query where clause is: " . $whereClause . "\n");

        while ($calendarColors && ($arr = $this->rc->db->fetch_assoc($calendarColors))) {
            $colors[$arr['calendar_id']] = $arr;
        }

//        $this->debug_msg("The colors are: " . serialize($colors) . "\n");

        // If we have found a color, add it to the calendar, or generate it

        foreach ($this->calendars as $calendar) {
            if ($colors[$calendar['id']]) {
                $this->calendars[$calendar['id']]['color'] =
                    $colors[$calendar['id']]['color'];
            } else {
                $this->calendars[$calendar['id']]['color'] =
                    $this->createColor($calendar['id'], $calendar['user_id']);
            }
        }
    }

    /**
     * Create a new calendar assigned to the current user
     *
     * @param array Hash array with calendar properties
     *    name: Calendar name
     *   color: The color of the calendar
     * @return mixed ID of the calendar on success, False on error
     */
    public function create_calendar($prop)
    {
        $this->debug_msg("\nStarting create_calendar\n");

        // Creating a custom color entry for the calendar
        $calendarId = $this->rc->db->insert_id($this->db_calendars);
        $this->createColor($calendarId, $this->rc->user->ID, $prop['color']);
        $colorResult = $this->rc->db->insert_id($this->colors);

        if ($colorResult)
            $this->debug_msg("Color " . $colorResult . " has been created\n");

        if ($result)
            return $this->rc->db->insert_id($this->db_calendars);

        return false;
    }

    /**
     * Create a new color
     */
    private function createColor($calendar, $user, $color=NULL)
    {
        $this->debug_msg("\nStarting createColor\n");
        if (! $color) {
            $color = $this->generateColorFromId($calendar);
        }
        return $this->rc->db->query(
                        "INSERT INTO " . $this->db_colors . "
                        (calendar_id, user_id, color)
                        VALUES (?, ?, ?)",
                        $calendar,
                        $user,
                        $color
                        );
        return $color;
    }

    /**
     * Create a deterministic color from a given ID
     */
    private function generateColorFromId($id)
    {
        $idNumber = strval($id);
        $color = "";

        for ($i=1; $i <= 3; $i++) {
            $colorComponent = $idNumber % (100^$i);
            if ($colorComponent < 10)
                $colorComponent += 10;
            $color .= $colorComponent;
            $idNumber = round($idNumber / (100^$i));
        }

        $this->debug_msg("The generated color is: " . $color . "\n");

        return $color;
    }

    /**
     * Update properties of an existing calendar
     *
     * @see calendar_driver::edit_calendar()
     */
    public function edit_calendar($prop)
    {
        $this->debug_msg("\nStarting edit_calendar\n");

        $colorQuery = $this->rc->db->query(
                "UPDATE " . $this->db_colors . "
                SET    color=?
                WHERE calendar_id=?
                AND   user_id=?",
                $prop['color'],
                $prop['id'],
                $this->rc->user->ID
                );

        $colorResult = $this->rc->db->affected_rows($colorQuery);
        if ($colorResult)
            $this->debug_msg("Colors affected: " . $colorResult . "\n");

        return true;
    }

    /**
     * Set active/subscribed state of a calendar
     * Save a list of hidden calendars in user prefs
     *
     * @see calendar_driver::subscribe_calendar()
     */
    public function subscribe_calendar($prop)
    {
        $this->debug_msg("\nStarting subscribe_calendar\n");
        $hidden = array_flip(explode(',', $this->rc->config->get('hidden_calendars', '')));

        if ($prop['active'])
            unset($hidden[$prop['id']]);
        else
            $hidden[$prop['id']] = 1;

        return $this->rc->user->save_prefs(array('hidden_calendars' => join(',', array_keys($hidden))));
    }

    /**
     * Delete the given calendar with all its contents
     *
     * @see calendar_driver::remove_calendar()
     */
    public function remove_calendar($prop)
    {
        $this->debug_msg("\nStarting remove_calendar\n");
        if (!$this->calendars[$prop['id']])
            return false;

        $colorQuery = $this->rc->db->query(
                "DELETE FROM " . $this->db_colors . "
                WHERE calendar_id=?",
                $prop['id']
                );

        $colorResult = $this->rc->db->affected_rows($colorQuery);
        if ($colorResult)
            $this->debug_msg("Colors affected: " . $colorResult . "\n");

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Add a single event to the database
     *
     * @param array Hash array with event properties
     * @see calendar_driver::new_event()
     */
    public function new_event($event)
    {
        $this->debug_msg("\nStarting new_event\n");
        $this->debug_msg(serialize($event) . "\n");
        if (!$this->validate($event))
            return false;

        if (!empty($this->calendars)) {
            if ($event['calendar'] && !$this->calendars[$event['calendar']])
                return false;
            if (!$event['calendar'])
                $event['calendar'] = reset(array_keys($this->calendars));

            $properties = OCParsing::parseRc2OcEvent($event);

            $this->debug_msg("The properties we set:\n");
            ob_start(); var_dump($properties);
            $this->debug_msg(ob_get_clean());
            $newEevent = OCParsing::createWithProperties($this->ocCalendar, $properties);

            $event_id = $newEevent->getID();
            unset($newEevent);

            $this->createdEventId = $event_id;
            $this->debug_msg("The id returned is: " . $event_id . "\n");

            return $event_id;
        }

        return false;
    }

    /**
     * Update an event entry with the given data
     *
     * @param array Hash array with event properties
     * @see calendar_driver::edit_event()
     */
    public function edit_event($event)
    {
        $this->debug_msg("\nStarting edit_event\n");
        if (!empty($this->calendars)) {
            $update_master = false;
            $update_recurring = true;
            $old = $this->get_event($event);

            $event = OCParsing::checkAllDayConsistency($event);
            $properties = OCParsing::parseRc2OcEvent($event);
            $ocEvent = $this->ocCalendar->openMessage($old['id'], 1);
            $setResult = OcContactsParser::setProperties($ocEvent, $properties);
            $ocEvent->save();

            return True;
        }

        return false;
    }

    /**
     * Convert save data to be used in SQL statements
     */
    private function _save_preprocess($event)
    {
        $this->debug_msg("\nStarting _save_preprocess\n");
        // shift dates to server's timezone
        $event['start'] = clone $event['start'];
        $event['start']->setTimezone($this->server_timezone);
        $event['end'] = clone $event['end'];
        $event['end']->setTimezone($this->server_timezone);

        // compose vcalendar-style recurrencue rule from structured data
        $rrule = $event['recurrence'] ? libcalendaring::to_rrule($event['recurrence']) : '';
        $event['_recurrence'] = rtrim($rrule, ';');
        $event['free_busy'] = intval($this->free_busy_map[strtolower($event['free_busy'])]);
        $event['sensitivity'] = intval($this->sensitivity_map[strtolower($event['sensitivity'])]);

        if (isset($event['allday'])) {
            $event['all_day'] = $event['allday'] ? 1 : 0;
        }

        // compute absolute time to notify the user
        $event['notifyat'] = $this->_get_notification($event);

        // process event attendees
        $_attendees = '';
        foreach ((array)$event['attendees'] as $attendee) {
            if (!$attendee['name'] && !$attendee['email'])
                continue;
            $_attendees .= 'NAME="'.addcslashes($attendee['name'], '"') . '"' .
                ';STATUS=' . $attendee['status'].
                ';ROLE=' . $attendee['role'] .
                ';EMAIL=' . $attendee['email'] .
                "\n";
        }
        $event['attendees'] = rtrim($_attendees);

        return $event;
    }

    /**
     * Compute absolute time to notify the user
     */
    private function _get_notification($event)
    {
        $this->debug_msg("\nStarting _get_notification\n");
        if ($event['alarms'] && $event['start'] > new DateTime()) {
            $alarm = libcalendaring::get_next_alarm($event);

            if ($alarm['time'] && $alarm['action'] == 'DISPLAY')
                return date('Y-m-d H:i:s', $alarm['time']);
        }

        return null;
    }

    /**
     * Move a single event
     *
     * @param array Hash array with event properties
     * @see calendar_driver::move_event()
     */
    public function move_event($event)
    {
        $this->debug_msg("\nStarting more_event\n");
        ob_start();var_dump($event);
        $this->debug_msg(ob_get_clean() . "\n");
        // let edit_event() do all the magic
        return $this->edit_event($event + (array)$this->get_event($event));
    }

    /**
     * Resize a single event
     *
     * @param array Hash array with event properties
     * @see calendar_driver::resize_event()
     */
    public function resize_event($event)
    {
        $this->debug_msg("\nStarting resize_event\n");
        // let edit_event() do all the magic
        return $this->edit_event($event + (array)$this->get_event($event));
    }

    /**
     * Remove a single event from the database
     *
     * @param array   Hash array with event properties
     * @param boolean Remove record irreversible (@TODO)
     *
     * @see calendar_driver::remove_event()
     */
    public function remove_event($event, $force = true)
    {
        $this->debug_msg("\nStarting remove_event\n");
        ob_start();var_dump($event);
        $this->debug_msg(ob_get_clean() . "\n");
        if (!empty($this->calendars)) {
            $event += (array)$this->get_event($event);

            //At event["calendar"] there is the ID of the calendar, use?
            $deletingResult = OCParsing::deleteEvents($this->ocCalendar, $event['id']);

            return true;
        }

        return false;
    }

    /**
     * Return data of a specific event
     * @param mixed  Hash array with event properties or event UID
     * @param boolean Only search in writeable calendars (ignored)
     * @param boolean Only search in active calendars
     * @param boolean Only search in personal calendars (ignored)
     * @return array Hash array with event properties
     */
    public function get_event($event, $writeable = false, $active = false, $personal = false)
    {
        $this->debug_msg("\nStarting get_event\n");

        if ($this->createdEventId){
            $id = $this->createdEventId;
            $this->createdEventId = false;
        } else
            $id = is_array($event) ? ($event['id'] ? $event['id'] : $event['uid']) : $event;

        $message = $this->ocCalendar->openMessage($id);

        $event = OCParsing::getFullEventProps($this->ocCalendar, $message);
        $event = OCParsing::parseEventOc2Rc($event);

        $this->debug_msg("\nEnding get_event\n");

        return $event;
    }

    /**
     * Get event data
     *
     * @see calendar_driver::load_events()
     */
    public function load_events($start, $end, $query = null, $calendars = null)
    {
        $this->debug_msg("\nStarting load_events\n");

        $this->fetchEvents();

        $events = array();

        $attendees = array(array(
            'name' => "",
            'status' => "ACCEPTED",
            'role' => "ORGANIZER",
            'email' => $this->username,
        ));

        foreach ($this->events as $event) {
            $tempEvent = OCParsing::parseEventOc2Rc($event);
            $tempEvent['attendees'] = $attendees;
            array_push($events, $tempEvent);
        }

        foreach ($events as $event) {
            $this->debug_msg("\nThe event id is: " . $event['id'] . "\n");
            $this->debug_msg(serialize($event) . "\n");
        }
        $this->debug_msg("Ending load_events\n");

        return $events;
    }

    /**
     * Gets the properties from the appointment and fill the result with
     * in the correct way.
     *
     * @param $event MapiMessage
     *
     * @return rcube_kolab_event
     */
    private function buildEventFromProperties($event)
    {
        $this->debug_msg("\nStarting buildEventFromProperties\n");
        return $event;

        $result = array();

        // TODO: set the $result['id'] component
        // TODO: set the $result['calendar'] = calendar_id

        return $result;
    }

    /**
     * Get a list of pending alarms to be displayed to the user
     *
     * @see calendar_driver::pending_alarms()
     */
    public function pending_alarms($time, $calendars = null)
    {
        $this->debug_msg("\nStarting pending_alarms\n");
        if (empty($calendars))
            $calendars = array_keys($this->calendars);
        else if (is_string($calendars))
            $calendars = explode(',', $calendars);

        // only allow to select from calendars with activated alarms
        $calendar_ids = array();
        foreach ($calendars as $cid) {
            if ($this->calendars[$cid] && $this->calendars[$cid]['showalarms'])
                $calendar_ids[] = $cid;
        }
        $alarms = array();

        return $alarms;
    }

    /**
     * Feedback after showing/sending an alarm notification
     *
     * @see calendar_driver::dismiss_alarm()
     */
    public function dismiss_alarm($event_id, $snooze = 0)
    {
        $this->debug_msg("\nStarting dismiss_alarm\n");
        // set new notifyat time or unset if not snoozed

        return true;
    }

    /**
     * Save an attachment related to the given event
     */
    private function add_attachment($attachment, $event_id)
    {
        $this->debug_msg("\nStarting add_attachment\n");

        return true;
    }

    /**
     * Remove a specific attachment from the given event
     */
    private function remove_attachment($attachment_id, $event_id)
    {
        $this->debug_msg("\nStarting remove_attachment\n");

        return true;
    }

    /**
     * List attachments of specified event
     */
    public function list_attachments($event)
    {
        $this->debug_msg("\nStarting list_attachments\n");

        return array();
    }

    /**
     * Get attachment properties
     */
    public function get_attachment($id, $event)
    {
        $this->debug_msg("\nStarting get_attachment\n");

        return null;
    }

    /**
     * Get attachment body
     */
    public function get_attachment_body($id, $event)
    {
        $this->debug_msg("\nStarting get_attachment_body\n");

        return null;
    }

    /**
     * Remove the given category
     */
    public function remove_category($name)
    {
        $this->debug_msg("\nStarting remove_category\n");

        return true;
    }

    /**
     * Update/replace a category
     */
    public function replace_category($oldname, $name, $color)
    {
        $this->debug_msg("\nStarting replace_category\n");

        return true;
    }

}
