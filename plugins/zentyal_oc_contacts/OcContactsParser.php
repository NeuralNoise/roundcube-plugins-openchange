<?php
    /**
     * Aux functions begin
     */

class OcContactsParser
{
    /**
     * Every accepted Roundcube field is at:
     * roundcubemail/program/steps/addressbooks/func.inc
     * at the "global $CONTACT_COLTYPES"
     *
     * If there is no "limit => 1" the value have to be set into
     * an array.
     *
     * It seems that rcube maidenname is not pressent a MS-OXOCNTC
     * TODO: Add the photo management
     */
    public static $contact_field_translation = array(
            'id'                        => 'ID',
            'PidTagDisplayName'         => 'name',
            'PidTagNickname'            => 'nickname',
            'PidTagGeneration'          => 'suffix',
            'PidTagDisplayNamePrefix'   => 'prefix',
            'PidTagGivenName'           => 'firstname',
            'PidTagSurname'             => 'surname',
            'PidTagMiddleName'          => 'middlename',

            'PidTagTitle'           =>'jobtitle',
            'PidTagDepartamentName' => 'department',
            'PidTagCompanyName'     => 'organization',

            /* 0 unespecified, 1 female, 2 male */
            'PidTagGender'              => 'gender',
            'PidTagAssistant'           => 'assistant',
            'PidTagManagerName'         => 'manager',
            'PidTagSpouseName'          => 'spouse',
            'PidTagBirthday'            => 'birthday',
            'PidTagWeddingAnniversary'  => 'anniversary',


            'PidLidEmail1EmailAddress'  => '@email:home',
            'PidLidEmail2EmailAddress'  => '@email:work',
            'PidLidEmail3EmailAddress'  => '@email:other',

            'PidTagPrimaryFaxNumber'    => '@phone:workfax',
            'PidTagBusinessFaxNumber'   => '@phone:workfax',
            'PidTagHomeFaxNumber'       => '@phone:homefax',

            'PidTagPrimaryTelephoneNumber'      => '@phone:main',
            'PidTagBusinessTelephoneNumber'     => '@phone:work',
            'PidTagBusiness2TelephoneNumber'    => '@phone:work2',
            'PidTagHomeTelephoneNumber'         => '@phone:home',
            'PidTagHome2TelephoneNumber'        => '@phone:home2',
            'PidTagMobileTelephoneNumber'       => '@phone:mobile',
            'PidTagCarTelephoneNumber'          => '@phone:car',
            'PidTagAssistantTelephoneNumber'    => '@phone:assistant',
            'PidTagOtherTelephoneNumber'        => '@phone:other',

            'PidTagHomeAddressStreet'           => 'address:home/street',
            'PidTagWorkAddressStreet'           => 'address:work/street',
            'PidTagOtherAddressStreet'          => 'address:other/street',
            'PidTagHomeAddressCity'             => 'address:home/locality',
            'PidTagWorkAddressCity'             => 'address:work/locality',
            'PidTagOtherAddressCity'            => 'address:other/locality',
            'PidTagHomeAddressPostalCode'       => 'address:home/zipcode',
            'PidTagWorkAddressPostalCode'       => 'address:work/zipcode',
            'PidTagOtherAddressPostalCode'      => 'address:other/zipcode',
            'PidTagHomeAddressStateOrProvince'  => 'address:home/region',
            'PidTagWorkAddressStateOrProvince'  => 'address:work/region',
            'PidTagOtherAddressStateOrProvince' => 'address:other/region',
            'PidTagHomeAddressCountry'          => 'address:home/country',
            'PidTagWorkAddressCountry'          => 'address:work/country',
            'PidTagOtherAddressCountry'         => 'address:other/country',

            'PidTagInstantMessagingAddress'=> '@im:other',

            'PidTagPersonalHomePage'    => '@website:homepage',
            'PidTagBusinessHomePage'    => '@website:work',
            );

    /**
     * Returns a contact ID obtained from a full message id
     * 
     * From a full message id (folderID/messageID) it will return a string with
     * only the message ID. As sometimes the delimiter could change, we add it
     * as a parameter. Due to differences between PHP and C signed vs unsigned
     * integers, we will use an HEX string to retrieve the message, if we want
     * that hex value, use the $toHex parameter.
     *
     * @param string $composedId the full folder/message id
     * @param string $delimiter which character splites de full id
     * @param bool $toHex wether tu return an HEX string or not
     *
     * @return string the contact id
     */
    public static function getContactId($composedId, $delimiter="/", $toHex=false)
    {
        $ids = explode($delimiter, $composedId);
        
        if ($toHex)
            $ids[1] = "0x" . $ids[1];

        return $ids[1];
    }

    public static function simpleOcContact2Rc($contact)
    {
        $result_contact = array();

        $result_contact['email:work'] = array($contact['email']);
        $result_contact['ID'] = $contact['id'];
        $result_contact['name'] = $contact['cardName'];

        return $result_contact;
    }

    public static function getFullContact($contacts, $id)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "\nStarting getFullContact id = " . $id . "\n");

        $resultContact = array();
        $contactId = self::getContactId($id, "/", true);    
        $fetchedContact = $contacts->openMessage($contactId);
        $contactProperties = call_user_func_array(
                array($fetchedContact, 'get'), $propertiesList);

fclose($handle);
        return $resultContact;
    }

    private function parse_recursive_field($key, $value, $contact)
    {
    /* TODO: if an array has elements, push into it, not replace */
        $result = $contact;

        $keys = explode("/", $key);

        if (count($keys) == 1)
            $result[$keys[0]] = $value;
        else if (count($keys) == 2)
            $result[$keys[0]][$keys[1]] = $value;

        return $result;
    }

    private function key_is_correct($key)
    {
        return array_key_exists($key, $this->contact_field_translation);
    }

    private function value_has_to_be_array($key)
    {
        return preg_match("/@/", $key);
    }
}
?>
