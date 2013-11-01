<?php
    require_once(dirname(__FILE__) . '/OpenchangeConfig.php');
    require_once(dirname(__FILE__) . '/OpenchangeDebug.php');

    /**
    * PHP library that handles the Mapi session objects.
    *
    * - Helps with the login procedures
    * - Runs the creation&destruction of the Mapi objects in the correct order
    * - Implements some caching features (TODO)
    *
    * @author  Miguel Julian <mjulian@zentyal.com>
    */

class MapiSessionHandler
{
    private $debug;

    private $sessionStarted = false;

    private $profileDB;
    private $mapiProfile;
    private $session;
    private $mailbox;
    private $folder;

    private $mapiDB;
    private $profile;

    // Used to get the PHP bindings function name to get the given folder
    private $bindingsFunctions = array(
        'contacts'  => 'contacts',
        'calendars' => 'calendar',
    );

    /**
     * Opens a mapi session with the Openchange server
     *
     * Extracted from the Roundcube documentation:
     * This hook is triggered each time an address book object is requested.
     * The id parameter specifies which address book the application needs.
     * If it's the ID of the plugins' address book, this hooks should
     * return an according instance of a rcube_addressbook implementation.
     *
     * @param   string  $username: Name of the profile to open
     * @param   string  $folder: The mailbox folder we want to get
     *
     */

    function __construct($username="", $folder="") {
        $this->debug = new OpenchangeDebug();

        if ($username && $folder) {
            try {
                $this->debug->writeMessage("Starting login procedure");
                $this->profileDB = new MAPIProfileDB(OpenchangeConfig::$profileLocation);
                $this->debug->writeMessage("Profile DB path  => " . $this->profileDB->path(), 0, "1");
                $this->mapiProfile = $this->profileDB->getProfile($username);
                $this->debug->writeMessage("Retrieved " . $username . " profile", 0, "2");
                $this->session = $this->mapiProfile->logon();
                $this->debug->writeMessage("Login successful. Session started", 0, "3");
                $this->mailbox = $this->session->mailbox();
                $this->debug->writeMessage("Mailbox opened", 0, "4");
                $this->folder = $this->openMailboxFolder($folder);
                $this->debug->writeMessage("Folder " . $folder . " retrieved", 0, "5");
                $this->sessionStarted = true;
            } catch (Exception $e) {
                $this->debug->writeMessage("Exception catched", 0, "EXCEPTION");
                ob_start();var_dump($e);
                $this->debug->writeMessage(ob_get_clean());
            }
        }
    }

    /**
     * Destroys the mapi objects in the convenient order
     *
     * As the PHP bindings are still not managing their own destruction,
     * we have to destroy every mapi object in the correct order, thus
     * no segmentation faults would appear.
     */

    function __destruct() {
        $this->debug->writeMessage("Starting Mapi objects destruction");
        if ($this->sessionStarted) {
            $this->debug->writeMessage("Unsetting", 0, "1");
            unset($this->folder);
            unset($this->mailbox);
            unset($this->session);
            unset($this->mapiProfile);
            unset($this->profileDB);
            $this->debug->writeMessage("Mapi objects unset done", 0, "2");
        } else {
            unset($this->profile);
            unset($this->mapiDB);
        }
    }

    private function openMailboxFolder($folder) {
        $getFolderFunction = $folder;

        if (isset($this->bindingsFunctions[$folder]))
            $getFolderFunction = $this->bindingsFunctions[$folder];

        return $this->mailbox->$getFolderFunction();
    }

    public function getFolder() {
        return $this->folder;
    }

    public function getMailbox() {
        return $this->mailbox;
    }

    /**
     * Call the PHP bindings to create or get a user profile
     *
     * @param   string  $user: user's email address
     * @param   string  $password: user's account password
     *
     * @return  boolean The profile operation was successful or not
     *
     */

    public function getProfile($user, $password) {
        $this->debug->writeMessage("Getting or creating the profile");

        $emailParts = explode('@', $user, 2);
        $username = $emailParts[0];
        $realm = $emailParts[1];
        $profileName = $user;
        $pathDB = OpenchangeConfig::$profileLocation;
        $server = OpenchangeConfig::$openchangeServerIP;
        $domain = OpenchangeConfig::$openchangeServerDomain;

        $this->debug->writeMessage($pathDB, 1, "Parameter");
        $this->debug->writeMessage($profileName, 1, "Parameter");
        $this->debug->writeMessage($username, 1, "Parameter");
        $this->debug->writeMessage(substr($password, 0, 1) . "... is secret!", 1, "Parameter");
        $this->debug->writeMessage($domain, 1, "Parameter");
        $this->debug->writeMessage($realm, 1, "Parameter");
        $this->debug->writeMessage($server, 1, "Parameter");

        $this->mapiDB = new MAPIProfileDB($pathDB);
        $this->profile = $this->mapiDB->createAndGetProfile($profileName, $username, $password, $domain, $realm, $server);

        return $this->profile ? true : false;
    }
}
?>
