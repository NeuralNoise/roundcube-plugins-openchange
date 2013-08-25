<?php

    function generate_random_string($length = 11, $onlyNumbers = FALSE)
    {
        $characters = "";
        if ($onlyNumbers) $characters = '0123456789';
        else $characters = 'abcdefghijklmnopqrstuvwxyz';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return ucfirst($randomString);
    }

class ArrayContactsMock
{
    private $contactsArray;
    private $fileHandler;

    function __construct($fileName, $numberOfContacts=200)
    {
        $this->contactsArray = array();

        if (file_exists($fileName)) {
            $this->fileToContacts($fileName);
        } else {
            $this->fileHandler = fopen($fileName, 'w');

            $this->generateRandomContacts($numberOfContacts);
            $this->contactsToFile();

            fclose($this->fileHandler);
        }
    }

    function addContact($contact = array())
    {
        if (! $contact){
            $contact['id'] = generate_random_string(10, TRUE);
            $contact['card_name'] = generate_random_string(4, FALSE);
            $contact['given_name'] = $contact['card_name'] . "GN" . generate_random_string(4, FALSE);
            $contact['surname'] = $contact['card_name'] . "SN" . generate_random_string(4, FALSE);
            $contact['middlename'] = generate_random_string(2, FALSE) . ".";
            $contact['email'] = $contact['card_name'] . "@supercow.test";
            $contact['home_phone'] = generate_random_string(9, TRUE);
            $contact['street_address'] = generate_random_string(9, FALSE);
        }

        array_push($this->contactsArray, $contact);

        return $contact;
    }

    function getContacts()
    {
        return $this->contactsArray;
    }

    private function generateRandomContacts($numberOfContacts)
    {
        $i = $numberOfContacts;
        while ($i > 0) {
            $this->addContact();
            $i --;
        }
    }

    private function contactsToFile()
    {
        fwrite($this->fileHandler, serialize($this->contactsArray));
    }

    private function fileToContacts($fileName)
    {
        $fileContents = file_get_contents($fileName);
        $this->contactsArray = unserialize($fileContents);
    }
}
?>
