<?php

/**
 * Zentyal Openchange driver for the Tasklist plugin
 *
 * @version @package_version@
 * @author Miguel Julian <mjulian@zentyal.com>
 *
 * Copyright (C) 2013, Zentyal
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

require_once(dirname(__FILE__) . '/../../../zentyal_lib/OpenchangeConfig.php');
require_once(dirname(__FILE__) . '/../../../zentyal_lib/MapiSessionHandler.php');
require_once(dirname(__FILE__) . '/../../../zentyal_lib/OpenchangeDebug.php');
require_once(dirname(__FILE__) . '/TasksOCParsing.php');

class tasklist_zentyal_openchange_driver extends tasklist_driver
{
    public $undelete = false;
    public $sortable = false;
    public $alarm_types = array('DISPLAY');

    private $rc;
    private $plugin;
    private $lists = array();
    private $tasks = array();
    private $list_ids = '';

    private $createdTaskId = "";

    private $debug;
    private $username;

    /**
     * Default constructor
     */
    public function __construct($plugin, $profileName)
    {
        $this->debug = new OpenchangeDebug();
        $this->debug->writeMessage("\nStarting the contructor");

        $this->rc = $plugin->rc;
        $this->plugin = $plugin;
        $this->username = $profileName;

        //Creating the OC binding
        $this->mapiSession = new MapiSessionHandler($profileName, "tasks");

        $this->_read_lists();
    }

    /**
     * Read available tasks for the current user and store them internally
     */
    private function _read_lists()
    {
      $this->debug->writeMessage("\nStarting _read_lists");

      $hidden = array_filter(explode(',', $this->rc->config->get('hidden_tasklists', '')));

      $list_id = $this->mapiSession->getFolder()->getID();
      $list['id'] = $list_id;
      //Should we use a html::quote for the name?
      $list['name'] = $this->mapiSession->getFolder()->getName();
      $list['showalarms'] = false;
      $list['active'] = true;
      $list['user_id'] = $this->rc->user->ID;
      $list['editable'] = true;
      $list['readonly'] = false;

      $this->lists[$list['id']] = $list;

      $this->debug->writeMessage("The list ids are:" . serialize($this->lists) . "");
    }

    /**
     * Get a list of available tasks lists from this source
     */
    public function get_lists()
    {
        $this->debug->writeMessage("\nStarting get_lists");

        if (empty($this->lists))
            $this->_read_lists();

        return $this->lists;
    }

    /**
     * Create a new list assigned to the current user
     *
     * @param array Hash array with list properties
     * @return mixed ID of the new list on success, False on error
     * @see tasklist_driver::create_list()
     */
    public function create_list($prop)
    {
        $this->debug->writeMessage("\nStarting create_list");

        return true;
    }

    /**
     * Update properties of an existing tasklist
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::edit_list()
     */
    public function edit_list($prop)
    {
        $this->debug->writeMessage("\nStarting edit_list");

        return true;
    }

    /**
     * Set active/subscribed state of a list
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::subscribe_list()
     */
    public function subscribe_list($prop)
    {
        $this->debug->writeMessage("\nStarting subscribe_list");

        $hidden = array_flip(explode(',', $this->rc->config->get('hidden_tasklists', '')));

        if ($prop['active'])
            unset($hidden[$prop['id']]);
        else
            $hidden[$prop['id']] = 1;

        return $this->rc->user->save_prefs(array('hidden_tasklists' => join(',', array_keys($hidden))));
    }

    /**
     * Delete the given list with all its contents
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::remove_list()
     */
    public function remove_list($prop)
    {
        $this->debug->writeMessage("\nStarting remove_list");

        $list_id = $prop['id'];

        return true;
    }

    /**
     * Get number of tasks matching the given filter
     *
     * @param array List of lists to count tasks of
     * @return array Hash array with counts grouped by status (all|flagged|today|tomorrow|overdue|nodate)
     * @see tasklist_driver::count_tasks()
     */
    function count_tasks($lists = null)
    {
        $this->debug->writeMessage("\nStarting count_tasks");

        return $counts;
    }

    /**
     * Get all taks records matching the given filter
     *
     * @param array Hash array with filter criterias:
     *  - mask:  Bitmask representing the filter selection (check against tasklist::FILTER_MASK_* constants)
     *  - from:  Date range start as string (Y-m-d)
     *  - to:    Date range end as string (Y-m-d)
     *  - search: Search query string
     * @param array List of lists to get tasks from
     * @return array List of tasks records matchin the criteria
     */
    function list_tasks($filter, $lists = null)
    {
        $this->debug->writeMessage("\nStarting list_tasks");

        $table = $this->mapiSession->getFolder()->getMessageTable();
        $ocTasks = $table->getMessages();

        $this->debug->writeMessage("The number of tasks in the table is: " . count($ocTasks) . "");

        $this->tasks = array();

        foreach ($ocTasks as $ocTask) {
            $fullOcTask = TasksOCParsing::getFullTask($this->mapiSession->getFolder(), $ocTask);
//            $this->debug->dumpVariable($fullOcTask, "The task we get from TasksOCParsing::getFullTask");
            $parsedTask = TasksOCParsing::parseTaskOc2Rc($fullOcTask);
//            $this->debug->dumpVariable($parsedTask, "The task we get from TasksOCParsing::parseTaskOc2Rc");
            array_push($this->tasks, $parsedTask);
            $this->debug->writeMessage("Task with ID: " . $parsedTask['id'], 1, "ADDING");
        }

        unset($ocTask);
        unset($ocTasks);
        unset($table);

        return $this->tasks;
    }

    /**
     * Return data of a specific task
     *
     * @param mixed  Hash array with task properties or task UID
     * @return array Hash array with task properties or false if not found
     */
    public function get_task($prop)
    {
        $this->debug->writeMessage("\nStarting get_task");

        $query = 'id';

        if ($this->createdTaskId) {
            $prop['id'] = $this->createdTaskId;
        } else if (is_string($prop)) {
            $prop['uid'] = $prop;
            $query = 'uid';
        }

        $this->list_tasks("");

        foreach ($this->tasks as $task) {
            if ($task[$query] == $prop[$query]) {
                $this->debug->dumpVariable($task, "The task we are returning");
                return $task;
            }
        }

        return false;
    }

    /**
     * Get all decendents of the given task record
     *
     * @param mixed  Hash array with task properties or task UID
     * @param boolean True if all childrens children should be fetched
     * @return array List of all child task IDs
     */
    public function get_childs($prop, $recursive = false)
    {
        $this->debug->writeMessage("\nStarting get_childs");

        // resolve UID first
        if (is_string($prop)) {
/*            $result = $this->rc->db->query(sprintf(
                "SELECT task_id AS id, tasklist_id AS list FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND uid=?",
                 $this->list_ids
                ),
                $prop);
*/
            $prop = $this->rc->db->fetch_assoc($result);
        }

        $childs = array();
        $task_ids = array($prop['id']);

        // query for childs (recursively)
        while (!empty($task_ids)) {
/*            $result = $this->rc->db->query(sprintf(
                "SELECT task_id AS id FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND parent_id IN (%s)
                 AND del=0",
                $this->list_ids,
                join(',', array_map(array($this->rc->db, 'quote'), $task_ids))
            ));
*/

            $task_ids = array();
            while ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
                $childs[] = $rec['id'];
                $task_ids[] = $rec['id'];
            }

            if (!$recursive)
                break;
        }

        return $childs;
    }

    /**
     * Get a list of pending alarms to be displayed to the user
     *
     * @param  integer Current time (unix timestamp)
     * @param  mixed   List of list IDs to show alarms for (either as array or comma-separated string)
     * @return array   A list of alarms, each encoded as hash array with task properties
     * @see tasklist_driver::pending_alarms()
     */
    public function pending_alarms($time, $lists = null)
    {
        $this->debug->writeMessage("\nStarting pending_alarms");

        if (empty($lists))
            $lists = array_keys($this->lists);
        else if (is_string($lists))
            $lists = explode(',', $lists);

        // only allow to select from calendars with activated alarms
        $list_ids = array();
        foreach ($lists as $lid) {
            if ($this->lists[$lid] && $this->lists[$lid]['showalarms'])
                $list_ids[] = $lid;
        }
        $list_ids = array_map(array($this->rc->db, 'quote'), $list_ids);

        $alarms = array();
        if (!empty($list_ids)) {
/*            $result = $this->rc->db->query(sprintf(
                "SELECT * FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND notify <= %s AND complete < 1",
                join(',', $list_ids),
                $this->rc->db->fromunixtime($time)
            ));
*/

            while ($result && ($rec = $this->rc->db->fetch_assoc($result)))
                $alarms[] = $this->_read_postprocess($rec);
        }

        return $alarms;
    }

    /**
     * Feedback after showing/sending an alarm notification
     *
     * @see tasklist_driver::dismiss_alarm()
     */
    public function dismiss_alarm($task_id, $snooze = 0)
    {
        $this->debug->writeMessage("\nStarting dismiss_alarm");

        // set new notifyat time or unset if not snoozed
        $notify_at = $snooze > 0 ? date('Y-m-d H:i:s', time() + $snooze) : null;

/*        $query = $this->rc->db->query(sprintf(
            "UPDATE " . $this->db_tasks . "
             SET   changed=%s, notify=?
             WHERE task_id=?
             AND tasklist_id IN (" . $this->list_ids . ")",
            $this->rc->db->now()),
            $notify_at,
            $task_id
        );
*/

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Map some internal database values to match the generic "API"
     */
    private function _read_postprocess($rec)
    {
        $this->debug->writeMessage("\nStarting _read_postprocess");

        $rec['id'] = $rec['task_id'];
        $rec['list'] = $rec['tasklist_id'];
        $rec['changed'] = new DateTime($rec['changed']);
        $rec['tags'] = array_filter(explode(',', $rec['tags']));

        if (!$rec['parent_id'])
            unset($rec['parent_id']);

        unset($rec['task_id'], $rec['tasklist_id'], $rec['created']);
        return $rec;
    }

    /**
     * Add a single task to the database
     *
     * @param array Hash array with task properties (see header of this file)
     * @return mixed New event ID on success, False on error
     * @see tasklist_driver::create_task()
     */
    public function create_task($task)
    {
        $this->debug->writeMessage("\nStarting create_task");
        $this->debug->dumpVariable($task, "The task we get for creation");

        // check list permissions
        $list_id = $task['list'];
        if (!$this->lists[$list_id] || $this->lists[$list_id]['readonly'])
            return false;

//        $notify_at = $this->_get_notification($prop);

        $task['flagged'] = false;

        $task['_timezone'] = $this->plugin->timezone;
        $properties = TasksOCParsing::parseRc2OcTask($task);
        $this->debug->dumpVariable($properties, "The properties we parse");

        $newTask = TasksOCParsing::createWithProperties($this->mapiSession->getFolder(), $properties);
        if (isset($newTask)) {
            $task_id = $newTask->getID();
            unset($newTask);
        } else {
            $this->debug->writeMessage("The 'newTask' we recieve is empty", 0, "ERROR");
            return false;
        }

        $this->createdTaskId = $task_id;
        $this->debug->writeMessage("The id returned is: " . $task_id . "");

        return $task_id;
    }

    /**
     * Update an task entry with the given data
     *
     * @param array Hash array with task properties
     * @return boolean True on success, False on error
     * @see tasklist_driver::edit_task()
     */
    public function edit_task($task)
    {
        $this->debug->writeMessage("\nStarting edit_task");

        //Our OC server will manage the last update timestamp
        unset($task['changed']);

        $task['_timezone'] = $this->plugin->timezone;
        //$this->debug->dumpVariable($task, "the parameter 'task'");
        $properties = TasksOCParsing::parseRc2OcTask($task);
        $ocTask = $this->mapiSession->getFolder()->openMessage($task['id'], 1);
        //$this->debug->dumpVariable($properties, "the properties we want to update");
        $setResult = TasksOCParsing::setProperties($ocTask, $properties);
        $ocTask->save();

        if (isset($task['date']) || isset($task['time']) || isset($task['alarms'])) {
            //update notifications
        }

        // moved from another list
        if ($task['_fromlist'] && ($newlist = $task['list'])) {
            //we still don't manage multiple lists
        }

        return true;
    }

    /**
     * Move a single task to another list
     *
     * @param array   Hash array with task properties:
     * @return boolean True on success, False on error
     * @see tasklist_driver::move_task()
     */
    public function move_task($prop)
    {
        $this->debug->writeMessage("\nStarting move_task");

        return $this->edit_task($prop);
    }

    /**
     * Remove a single task from the database
     *
     * @param array   Hash array with task properties
     * @param boolean Remove record irreversible
     * @return boolean True on success, False on error
     * @see tasklist_driver::delete_task()
     */
    public function delete_task($prop, $force = true)
    {
        $this->debug->writeMessage("\nStarting delete_task");

        $task_id = $prop['id'];

        if ($task_id && $force) {
/*            $query = $this->rc->db->query(
                "DELETE FROM " . $this->db_tasks . "
                 WHERE task_id=?
                 AND tasklist_id IN (" . $this->list_ids . ")",
                $task_id
            );
*/
        }
        else if ($task_id) {
 /*           $query = $this->rc->db->query(sprintf(
                "UPDATE " . $this->db_tasks . "
                 SET   changed=%s, del=1
                 WHERE task_id=?
                 AND   tasklist_id IN (%s)",
                $this->rc->db->now(),
                $this->list_ids
              ),
              $task_id
            );
*/
        }

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Restores a single deleted task (if supported)
     *
     * @param array Hash array with task properties
     * @return boolean True on success, False on error
     * @see tasklist_driver::undelete_task()
     */
    public function undelete_task($prop)
    {
        $this->debug->writeMessage("\nStarting undelete_task");

/*        $query = $this->rc->db->query(sprintf(
            "UPDATE " . $this->db_tasks . "
             SET   changed=%s, del=0
             WHERE task_id=?
             AND   tasklist_id IN (%s)",
            $this->rc->db->now(),
            $this->list_ids
          ),
          $prop['id']
        );
*/

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Compute absolute time to notify the user
     */
    private function _get_notification($task)
    {
        $this->debug->writeMessage("\nStarting _get_notifications");

        if ($task['alarms'] && $task['complete'] < 1 || strpos($task['alarms'], '@') !== false) {
            $alarm = libcalendaring::get_next_alarm($task, 'task');

        if ($alarm['time'] && $alarm['action'] == 'DISPLAY')
          return date('Y-m-d H:i:s', $alarm['time']);
      }

      return null;
    }

}
