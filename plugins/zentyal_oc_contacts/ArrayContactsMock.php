<?php

    function generate_random_string($length = 11, $onlyNumbers = FALSE)
    {
        $characters = "";
        if ($onlyNumbers) $characters = '0123456789';
        else $characters = 'abcdefghijklmnopqrstuvwxyz';

        $randomString = '';
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
            $contact['card_name'] = generate_random_string(10, FALSE);
            $contact['topic'] = "topic | " . generate_random_string(10, FALSE);
            $contact['full_name'] = "full_name | " . generate_random_string(10, FALSE);
            $contact['title'] = "title | " . generate_random_string(3, FALSE);
            $contact['department'] = "department | " . generate_random_string(10, FALSE);
            $contact['company'] = "company | " . generate_random_string(10, FALSE);
            $contact['email'] = $contact['card_name'] . "@supercow.test";
            $contact['office_phone'] = "office_phone | " . generate_random_string(9, TRUE);
            $contact['home_phone'] = "home_phone | " . generate_random_string(9, TRUE);
            $contact['mobile_phone'] = "mobile_phone | " . generate_random_string(9, TRUE);
            $contact['business_fax'] = "business_fax | " . generate_random_string(9, TRUE);
            $contact['business_fax'] = "business_fax | " . generate_random_string(9, TRUE);
            $contact['business_home_page'] = "business_home_page | http://www." .
                                                generate_random_string(10, FALSE) .
                                                ".test";
            $contact['postal_address'] = "postal_address | " . generate_random_string(10, FALSE);
            $contact['street_address'] = "street_address | " . generate_random_string(10, FALSE);
            $contact['locality'] = "locality | " . generate_random_string(10, FALSE);
            $contact['state'] = "state | " . generate_random_string(10, FALSE);
            $contact['country'] = "country | " . generate_random_string(10, FALSE);
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
