<?php
    require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeConfig.php');
    require_once(dirname(__FILE__) . '/../zentyal_lib/MapiSessionHandler.php');
    require_once(dirname(__FILE__) . '/../zentyal_lib/OpenchangeDebug.php');

    /**
     * Roundcube plugin that will handle the user Openchange profile
     *
     * This Roundcube plugin will check the user credentials against the
     * Openchange server once the IMAP login has been successful.
     * - It is triggered with the 'login_after' hook
     * - If something happens the user will be redirected to the login page
     *   (again) logging some information in the roundcube-openchange log.
     *
     * @author  Miguel Julian <mjulian@zentyal.com>
     */

class zentyal_oc_login extends rcube_plugin
{
    public $task = 'login';

    private $debug;

    function init()
    {
        $this->debug = new OpenchangeDebug();

        $this->debug->writeMessage("Starting the zentyal_oc_login plugin.", 0, "login");

        $this->rc = rcmail::get_instance();
        $this->load_config();

        $this->add_hook('login_after', array($this, 'checkMapiProfile'));
        //to perform a logout it's only needed a GET request to /?_task=logout
    }

    function checkMapiProfile($args)
    {
        $this->debug->writeMessage("Starting the checkMapiProfile function", 0, "login");

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
