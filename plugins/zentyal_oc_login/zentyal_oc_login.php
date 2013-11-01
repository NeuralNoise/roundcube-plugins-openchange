<?php
require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeConfig.php');
require_once(dirname(__FILE__) . '/../zentyal_lib/MapiSessionHandler.php');

class zentyal_oc_login extends rcube_plugin
{
    public $task = 'login';

    private $handle;

    private function debug_msg($string)
    {
        if (OpenchangeConfig::$debugEnabled) {
            fwrite($this->handle, $string);
        }
    }

    function init()
    {
        $this->handle = fopen(OpenchangeConfig::$logLocation, 'a');
        $this->rc = rcmail::get_instance();
        $this->load_config();

        /* we use login after instead the authenticate hook, because, for the
         * "first login check" we will use IMAP instead of ADirectory
         */
        //$this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('login_after', array($this, 'checkMapiProfile'));
        //to perform a logout it's only needed a GET request to /?_task=logout
    }

    function checkMapiProfile($args)
    {
        $this->debug_msg("Starting the checkMapiProfile function\n");

        $user = get_input_value('_user', RCUBE_INPUT_POST);
        $password = get_input_value('_pass', RCUBE_INPUT_POST);

        $mapiSession = new MapiSessionHandler();
        $profileCreated = $mapiSession->getProfile($user, $password);

        /* As we can set $args['task'] and $args['action'] (and other URL params) we can redirect here to
         * wherever we want
         * If we want a custom logout, search for => $OUTPUT->show_message('loggedout');
         */

        if (!$profileCreated) {
            $args['_task'] = "logout";
            $args['_user'] = $user;
            $this->debug_msg("Something went wrong. Redirecting to logout task.\n");
        }

        return $args;
    }

    private function is_localhost()
    {
        return $_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1';
    }
}
