<?php
/**
 * Static class that will help to parse Openchange contacts data
 * to the way Roundcube would understand it.
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

    public static $full_contact_properties = array(
        PidTagDisplayName,PidTagNickname,PidTagGeneration,PidTagDisplayNamePrefix,
        PidTagGivenName,PidTagSurname,PidTagMiddleName,PidTagTitle,
        PidTagDepartmentName,PidTagCompanyName,PidTagGender,
        PidTagAssistant,PidTagManagerName,PidTagSpouseName,PidTagBirthday,
        PidTagWeddingAnniversary,PidLidEmail1EmailAddress,
        PidLidEmail2EmailAddress,PidLidEmail3EmailAddress,
        PidTagBusinessFaxNumber,PidTagHomeFaxNumber,PidTagPrimaryTelephoneNumber,
        PidTagBusinessTelephoneNumber,PidTagBusiness2TelephoneNumber,
        PidTagHomeTelephoneNumber,PidTagHome2TelephoneNumber,
        PidTagMobileTelephoneNumber,PidTagCarTelephoneNumber,
        PidTagAssistantTelephoneNumber,PidTagOtherTelephoneNumber,
        PidTagHomeAddressStreet,PidLidWorkAddressStreet,PidTagOtherAddressStreet,
        PidTagHomeAddressCity,PidLidWorkAddressCity,PidTagOtherAddressCity,
        PidTagHomeAddressPostalCode,PidLidWorkAddressPostalCode,
        PidTagOtherAddressPostalCode,PidTagHomeAddressStateOrProvince,
        PidLidWorkAddressState,PidTagOtherAddressStateOrProvince,
        PidTagHomeAddressCountry,PidLidWorkAddressCountry,
        PidTagOtherAddressCountry,PidLidInstantMessagingAddress,
        PidTagPersonalHomePage,PidTagBusinessHomePage, PidTagBody
    );

    public static $contact_field_translation = array(
            PidTagDisplayName       => 'name',
            PidTagNickname          => 'nickname',
            PidTagGeneration        => 'suffix',
            PidTagDisplayNamePrefix => 'prefix',
            PidTagGivenName         => 'firstname',
            PidTagSurname           => 'surname',
            PidTagMiddleName        => 'middlename',

            PidTagTitle             =>'jobtitle',
            PidTagDepartmentName    => 'department',
            PidTagCompanyName       => 'organization',

            /* 0 unespecified, 1 female, 2 male */
            PidTagGender                => 'gender',
            PidTagAssistant             => 'assistant',
            PidTagManagerName           => 'manager',
            PidTagSpouseName            => 'spouse',
            PidTagBirthday              => 'birthday',
            PidTagWeddingAnniversary    => 'anniversary',

            PidLidEmail1EmailAddress    => '@email:home',
            PidLidEmail2EmailAddress    => '@email:work',
            PidLidEmail3EmailAddress    => '@email:other',

            PidTagBusinessFaxNumber => '@phone:workfax',
            PidTagHomeFaxNumber     => '@phone:homefax',

            PidTagPrimaryTelephoneNumber    => '@phone:main',
            PidTagBusinessTelephoneNumber   => '@phone:work',
            PidTagBusiness2TelephoneNumber  => '@phone:work2',
            PidTagHomeTelephoneNumber       => '@phone:home',
            PidTagHome2TelephoneNumber      => '@phone:home2',
            PidTagMobileTelephoneNumber     => '@phone:mobile',
            PidTagCarTelephoneNumber        => '@phone:car',
            PidTagAssistantTelephoneNumber  => '@phone:assistant',
            PidTagOtherTelephoneNumber      => '@phone:other',

            PidTagHomeAddressStreet           => 'address:home/street',
            PidLidWorkAddressStreet           => 'address:work/street',
            PidTagOtherAddressStreet          => 'address:other/street',
            PidTagHomeAddressCity             => 'address:home/locality',
            PidLidWorkAddressCity             => 'address:work/locality',
            PidTagOtherAddressCity            => 'address:other/locality',
            PidTagHomeAddressPostalCode       => 'address:home/zipcode',
            PidLidWorkAddressPostalCode       => 'address:work/zipcode',
            PidTagOtherAddressPostalCode      => 'address:other/zipcode',
            PidTagHomeAddressStateOrProvince  => 'address:home/region',
            PidLidWorkAddressState            => 'address:work/region',
            PidTagOtherAddressStateOrProvince => 'address:other/region',
            PidTagHomeAddressCountry          => 'address:home/country',
            PidLidWorkAddressCountry          => 'address:work/country',
            PidTagOtherAddressCountry         => 'address:other/country',

            PidLidInstantMessagingAddress => '@im:other',

            PidTagPersonalHomePage  => '@website:homepage',
            PidTagBusinessHomePage  => '@website:work',

            PidTagBody => 'notes',
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

    /* TODO: manage de id -> ID conversion */
        $contactId = self::getContactId($id, "/", true);    
        $fetchedContact = $contacts->openMessage($contactId);
        $contactProperties = call_user_func_array(
                array($fetchedContact, 'get'),
                self::$full_contact_properties);

        $resultContact = self::parseProperties($contactProperties);
        $resultContact['ID'] = $id;

ob_start(); var_dump($resultContact);
fwrite($handle, "this is the contact:\n" . ob_get_clean() . "\n");

fclose($handle);
        return $resultContact;
    }

    private static function parseProperties($properties)
    {
$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
        $contact = array();

        $i = 0;

        foreach ($properties as $prop => $field) {
            $prop = self::$full_contact_properties[$i];
            $i++;
fwrite($handle, "prop: " . $prop . " | field: " . $field . "\n");
            $contact = self::parseProperty($prop, $field, $contact);    
        }
fclose($handle);
        return $contact;
    }

    private static function parseProperty($key, $value, $contact)
    {
    /* TODO: if an array has elements, push into it, not replace */

$file = '/var/log/roundcube/my_debug.txt';
$handle = fopen($file, 'a');
fwrite($handle, "===> ");
        $result = $contact;

        $key = self::$contact_field_translation[$key];
        $keys = explode("/", $key);
        
        if ($value) {
            if (self::valueHasToBeArray($key)) {
                $finalValue = array($value);
                $keys[0] = substr($keys[0], 1);
            } else {
                $finalValue = $value;
            }

    
    fwrite($handle, "prop: " . serialize($keys) . " | field: " . $finalValue . "\n");

            if (count($keys) == 1)
                $result[$keys[0]] = $finalValue;
            else if (count($keys) == 2)
                $result[$keys[0]][$keys[1]] = $finalValue;
        }
fclose($handle);
        return $result;
    }

    private static function keyIsCorrect($key)
    {
        return array_key_exists($key, $this->contact_field_translation);
    }

    private static function valueHasToBeArray($key)
    {
        return preg_match("/@/", $key);
    }
}
?>
