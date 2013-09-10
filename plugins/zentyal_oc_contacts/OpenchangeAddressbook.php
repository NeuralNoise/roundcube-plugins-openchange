<?php

require_once(dirname(__FILE__) . '/ArrayContactsMock.php');
require_once(dirname(__FILE__) . '/OcContactsParser.php');

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

    private $handle;
    private $mocked = false;
    private $oc_enabled = true;

    private $ocContacts;
    private $mailbox;
    private $session;
    private $mapiProfile;
    private $mapi;

    /**
     * This variable sets which fields can be shown or set while editing
     */
    public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
            'jobtitle', 'organization', 'department', 'assistant', 'manager',
            'gender', 'spouse', 'email', 'phone', 'address',
            'birthday', 'anniversary', 'website', 'im', 'notes', 'photo');

    /**
     * Default destructor
     */
    public function __destruct()
    {
        fwrite($this->handle, "\nStarting the destructor\n");
        if ($this->oc_enabled) {
            unset($this->ocContacts);
            unset($this->mailbox);
            unset($this->session);
            unset($this->mapiProfile);
            unset($this->mapi);
        }
        fwrite($this->handle, "\nExiting the destructor\n");
        fclose($this->handle);
    }

    public function __construct($id)
    {
        $this->ready = true;
        $this->name = "Contacts";

        $this->rc = rcmail::get_instance();
        $this->primary_key = $id;
        $this->groups = false;
        $this->readonly = false;

        $file = '/var/log/roundcube/my_debug.txt';
        $this->handle = fopen($file, 'a');
        fwrite($this->handle, "\nStarting the constructor\n");

        //Creating the OC binding
        /* TODO: Defensive code here */
        if ($this->oc_enabled) {
            $this->mapi = new MAPIProfileDB("/home/vagrant/.openchange/profiles.ldb");
            $this->mapiProfile = $this->mapi->getProfile('test');
            $this->session = $this->mapiProfile->logon();
            $this->mailbox = $this->session->mailbox();
            $this->ocContacts = $this->mailbox->contacts();

            $contactsTable =  $this->ocContacts->getMessageTable();
            $this->contacts = $contactsTable->summary();
        }

    /* Creating mocking contacts array */
        if ($this->mocked) {
            $mocked_contacts = new ArrayContactsMock('/tmp/'.$id);
            $this->contacts = $mocked_contacts->getContacts();
        }
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

        $length = ($length > count($this->contacts)) ? count($this->contacts) : $length;

        $this->result = $this->count($length);

        $i = $start_pos;

fwrite($this->handle, "start: " . $start_pos . " | length: " . $length . "\n");
        while ($i < $start_pos + $length){
            $contact = OcContactsParser::simpleOcContact2Rc($this->contacts[$i]);
            $this->result->add($contact);
fwrite($handle, $i . " - Adding to the result:" . $contact["email"]);
fwrite($handle, " | " . $contact["ID"] . "\n");

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
                    if (str_replace('/','_', $record['id']) == $id){
                        $result_record = OcContactsParser::simpleOcContact2Rc($record);
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
        $size = ($size == 0) ? $size : count($this->contacts);
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
fwrite($handle, "\nStarting get_record id = " . $id . " | assoc = " .$assoc);
fwrite($handle, " | Single id = " . OcContactsParser::getContactId($id, "_") ."\n");

        foreach ($this->contacts as $record) {
            if (str_replace('/','_',$record['id']) == $id){
                $result_record = OcContactsParser::getFullContact($this->ocContacts, $record['id']);
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
}
?>
