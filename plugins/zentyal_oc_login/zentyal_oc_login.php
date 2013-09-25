<?php

class zentyal_oc_login extends rcube_plugin
{
    public $task = 'login';

    //TODO Should be at a config file?
    private $server = '192.168.56.56'; //should be localhost
    private $port = 389;

    private $handle;
    private $debug = true;
    private $file = '/var/log/roundcube/my_debug.txt';

    private function debug_msg($string)
    {
        if ($this->debug) {
            fwrite($this->handle, $string);
        }
    }

    function init()
    {
        $this->handle = fopen($this->file, 'a');
        $this->rc = rcmail::get_instance();

        /* we use login after instead the authenticate hook, because, for the
         * "first login check" we will use IMAP instead of ADirectory
         */
        //$this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('login_after', array($this, 'checkMapiProfile'));
        //to perform a logout it's only needed a GET request to /?_task=logout
    }

    function authenticate($args)
    {
        $this->debug_msg("Starting the authenticate function\n");

        $args['user'] = get_input_value('_user', RCUBE_INPUT_POST);
        $args['pass'] = get_input_value('_pass', RCUBE_INPUT_POST);
        $args['cookiecheck'] = false; //do not check the cookie consistencie
        $args['valid'] = true; //do not CSRF check

        $bindingSuccessful = false;

        $ldap_conn = ldap_connect($this->server);

        if ($ldap_conn) {
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);

            $bindingSuccessful = ldap_bind($ldap_conn, $user, $pass);
        }

        if ($bindingSuccessful) {
            $this->debug_msg("Ldap bind was correct\n");
        }

        $args['user'] = $bindingSuccessful ? $args['user'] : "";

        return $args;
    }

    // jkerihuel@zempresa2.example.com
    function checkMapiProfile($args)
    {
        $this->debug_msg("Starting the checkMapiProfile function\n");
        ob_start(); var_dump($args);
        $this->debug_msg("The arguments are: \n" . ob_get_clean() . "\n");

        $rcube_user = $this->rc->user;
        $username = get_input_value('_user', RCUBE_INPUT_POST);
        $password = get_input_value('_pass', RCUBE_INPUT_POST);

        return $args;
    }

    private function is_localhost()
    {
        return $_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1';
    }
}
