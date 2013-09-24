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
    private $debug = true;
    private $file = '/var/log/roundcube/my_debug.txt';
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
            'jobtitle', 'organization', 'department', 'email', 'phone', 'address',
            'birthday', 'website', 'im', 'notes', 'photo');

    /**
     * Default destructor
     */
    public function __destruct()
    {
        $this->debug_msg( "\nStarting the destructor\n");
        if ($this->oc_enabled) {
            unset($this->ocContacts);
            unset($this->mailbox);
            unset($this->session);
            unset($this->mapiProfile);
            unset($this->mapi);
        }
        $this->debug_msg( "\nExiting the destructor\n");
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

        $this->handle = fopen($this->file, 'a');
        $this->debug_msg( "\nStarting the constructor\n");

        //Creating the OC binding
        /* TODO: Defensive code here */
        if ($this->oc_enabled) {
            $this->mapi = new MAPIProfileDB("/home/vagrant/.openchange/profiles.ldb");
            $this->debug_msg( "1: Path => " . $this->mapi->path() . " | ");
            $this->mapiProfile = $this->mapi->getProfile('test');
            $this->debug_msg( "2");
            $this->session = $this->mapiProfile->logon();
            $this->debug_msg( "3");
            $this->mailbox = $this->session->mailbox();
            $this->debug_msg( "4: Mailbox name => " . $this->mailbox->getName() . " | ");
            $this->ocContacts = $this->mailbox->contacts();
            $this->debug_msg( "5");

            $contactsTable =  $this->ocContacts->getMessageTable();
            $this->debug_msg( "6");
            $this->contacts = array();
            $messages = $contactsTable->getMessages();
            foreach ($messages as $message) {
                $record = array();
                $record['email'] = $message->get(PidLidEmail1EmailAddress);
                $record['id'] = $message->getID();
                $record['cardName'] = $message->get(PidLidFileUnder);

                array_push($this->contacts, $record);
            }
            $this->debug_msg( "7");
        }
    }

    private function debug_msg($string)
    {
        if ($this->debug) {
            fwrite($this->handle, $string);
        }
    }

    public function get_name()
    {
        $this->debug_msg( "\nStarting get_name\n");
        return $this->name;
    }

    public function set_search_set($filter)
    {
        $this->debug_msg( "\nStarting set_search_set\n");
        $this->filter = $filter;
    }

    public function get_search_set()
    {
        $this->debug_msg( "\nStarting get_search_set\n");
        return $this->filter;
    }

    public function set_group($gid)
    {
        $this->debug_msg( "\nStarting set_group\n");
        $this->group_id = $gid;
    }

    public function reset()
    {
        $this->debug_msg( "\nStarting reset\n");
        $this->result = null;
        $this->filter = null;
    }

    function list_groups($search = null)
    {
        $this->debug_msg( "\nStarting list_groups\n");
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
        $this->debug_msg( "\nStarting list_records\n");
        $this->debug_msg( "cols: " . serialize($cols) . "\n");
        $this->debug_msg( "subset: " . $subset . "\n");
        $this->debug_msg( "nocount: " . $nocount. "\n");
        $this->debug_msg( "filter: " . $this->filter. "\n");
        $this->debug_msg( "page_size: " . $this->page_size . "\n");
        $this->debug_msg( "contacts count: " . count($this->contacts) . "\n");

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

        $this->debug_msg( "start: " . $start_pos . " | length: " . $length . "\n");
        while ($i < $start_pos + $length){
            $contact = OcContactsParser::simpleOcContact2Rc($this->contacts[$i]);
            $this->result->add($contact);
            $this->debug_msg( $i . " - Adding to the result:" . $contact["email"]);
            $this->debug_msg( " | " . $contact["ID"] . "\n");

            $i++;
        }
        return $this->result;
    }

    /***
     * This is the search call after clicking at compose from Contacts:
     *      $CONTACTS->search($CONTACTS->primary_key, $cid, 0, true, true, 'email');
     *          $cid es un array con uno o varios IDs.
     */
    public function search($fields, $value, $strict=false, $select=true, $nocount=false, $required=array())
    {
        $this->debug_msg( "\nStarting search\n");

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
        $this->debug_msg( "\nStarting count\n");
        $this->debug_msg( "Size: " . $size . "\n");
        $this->debug_msg( "First: " . ($this->list_page-1) * $this->page_size . "\n");
        $size = ($size == 0) ? $size : count($this->contacts);
        return new rcube_result_set($size, ($this->list_page-1) * $this->page_size);
    }

    public function get_result()
    {
        $this->debug_msg( "\nStarting get_result\n");
        return $this->result;
    }

    /**
     * Called when a single contact is selected
     * Also when you modify a contact
     * This function should make a PHPbindings specific call
     */
    public function get_record($id, $assoc=false)
    {
        $this->debug_msg( "\nStarting get_record id = " . $id . " | assoc = " .$assoc);
        $this->debug_msg( " | Single id = " . OcContactsParser::getContactId($id, "_") ."\n");

        foreach ($this->contacts as $record) {
            $recordId = str_replace('/','_',$record['id']);
            if ($recordId  == $id){
                $result_record = $this->getFullContact($recordId);
                $this->result = new rcube_result_set(1);
                $this->result->add($result_record);

                return $result_record;
            }
        }


        return $assoc ? $result_record: $this->result;
    }

    private function getFullContact($id)
    {
        $contact = array();

        $ocContact = $this->ocContacts->openMessage($id);

        $propsToGet = OcContactsParser::$full_contact_properties;
        $properties = OcContactsParser::getProperties($ocContact, $propsToGet);
        $contact = OcContactsParser::oc2RcParseProps($ocContact, $properties);
        $contact['ID'] = $id;

        $this->debug_msg("The full contact is: \n" . serialize($contact) . "\n");

        return $contact;
    }

    /**
     * Update a specific contact record
     *
     * @param mixed Record identifier
     * @param array Assoziative array with save data
     * @return boolean True on success, False on error
     */
    function update($id, $save_cols)
    {
        $this->debug_msg( "\nStarting update id = " . $id . "\n");
        $updated = false;
        $properties = array();

        foreach ($save_cols as $col => $value) {
            $property = OcContactsParser::parseRc2OcProp($col, $value);
            $this->debug_msg("Col: " . $col . " | Value: ");
            if (is_array($value)) $this->debug_msg(serialize($value));
            else $this->debug_msg($value);
            $this->debug_msg(" | Prop: " . $property . "\n");
            $properties = array_merge($property, $properties);

        }

        $ocContact = $this->ocContacts->openMessage($id, 1);
        $setResult = OcContactsParser::setProperties($ocContact, $properties);
        $ocContact->save();

        $this->result = null;  // clear current result

        return count($properties);
    }

    /**
     * Create a new contact record
     *
     * @param array Associative array with save data
     * @return integer|boolean The created record ID on success, False on error
     */
    function insert($save_data, $check=false)
    {
        $this->debug_msg( "\nStarting insert id = " . $id . "\n");
        $updated = false;
        $properties = array();

        foreach ($save_data as $col => $value) {
            $property = OcContactsParser::parseRc2OcProp($col, $value);
            $properties = array_merge($property, $properties);
        }

        foreach ($properties as $prop) {
            $rcubeProps = OcContactsParser::$oc2RcPropTranslation[979173407];
            $this->debug_msg(serialize($rcubeProps) . "\n");
            ob_start(); var_dump($prop);
            $this->debug_msg(ob_get_clean());
        }

        $createResult = OcContactsParser::createWithProperties($this->ocContacts, $properties);
        $this->debug_msg( "\nEnding insert id = " . $createResult . "\n");

        return $createResult ? $createResult : $False;

    }

    function create_group($name)
    {
        $this->debug_msg( "\nStarting create_group\n");
        $result = false;

        return $result;
    }

    function delete_group($gid)
    {
        $this->debug_msg( "\nStarting delete_group\n");
        return false;
    }

    function rename_group($gid, $newname)
    {
        $this->debug_msg( "\nStarting rename_group\n");
        return $newname;
    }

    function add_to_group($group_id, $ids)
    {
        $this->debug_msg( "\nStarting add_to_group\n");
        return false;
    }

    function remove_from_group($group_id, $ids)
    {
        $this->debug_msg( "\nStarting remove_from_group\n");
        return false;
    }
}
?>
