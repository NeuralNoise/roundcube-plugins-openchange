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

    private $events = array();


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
    public function __construct($cal)
    {
        $this->handle = fopen($this->file, 'a');
        $this->debug_msg("\nError => Starting the contructor\n");

        //Creating the OC binding
        /* TODO: Defensive code here */
        if ($this->oc_enabled) {
            $this->mapi = new MAPIProfileDB("/home/vagrant/.openchange/profiles.ldb");
            $this->mapiProfile = $this->mapi->getProfile('test');
            $this->session = $this->mapiProfile->logon();
            $this->mailbox = $this->session->mailbox();
            $this->ocCalendar = $this->mailbox->calendar();

            $table = $this->ocCalendar->getMessageTable();
            $messages = $table->getMessages();

            foreach ($messages as $message) {
                $record = OCParsing::getFullEventProps($this->ocCalendar, $message);
                array_push($this->events, $record);
//                $this->debug_msg(serialize($record) . "\n");
            }

            unset($messages);
            unset($table);
        }

        $this->cal = $cal;
        $this->rc = $cal->rc;
        $this->server_timezone = new DateTimeZone(date_default_timezone_get());

        // load library classes
        require_once($this->cal->home . '/lib/Horde_Date_Recurrence.php');

        // read database config
        $this->db_colors= $this->rc->config->get('db_table_colors', 'colors');

        $this->_read_calendars();
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

            $this->debug_msg("The properties we get:\n");
            ob_start(); var_dump($properties);
            $this->debug_msg(ob_get_clean());
            $event_id = OCParsing::createWithProperties($this->ocCalendar, $properties);

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

            $properties = OCParsing::parseRc2OcEvent($event);
            $ocEvent = $this->ocCalendar->openMessage($old['id'], 1);
            $setResult = OcContactsParser::setProperties($ocEvent, $properties);
            $ocContact->save();

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
     * Save the given event record to database
     *
     * @param array Event data, already passed through self::_save_preprocess()
     * @param boolean True if recurring events instances should be updated, too
     */
    private function _update_event($event, $update_recurring = true)
    {
        $this->debug_msg("\nStarting _update_event\n");
        $event = $this->_save_preprocess($event);
        $sql_set = array();
        $set_cols = array('start', 'end', 'all_day', 'recurrence_id', 'sequence', 'title', 'description', 'location', 'categories', 'url', 'free_busy', 'priority', 'sensitivity', 'attendees', 'alarms', 'notifyat');
        foreach ($set_cols as $col) {
            if (is_object($event[$col]) && is_a($event[$col], 'DateTime'))
                $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . $this->rc->db->quote($event[$col]->format(self::DB_DATE_FORMAT));
            else if (isset($event[$col]))
                $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . $this->rc->db->quote($event[$col]);
        }

        if ($event['_recurrence'])
            $sql_set[] = $this->rc->db->quote_identifier('recurrence') . '=' . $this->rc->db->quote($event['_recurrence']);

        if ($event['_fromcalendar'] && $event['_fromcalendar'] != $event['calendar'])
            $sql_set[] = 'calendar_id=' . $this->rc->db->quote($event['calendar']);

        $query = $this->rc->db->query(sprintf(
                    "UPDATE " . $this->db_events . "
                    SET   changed=%s %s
                    WHERE event_id=?
                    AND   calendar_id IN (" . $this->calendar_ids . ")",
                    $this->rc->db->now(),
                    ($sql_set ? ', ' . join(', ', $sql_set) : '')
                    ),
                $event['id']
                );

        $success = $this->rc->db->affected_rows($query);

        // add attachments
        if ($success && !empty($event['attachments'])) {
            foreach ($event['attachments'] as $attachment) {
                $this->add_attachment($attachment, $event['id']);
                unset($attachment);
            }
        }

        // remove attachments
        if ($success && !empty($event['deleted_attachments'])) {
            foreach ($event['deleted_attachments'] as $attachment) {
                $this->remove_attachment($attachment, $event['id']);
            }
        }

        if ($success) {
            unset($this->cache[$event['id']]);
            if ($update_recurring)
                $this->_update_recurring($event);
        }

        return $success;
    }

    /**
     * Insert "fake" entries for recurring occurences of this event
     */
    private function _update_recurring($event)
    {
        $this->debug_msg("\nStarting _update_recurring\n");
        if (empty($this->calendars))
            return;

        // clear existing recurrence copies
        $this->rc->db->query(
                "DELETE FROM " . $this->db_events . "
                WHERE recurrence_id=?
                AND calendar_id IN (" . $this->calendar_ids . ")",
                $event['id']
                );

        // create new fake entries
        if ($event['recurrence']) {
            // include library class
            require_once($this->cal->home . '/lib/calendar_recurrence.php');

            $recurrence = new calendar_recurrence($this->cal, $event);

            $count = 0;
            $duration = $event['start']->diff($event['end']);
            while ($next_start = $recurrence->next_start()) {
                $next_start->setTimezone($this->server_timezone);
                $next_end = clone $next_start;
                $next_end->add($duration);
                $notify_at = $this->_get_notification(array('alarms' => $event['alarms'], 'start' => $next_start, 'end' => $next_end));
                $query = $this->rc->db->query(sprintf(
                            "INSERT INTO " . $this->db_events . "
                            (calendar_id, recurrence_id, created, changed, uid, %s, %s, all_day, recurrence, title, description, location, categories, url, free_busy, priority, sensitivity, alarms, notifyat)
                            SELECT calendar_id, ?, %s, %s, uid, ?, ?, all_day, recurrence, title, description, location, categories, url, free_busy, priority, sensitivity, alarms, ?
                            FROM  " . $this->db_events . " WHERE event_id=? AND calendar_id IN (" . $this->calendar_ids . ")",
                            $this->rc->db->quote_identifier('start'),
                            $this->rc->db->quote_identifier('end'),
                            $this->rc->db->now(),
                            $this->rc->db->now()
                            ),
                        $event['id'],
                        $next_start->format(self::DB_DATE_FORMAT),
                        $next_end->format(self::DB_DATE_FORMAT),
                        $notify_at,
                        $event['id']
                        );

                if (!$this->rc->db->affected_rows($query))
                    break;

                // stop adding events for inifinite recurrence after 20 years
                if (++$count > 999 || (!$recurrence->recurEnd && !$recurrence->recurCount && $next_start->format('Y') > date('Y') + 20))
                    break;
            }
        }
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
            $master = $event;
            $update_master = false;
            $savemode = 'all';

            // read master if deleting a recurring event
            if ($event['recurrence'] || $event['recurrence_id']) {
                $master = $event['recurrence_id'] ? $this->get_event(array('id' => $event['recurrence_id'])) : $event;
                $savemode = $event['_savemode'];
            }

            switch ($savemode) {
                case 'current':
                    // add exception to master event
                    $master['recurrence']['EXDATE'][] = $event['start'];
                    $update_master = true;

                    // just delete this single occurence
                    $query = $this->rc->db->query(
                            "DELETE FROM " . $this->db_events . "
                            WHERE calendar_id IN (" . $this->calendar_ids . ")
                            AND event_id=?",
                            $event['id']
                            );
                    break;

                case 'future':
                    if ($master['id'] != $event['id']) {
                        // set until-date on master event
                        $master['recurrence']['UNTIL'] = clone $event['start'];
                        $master['recurrence']['UNTIL']->sub(new DateInterval('P1D'));
                        unset($master['recurrence']['COUNT']);
                        $update_master = true;

                        // delete this and all future instances
                        $fromdate = clone $event['start'];
                        $fromdate->setTimezone($this->server_timezone);
                        $query = $this->rc->db->query(
                                "DELETE FROM " . $this->db_events . "
                                WHERE calendar_id IN (" . $this->calendar_ids . ")
                                AND " . $this->rc->db->quote_identifier('start') . " >= ?
                                AND recurrence_id=?",
                                $fromdate->format(self::DB_DATE_FORMAT),
                                $master['id']
                                );
                        break;
                    }
                    // else: future == all if modifying the master event

                default:  // 'all' is default
                    $query = $this->rc->db->query(
                            "DELETE FROM " . $this->db_events . "
                            WHERE (event_id=? OR recurrence_id=?)
                            AND calendar_id IN (" . $this->calendar_ids . ")",
                            $master['id'],
                            $master['id']
                            );
                    break;
            }

            $success = $this->rc->db->affected_rows($query);
            if ($success && $update_master)
                $this->_update_event($master, true);

            return $success;
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
        $this->debug_msg("The event we get is:\n" . serialize($event) . "\n");

        $id = is_array($event) ? ($event['id'] ? $event['id'] : $event['uid']) : $event;
        $col = is_array($event) && is_numeric($id) ? 'event_id' : 'uid';

        $message = $this->ocCalendar->openMessage('0x' . $id);

        $event = OCParsing::getFullEventProps($this->ocCalendar, $message);
        $event = OCParsing::parseEventOc2Rc($event);

        $this->debug_msg("The event we build is:\n" . serialize($event) . "\n");

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
        $events = array();

        foreach ($this->events as $event) {
            $tempEvent = OCParsing::parseEventOc2Rc($event);
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
     * Convert sql record into a rcube style event object
     */
    private function _read_postprocess($event)
    {
        $this->debug_msg("\nStarting _read_postprocess\n");
        $free_busy_map = array_flip($this->free_busy_map);
        $sensitivity_map = array_flip($this->sensitivity_map);

        $event['id'] = $event['event_id'];
        $event['start'] = new DateTime($event['start']);
        $event['end'] = new DateTime($event['end']);
        $event['allday'] = intval($event['all_day']);
        $event['created'] = new DateTime($event['created']);
        $event['changed'] = new DateTime($event['changed']);
        $event['free_busy'] = $free_busy_map[$event['free_busy']];
        $event['sensitivity'] = $sensitivity_map[$event['sensitivity']];
        $event['calendar'] = $event['calendar_id'];
        $event['recurrence_id'] = intval($event['recurrence_id']);

        // parse recurrence rule
        if ($event['recurrence'] && preg_match_all('/([A-Z]+)=([^;]+);?/', $event['recurrence'], $m, PREG_SET_ORDER)) {
            $event['recurrence'] = array();
            foreach ($m as $rr) {
                if (is_numeric($rr[2]))
                    $rr[2] = intval($rr[2]);
                else if ($rr[1] == 'UNTIL')
                    $rr[2] = date_create($rr[2]);
                else if ($rr[1] == 'EXDATE')
                    $rr[2] = array_map('date_create', explode(',', $rr[2]));
                $event['recurrence'][$rr[1]] = $rr[2];
            }
        }

        if ($event['_attachments'] > 0)
            $event['attachments'] = (array)$this->list_attachments($event);

        // decode serialized event attendees
        if ($event['attendees']) {
            $attendees = array();
            foreach (explode("\n", $event['attendees']) as $line) {
                $att = array();
                foreach (rcube_utils::explode_quoted_string(';', $line) as $prop) {
                    list($key, $value) = explode("=", $prop);
                    $att[strtolower($key)] = stripslashes(trim($value, '""'));
                }
                $attendees[] = $att;
            }
            $event['attendees'] = $attendees;
        }

        unset($event['event_id'], $event['calendar_id'], $event['notifyat'], $event['all_day'], $event['_attachments']);
        return $event;
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
        $calendar_ids = array_map(array($this->rc->db, 'quote'), $calendar_ids);

        $alarms = array();
        if (!empty($calendar_ids)) {
            $result = $this->rc->db->query(sprintf(
                        "SELECT * FROM " . $this->db_events . "
                        WHERE calendar_id IN (%s)
                        AND notifyat <= %s AND %s > %s",
                        join(',', $calendar_ids),
                        $this->rc->db->fromunixtime($time),
                        $this->rc->db->quote_identifier('end'),
                        $this->rc->db->fromunixtime($time)
                        ));

            while ($result && ($event = $this->rc->db->fetch_assoc($result)))
                $alarms[] = $this->_read_postprocess($event);
        }

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
        $notify_at = $snooze > 0 ? date(self::DB_DATE_FORMAT, time() + $snooze) : null;

        $query = $this->rc->db->query(sprintf(
                    "UPDATE " . $this->db_events . "
                    SET   changed=%s, notifyat=?
                    WHERE event_id=?
                    AND calendar_id IN (" . $this->calendar_ids . ")",
                    $this->rc->db->now()),
                $notify_at,
                $event_id
                );

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Save an attachment related to the given event
     */
    private function add_attachment($attachment, $event_id)
    {
        $this->debug_msg("\nStarting add_attachment\n");
        $data = $attachment['data'] ? $attachment['data'] : file_get_contents($attachment['path']);

        $query = $this->rc->db->query(
                "INSERT INTO " . $this->db_attachments .
                " (event_id, filename, mimetype, size, data)" .
                " VALUES (?, ?, ?, ?, ?)",
                $event_id,
                $attachment['name'],
                $attachment['mimetype'],
                strlen($data),
                base64_encode($data)
                );

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Remove a specific attachment from the given event
     */
    private function remove_attachment($attachment_id, $event_id)
    {
        $this->debug_msg("\nStarting remove_attachment\n");
        $query = $this->rc->db->query(
                "DELETE FROM " . $this->db_attachments .
                " WHERE attachment_id = ?" .
                " AND event_id IN (SELECT event_id FROM " . $this->db_events .
                " WHERE event_id = ?"  .
                " AND calendar_id IN (" . $this->calendar_ids . "))",
                $attachment_id,
                $event_id
                );

        return $this->rc->db->affected_rows($query);
    }

    /**
     * List attachments of specified event
     */
    public function list_attachments($event)
    {
        $this->debug_msg("\nStarting list_attachments\n");
        $attachments = array();

        if (!empty($this->calendar_ids)) {
            $result = $this->rc->db->query(
                    "SELECT attachment_id AS id, filename AS name, mimetype, size " .
                    " FROM " . $this->db_attachments .
                    " WHERE event_id IN (SELECT event_id FROM " . $this->db_events .
                    " WHERE event_id=?"  .
                    " AND calendar_id IN (" . $this->calendar_ids . "))".
                    " ORDER BY filename",
                    $event['recurrence_id'] ? $event['recurrence_id'] : $event['event_id']
                    );

            while ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
                $attachments[] = $arr;
            }
        }

        return $attachments;
    }

    /**
     * Get attachment properties
     */
    public function get_attachment($id, $event)
    {
        $this->debug_msg("\nStarting get_attachment\n");
        if (!empty($this->calendar_ids)) {
            $result = $this->rc->db->query(
                    "SELECT attachment_id AS id, filename AS name, mimetype, size " .
                    " FROM " . $this->db_attachments .
                    " WHERE attachment_id=?".
                    " AND event_id=?",
                    $id,
                    $event['recurrence_id'] ? $event['recurrence_id'] : $event['id']
                    );

            if ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
                return $arr;
            }
        }

        return null;
    }

    /**
     * Get attachment body
     */
    public function get_attachment_body($id, $event)
    {
        $this->debug_msg("\nStarting get_attachment_body\n");
        if (!empty($this->calendar_ids)) {
            $result = $this->rc->db->query(
                    "SELECT data " .
                    " FROM " . $this->db_attachments .
                    " WHERE attachment_id=?".
                    " AND event_id=?",
                    $id,
                    $event['id']
                    );

            if ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
                return base64_decode($arr['data']);
            }
        }

        return null;
    }

    /**
     * Remove the given category
     */
    public function remove_category($name)
    {
        $this->debug_msg("\nStarting remove_category\n");
        $query = $this->rc->db->query(
                "UPDATE " . $this->db_events . "
                SET   categories=''
                WHERE categories=?
                AND   calendar_id IN (" . $this->calendar_ids . ")",
                $name
                );

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Update/replace a category
     */
    public function replace_category($oldname, $name, $color)
    {
        $this->debug_msg("\nStarting replace_category\n");
        $query = $this->rc->db->query(
                "UPDATE " . $this->db_events . "
                SET   categories=?
                WHERE categories=?
                AND   calendar_id IN (" . $this->calendar_ids . ")",
                $name,
                $oldname
                );

        return $this->rc->db->affected_rows($query);
    }

}
