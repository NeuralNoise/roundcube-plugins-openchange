<?php

require_once(dirname(__FILE__) . '/ContactsParser.php');
require_once(dirname(__FILE__) . '/ArrayContactsMock.php');

class OpenchangeAddressbook extends rcube_addressbook
{

    /** public properties (mandatory) */
    public $primary_key;
    public $groups = false;
    public $readonly = true;
    public $searchonly = false;
    public $undelete = false;
    public $ready = false;
    public $group_id = 0;
    public $list_page = 1;
    public $page_size = 10;
    public $sort_col = 'name';
    public $sort_order = 'ASC';
    public $coltypes = array(
                'name' => array('limit'=>1),
                'firstname' => array('limit'=>1),
                'surname' => array('limit'=>1),
                'email' => array('limit'=>1)
    );
    public $date_cols = array();

    private $filter;
    private $result;
    private $name;
    private $contacts;

    public function __construct($id)
    {
        $this->ready = true;
        $this->name = "Contacts";

        $this->rc = rcmail::get_instance();
        $this->primary_key = $id;
        $this->groups = false;
        $this->readonly = false;

    /* Creating mocking contacts array */
        $mocked_contacts = new ArrayContactsMock('/tmp/'.$id);
        $this->contacts = $mocked_contacts->getContacts();
    /* END - Creating mocking contacts array */
    }

    public function get_name()
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting get_name\n");
fclose($handle);
        return $this->name;
    }

    public function set_search_set($filter)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting set_search_set\n");
fclose($handle);
        $this->filter = $filter;
    }

    public function get_search_set()
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting get_search_set\n");
fclose($handle);
        return $this->filter;                                                   
    }

    public function set_group($gid)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting set_group\n");
fclose($handle);
        $this->group_id = $gid;
    }

    public function reset()                                                     
    {                                                                           
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting reset\n");
fclose($handle);
        $this->result = null;                                                   
        $this->filter = null;                                                   
    }                                                                           

    function list_groups($search = null)
    {                                                                           
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting list_groups\n");
fclose($handle);
        return array(                                                           
                //array('ID' => 'testgroup1', 'name' => "Testgroup"),           
                );                                                              
    }                                                                           

    /**
     * List the current set of contact records
     *
     * @param  array   List of cols to show, Null means all
     * @param  int     Only return this number of records, use negative values for tail
     * @param  boolean True to skip the count query (select only)
     * @return array  Indexed list of contact records, each a hash array
     */
    public function list_records($cols=null, $subset=0, $nocount=false)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting list_records\n");
fwrite($handle, "cols: " . implode(",", $cols) . "\n");
fwrite($handle, "subset: " . $subset . "\n");
fwrite($handle, "nocount: " . $nocount. "\n");
fwrite($handle, "filter: " . $this->filter. "\n");
fwrite($handle, "page_size: " . $this->page_size . "\n");
fwrite($handle, "contacts count: " . count($this->contacts) . "\n");

        if ($nocount || $this->list_page <= 1) {
            $this->result = new rcube_result_set();
        } else {
            $this->result = $this->count();
        }

        /*
            The contacts to obtain are within the range:
                [$start_pos, $start_pos + $length]
        */

        if ($subset < 0)
            $start_pos = $this->result->first + $this->page_size + $subset;
        else
            $start_pos = $this->result->first;

        $length = $subset != 0 ? abs($subset) : $this->page_size;

        $this->result = $this->count(200);

        $i = $start_pos;

        while ($i < $start_pos + $length){
            $contact = ContactsParser::contact_oc2rc($this->contacts[$i]);
            $this->result->add($contact);
fwrite($handle, $i . " - Adding to the result:" . $contact["email"]);
fwrite($handle, " | " . $contact["ID"] . "\n");

            $i++;
        }
fclose($handle);
        return $this->result;
    }

    public function search($fields, $value, $strict=false, $select=true, $nocount=false, $required=array())
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting search\n");
fclose($handle);
        // no search implemented, just list all records
        return $this->list_records();
    }

    public function count($size = 0)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting count\n");
fwrite($handle, "Size: " . $size . "\n");
fwrite($handle, "First: " . ($this->list_page-1) * $this->page_size . "\n");
fclose($handle);
        return new rcube_result_set($size, ($this->list_page-1) * $this->page_size);
    }

    public function get_result()
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting get_result\n");
fclose($handle);
        return $this->result;
    }

    public function get_record($id, $assoc=false)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting get_record with id = " . $id ."\n");
fclose($handle);

        $this->list_records();

        while ($record = $this->result->next()) {
            if ($record['ID'] == $id){
                $this->result = new rcube_result_set(1);
                $this->result->add($record);

                return $assoc ? $record: $this->result;
            }
        }

        return false;
    }


    function create_group($name)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting create_group\n");
fclose($handle);
        $result = false;

        return $result;
    }

    function delete_group($gid)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting delete_group\n");
fclose($handle);
        return false;
    }

    function rename_group($gid, $newname)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting rename_group\n");
fclose($handle);
        return $newname;
    }

    function add_to_group($group_id, $ids)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting add_to_group\n");
fclose($handle);
        return false;
    }

    function remove_from_group($group_id, $ids)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting remove_from_group\n");
fclose($handle);
        return false;
    }

}

