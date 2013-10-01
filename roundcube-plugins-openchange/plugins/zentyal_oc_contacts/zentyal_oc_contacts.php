<?php

    require_once(dirname(__FILE__) . '/OpenchangeAddressbook.php');

    /**
     * Roundcube plugin to show our custom contacts address books and contacts.
     *
     * Populate Roundcube interface with the contacts obtained from the
     * Openchange backend.
     *
     * @author  Miguel Julian <mjulian@zentyal.com>
     */
    class zentyal_oc_contacts extends rcube_plugin
    {
        function init()
        {
            $this->rc = rcmail::get_instance();
            $this->load_config();
            $this->add_hook('addressbooks_list', array($this, 'get_all_addressbooks'));
            $this->add_hook('addressbook_get', array($this, 'get_address_book'));
        }

        function log_message($message)
        {
            if ($this->rc->config->get('zcontacts_debug', false)){
                write_log($this->rc->config->get('zcontacts_log_file', 'default_zentyal_contacts'), $message);
            }
        }

        /**
         * addressbooks_list hook
         *
         * Extracted from the Roundcube documentation:
         * Triggered when building the list of address sources in the address
         * book view. Each entry in the sources list needs to hold the
         * following fields: id, name, readonly.
         *
         * @param   array   $sources: Hash array with list of available address books
         *
         * @return  array   $sources
         *
         * mjulian notes:
         *      Every array inside the 'sources' element could have the following keys:
         *          - id        => id of the current address book, used for obtaining
         *              the contacts list.
         *          - name      => Actual shown name of the address book
         *          - readonly  => Does Roundcube show the "Edit contact" button?
         */

        function get_all_addressbooks($args)
        {
            $args['sources']['openchange'] = array(
                    'id' => 'openchange',
                    'name' => 'Contacts',
                    'readonly' => false,
            );

            return $args;
        }

        /**
         * addressbook_get hook
         *
         * Extracted from the Roundcube documentation:
         * This hook is triggered each time an address book object is requested.
         * The id parameter specifies which address book the application needs.
         * If it's the ID of the plugins' address book, this hooks should
         * return an according instance of a rcube_addressbook implementation.
         *
         * Arguments:
         *      id
         *
         * Return values:
         *      instance: Instance of an address book implementation derived
         *      from rcube_addressbook
         * @return  array   $sources
         *
         */

        function get_address_book($args)
        {
            $username = $_SESSION['username'];

            $args['instance'] = new OpenchangeAddressbook($args['id'], $username);

            return $args;
        }
    }
?>
