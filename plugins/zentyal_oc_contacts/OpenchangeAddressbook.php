<?php

require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeConfig.php');
require_once(dirname(__FILE__) . '/../zentyal_lib/MapiSessionHandler.php');
require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeDebug.php');
require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeDebug.php');
require_once(dirname(__FILE__) . '/../zentyal_lib/MapiObjectsCache.php');
require_once(dirname(__FILE__) . '/OcContactsParser.php');

class OpenchangeAddressbook extends rcube_addressbook
{

    /** public properties (mandatory) */
    public $primary_key = 'id';
    public $groups = false;
    public $readonly = false;
    public $searchonly = false;
    public $ready = true;
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

    private $debug;

    private $mapiSession = false;

    /**
     * This variable sets which fields can be shown or set while editing
     */
    public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'prefix', 'suffix', 'nickname',
            'jobtitle', 'organization', 'department', 'email', 'phone', 'address',
            'website', 'im', 'notes', 'photo');

    public function __construct($plugin, $id, $username)
    {
        $this->ready = true;
        $this->name = "Contacts";

        $this->rc = rcmail::get_instance();
        $this->plugin = $plugin;

        $this->primary_key = $id;
        $this->groups = false;
        $this->readonly = false;

        $this->debug = new OpenchangeDebug();
        $this->debug->writeMessage( "\nStarting the constructor\n");
        $this->debug->writeMessage("ID: " . $id . " | profileName: " . $username . "\n");

        $this->contacts = array();

        //Creating the OC binding
        if (OpenchangeConfig::$useCachedSessions) {
            $this->debug->writeMessage("The cache before starting:\n" . MapiObjectsCache::cacheDump());
            $this->mapiSession = MapiObjectsCache::get($username);
            $this->debug->writeMessage("We have queried the " . $username . " mapi object.", 0, "CACHE");
        }

        if ($this->mapiSession === false) {
            $this->mapiSession = new MapiSessionHandler($username, "contacts");
            if (OpenchangeConfig::$useCachedSessions) {
                MapiObjectsCache::add($username, $this->mapiSession);
                $this->debug->writeMessage("We have added the " . $username . " mapi objects to the cache.", 0, "CACHE");
                $this->debug->writeMessage("The cache after doing all the stuff:\n" . MapiObjectsCache::cacheDump());
            }
        }


        // Fisrt contact fetching
        if ($this->mapiSession->sessionStarted) {
            $this->fetchOcContacts();
        } else {
            $this->rc->output->command('display_message', $this->plugin->gettext('openchangeerror'), 'notice');
        }
    }

    private function fetchOcContacts()
    {
        $contactsTable =  $this->mapiSession->getFolder()->getMessageTable();
        $messages = $contactsTable->getMessages();
        foreach ($messages as $message) {
            $record = array();
            $record['email'] = $message->get(PidLidEmail1EmailAddress);
            $record['id'] = $message->getID();
            $record['cardName'] = $message->get(PidLidFileUnder);

            array_push($this->contacts, $record);
        }
        unset($message);
        unset($messages);
        unset($contactsTable);
        $this->debug->writeMessage(" - The number of fetched contacts is: " . count($this->contacts) . " - ");
    }

    public function get_name()
    {
        $this->debug->writeMessage( "\nStarting get_name\n");
        return $this->name;
    }

    public function set_search_set($filter)
    {
        $this->debug->writeMessage( "\nStarting set_search_set\n");
        $this->filter = $filter;
    }

    public function get_search_set()
    {
        $this->debug->writeMessage( "\nStarting get_search_set\n");
        return $this->filter;
    }

    public function set_group($gid)
    {
        $this->debug->writeMessage( "\nStarting set_group\n");
        $this->group_id = $gid;
    }

    public function reset()
    {
        $this->debug->writeMessage( "\nStarting reset\n");
        $this->result = null;
        $this->filter = null;
    }

    function list_groups($search = null)
    {
        $this->debug->writeMessage( "\nStarting list_groups\n");
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
        $this->debug->writeMessage( "\nStarting list_records\n");
        $this->debug->writeMessage( "cols: " . serialize($cols) . "\n");
        $this->debug->writeMessage( "subset: " . $subset . "\n");
        $this->debug->writeMessage( "nocount: " . $nocount. "\n");
        $this->debug->writeMessage( "filter: " . $this->filter. "\n");
        $this->debug->writeMessage( "page_size: " . $this->page_size . "\n");
        $this->debug->writeMessage( "contacts count: " . count($this->contacts) . "\n");

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

        $this->debug->writeMessage( "start: " . $start_pos . " | length: " . $length . "\n");
        while ($i < $start_pos + $length){
            $contact = OcContactsParser::simpleOcContact2Rc($this->contacts[$i]);
            $this->result->add($contact);
            $this->debug->writeMessage( $i . " - Adding to the result:" . $contact["email"]);
            $this->debug->writeMessage( " | " . $contact["ID"] . "\n");

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
        $this->debug->writeMessage( "\nStarting search\n");

        /* Compose from Contacts screen  or after contact creation*/
        if ($fields == $this->primary_key || $fields == 'openchange') {
            if (!is_array($value))
                $value = array($value);

            /* TODO Improve this, a whole search for having the new contact */
            $this->contacts = array();
            $this->fetchOcContacts();
            $this->result = new rcube_result_set(count($value));

            foreach ($value as $id) {
                foreach ($this->contacts as $record) {
                    if (str_replace('/','_', $record['id']) == $id){
                        $result_record = OcContactsParser::simpleOcContact2Rc($record);
                        $this->result->add($result_record);
                    }
                }
            }
        }

        return $this->result;
    }

    public function count($size = 0)
    {
        $this->debug->writeMessage( "\nStarting count\n");
        $this->debug->writeMessage( "Size: " . $size . "\n");
        $this->debug->writeMessage( "First: " . ($this->list_page-1) * $this->page_size . "\n");
        $size = ($size == 0) ? $size : count($this->contacts);
        return new rcube_result_set($size, ($this->list_page-1) * $this->page_size);
    }

    public function get_result()
    {
        $this->debug->writeMessage( "\nStarting get_result\n");
        return $this->result;
    }

    /**
     * Called when a single contact is selected
     * Also when you modify a contact
     * This function should make a PHPbindings specific call
     */
    public function get_record($id, $assoc=false)
    {
        $this->debug->writeMessage( "\nStarting get_record id = " . $id . " | assoc = " .$assoc);
        $this->debug->writeMessage( " | Single id = " . OcContactsParser::getContactId($id, "_") ."\n");

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

        $ocContact = $this->mapiSession->getFolder()->openMessage($id);

        $propsToGet = OcContactsParser::$full_contact_properties;
        $properties = OcContactsParser::getProperties($ocContact, $propsToGet);
        $contact = OcContactsParser::oc2RcParseProps($ocContact, $properties);
        $contact['photo'] = OcContactsParser::parsePhotoOc2Rc($ocContact);
        $contact['ID'] = $id;

        unset($ocContact);

        $this->debug->writeMessage("The full contact is: \n" . serialize($contact) . "\n");

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
        $this->debug->writeMessage( "\nStarting update id = " . $id . "\n");
        $updated = false;
        $properties = array();

        foreach ($save_cols as $col => $value) {
            $property = OcContactsParser::parseRc2OcProp($col, $value);
            $this->debug->writeMessage("Col: " . $col . " | Value: ");
            if (is_array($value)) $this->debug->writeMessage(serialize($value));
            else $this->debug->writeMessage($value);
            $this->debug->writeMessage(" | Prop: " . $property . "\n");
            $properties = array_merge($property, $properties);

        }

        /* When the attachments could be set */
        /*
        if (array_key_exists('photo') && $save_cols['photo']) {
            $photoProperties = OcContactsParser::parsePhotoRc2Oc($save_cols['photo']);
            $properties = array_merge($properties, $photoProperties);
        }
        */

        $ocContact = $this->mapiSession->getFolder()->openMessage($id, 1);
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
        $this->debug->writeMessage( "\nStarting insert id = " . $id . "\n");
        $updated = false;
        $properties = array();

        foreach ($save_data as $col => $value) {
            $property = OcContactsParser::parseRc2OcProp($col, $value);
            $properties = array_merge($property, $properties);
        }

        foreach ($properties as $prop) {
            $rcubeProps = OcContactsParser::$oc2RcPropTranslation[979173407];
            $this->debug->writeMessage(serialize($rcubeProps) . "\n");
            ob_start(); var_dump($prop);
            $this->debug->writeMessage(ob_get_clean());
        }

        $contact = OcContactsParser::createWithProperties($this->mapiSession->getFolder(), $properties);
        $id = $contact->getID();
        $this->debug->writeMessage( "\nEnding insert id = " . $id. "\n");

        return $id ? $id: $False;

    }

    /**
     * Mark one or more contact records as deleted
     *
     * @param array   Record identifiers
     * @param boolean Remove record(s) irreversible (unsupported)
     */
    function delete($ids, $force=true)
    {
        $this->debug->writeMessage( "\nDeleting contacts\n");
        OcContactsParser::deleteContacts($this->mapiSession->getFolder(), $ids);

        return true;
    }

    function create_group($name)
    {
        $this->debug->writeMessage( "\nStarting create_group\n");
        $result = false;

        return $result;
    }

    function delete_group($gid)
    {
        $this->debug->writeMessage( "\nStarting delete_group\n");
        return false;
    }

    function rename_group($gid, $newname)
    {
        $this->debug->writeMessage( "\nStarting rename_group\n");
        return $newname;
    }

    function add_to_group($group_id, $ids)
    {
        $this->debug->writeMessage( "\nStarting add_to_group\n");
        return false;
    }

    function remove_from_group($group_id, $ids)
    {
        $this->debug->writeMessage( "\nStarting remove_from_group\n");
        return false;
    }
}
?>
