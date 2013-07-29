<?php

    require_once(dirname(__FILE__) . '/folders_array_mock.php');
    require_once(dirname(__FILE__) . '/folders_array_parser.php');

    /**
     * Roundcube plugin to show our custom folder list
     *
     * We send Roundcube the folder list (structure) which
     * has been already obtained from the PHPbindings.
     *
     * @author  Miguel Julian <mjulian@zentyal.com>
     */
    class zentyal_oc_folders extends rcube_plugin
    {
        function init()
        {
            $this->rc = rcmail::get_instance();
            $this->load_config();
            $this->add_hook('storage_folders', array($this, 'list_mail_folders'));
        }

        function log_message($message)
        {
            if ($this->rc->config->get('zfolders_debug', false)){
                write_log($this->rc->config->get('zfolders_log_file', 'default_zentyal_folders'), $message);
            }
        }

        /**
         * storage_folders hook
         *
         * Extracted from the Roundcube documentation:
         * @param   string  $root      Optional root folder
         * @param   string  $name      Optional name pattern
         * @param   string  $filter    Optional filter
         * @param   string  $rights    Optional ACL requirements
         * @param   bool    $skip_sort Enable to return unsorted list (for better performance)
         *
         * @return  array   List of folders
         *
         * mjulian addings: ['folders' => array('folder1', 'folder1[delimiter]subfolder1', ...)]
         *                  Default delimiter: '/'
         *                  A non-clickable folder: array('hellos', 'virtual' => true)
         *                  If no "Inbox" node is present, Roundcube will add it
         */
        function list_mail_folders($args)
        {
            $this->log_message("Entering the mail folder listing plugin");

            $mock_array = new folders_array_mock();
            $mocked_folder_array = $mock_array->get_array();

            $folders_array_parser = new folders_array_parser();
            $result['folders'] = $folders_array_parser->parse_array($mocked_folder_array);

            $this->log_message("This is the array of folders we are sending to Roundcube\n");
            $this->log_message(print_r($result));

            return $result;
        }
    }
?>

