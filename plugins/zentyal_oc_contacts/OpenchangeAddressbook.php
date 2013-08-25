<?php

require_once(dirname(__FILE__) . '/ArrayContactsMock.php');

class OpenchangeAddressbook extends rcube_addressbook
{

    /** public properties (mandatory) */
    public $primary_key = 'id';
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
    public $date_cols = array();

    private $filter;
    private $result;
    private $name;
    private $contacts;
    /**
     * This variable sets which fields can be shown or set while editing
     */
    public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
            'jobtitle', 'organization', 'department', 'assistant', 'manager',
            'gender', 'maidenname', 'spouse', 'email', 'phone', 'address',
            'birthday', 'anniversary', 'website', 'im', 'notes', 'photo');

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
fwrite($handle, "cols: " . serialize($cols) . "\n");
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
            $contact = $this->contact_oc2rc($this->contacts[$i]);
            $this->result->add($contact);
/*fwrite($handle, $i . " - Adding to the result:" . $contact["email"]);
fwrite($handle, " | " . $contact["ID"] . "\n");*/

            $i++;
        }
fclose($handle);
        return $this->result;
    }

    /***
     * This is the search call after clicking at compose from Contacts:
     *      $CONTACTS->search($CONTACTS->primary_key, $cid, 0, true, true, 'email');
     *          $cid es un array con uno o varios IDs.
     */
    public function search($fields, $value, $strict=false, $select=true, $nocount=false, $required=array())
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting search\n");
fclose($handle);

        /* Compose from Contacts screen */
        if ($fields == $this->primary_key) {
            $this->result = new rcube_result_set(count($value));

            foreach ($value as $id) {
                foreach ($this->contacts as $record) {
                    if ($record['id'] == $id){
                        $result_record = $this->contact_oc2rc($record);
                        $this->result->add($result_record);
                    }
                }
            }

            return $this->result;
        }

        return $this->result;
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

    /**
     * Called when a single contact is selected
     * Also when you modify a contact
     * This function should make a PHPbindings specific call
     */
    public function get_record($id, $assoc=false)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting get_record id = " . $id . " | assoc = " .$assoc ."\n");

        foreach ($this->contacts as $record) {
            if ($record['id'] == $id){
                $result_record = $this->contact_oc2rc($record);
                $this->result = new rcube_result_set(1);
                $this->result->add($result_record);

                return $result_record;
            }
        }

fclose($handle);

        return $assoc ? $result_record: $this->result;
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

    /**
     * Aux functions begin
     */

    /**
     * Every accepted Roundcube field is at:
     * roundcubemail/program/steps/addressbooks/func.inc
     * at the "global $CONTACT_COLTYPES"
     *
     * If there is no "limit => 1" the value have to be set into
     * an array.
     */
    private $contact_field_translation = array(
            'id'                    => 'ID',
            'card_name'             => 'name',
            'topic'                 => 'nickname',
            'full_name'             => 'full_name',
            'given_name'            => 'firstname',
            'surname'               => 'surname',
            'title'                 => 'title',
            'department'            => 'department',
            'company'               => 'organization',
            'email'                 => '@email:home',
            'office_phone'          => '@phone:work',
            'home_phone'            => '@phone:home',
            'mobile_phone'          => 'mobile_phone',
            'business_fax'          => 'business_fax',
            'business_home_page'    => 'business_home_page',
            'postal_address'        => 'address',
            'street_address'        => 'address/street',
            'locality'              => 'locality',
            'state'                 => 'state',
            'country'               => 'country',
            'middlename'            => 'middlename',
            );

    private function contact_oc2rc($contact)
    {
        $result_contact = array();

        foreach ($contact as $key => $value) {
            if ($this->key_is_correct($key)) {
                $rcube_key = $this->contact_field_translation[$key];

                if ($this->value_has_to_be_array($rcube_key)) {
                    $value = array($value);
                    $rcube_key = substr($rcube_key, 1);
                }

                $result_contact = $this->parse_recursive_field($rcube_key,
                                                    $value, $result_contact);
            }
        }

        return $result_contact;
    }

    private function parse_recursive_field($key, $value, $contact)
    {
        $result = $contact;

        $keys = explode("/", $key);

        if (count($keys) == 1)
            $result[$keys[0]] = $value;
        else if (count($keys) == 2)
            $result[$keys[0]][$keys[1]] = $value;

        return $result;
    }

    private function key_is_correct($key)
    {
        return array_key_exists($key, $this->contact_field_translation);
    }

    private function value_has_to_be_array($key)
    {
        return preg_match("/@/", $key);
    }
}
?>
