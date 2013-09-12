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
        PidTagPersonalHomePage,PidTagBusinessHomePage, PidTagBody,
        PidTagAttachDataBinary
    );

    /* OpenChangeProperty => array(RcubeFieldName, IsArray, HasSubFields) */
    public static $oc2RcPropTranslation = array(
            PidTagDisplayName       => array('field' => 'name', 'isArray' => False, 'subfield' => False),
            PidTagNickname          => array('field' => 'nickname', 'isArray' => False, 'subfield' => False),
            PidTagGeneration        => array('field' => 'suffix', 'isArray' => False, 'subfield' => False),
            PidTagDisplayNamePrefix => array('field' => 'prefix', 'isArray' => False, 'subfield' => False),
            PidTagGivenName         => array('field' => 'firstname', 'isArray' => False, 'subfield' => False),
            PidTagSurname           => array('field' => 'surname', 'isArray' => False, 'subfield' => False),
            PidTagMiddleName        => array('field' => 'middlename', 'isArray' => False, 'subfield' => False),

            PidTagTitle             => array('field' => 'jobtitle', 'isArray' => False, 'subfield' => False),
            PidTagDepartmentName    => array('field' => 'department', 'isArray' => False, 'subfield' => False),
            PidTagCompanyName       => array('field' => 'organization', 'isArray' => False, 'subfield' => False),

            /* 0 unespecified, 1 female, 2 male */
            PidTagGender                => array('field' => 'gender', 'isArray' => False, 'subfield' => False),
            PidTagAssistant             => array('field' => 'assistant', 'isArray' => False, 'subfield' => False),
            PidTagManagerName           => array('field' => 'manager', 'isArray' => False, 'subfield' => False),
            PidTagSpouseName            => array('field' => 'spouse', 'isArray' => False, 'subfield' => False),
            PidTagBirthday              => array('field' => 'birthday', 'isArray' => False, 'subfield' => False),
            PidTagWeddingAnniversary    => array('field' => 'anniversary', 'isArray' => False, 'subfield' => False),

            PidLidEmail1EmailAddress    => array('field' => 'email:home', 'isArray' => True, 'subfield' => False),
            PidLidEmail2EmailAddress    => array('field' => 'email:work', 'isArray' => True, 'subfield' => False),
            PidLidEmail3EmailAddress    => array('field' => 'email:other', 'isArray' => True, 'subfield' => False),

            PidTagBusinessFaxNumber => array('field' => 'phone:workfax', 'isArray' => True, 'subfield' => False),
            PidTagHomeFaxNumber     => array('field' => 'phone:homefax', 'isArray' => True, 'subfield' => False),

            PidTagPrimaryTelephoneNumber    => array('field' => 'phone:main', 'isArray' => True, 'subfield' => False),
            PidTagBusinessTelephoneNumber   => array('field' => 'phone:work', 'isArray' => True, 'subfield' => False),
            PidTagBusiness2TelephoneNumber  => array('field' => 'phone:work2', 'isArray' => True, 'subfield' => False),
            PidTagHomeTelephoneNumber       => array('field' => 'phone:home', 'isArray' => True, 'subfield' => False),
            PidTagHome2TelephoneNumber      => array('field' => 'phone:home2', 'isArray' => True, 'subfield' => False),
            PidTagMobileTelephoneNumber     => array('field' => 'phone:mobile', 'isArray' => True, 'subfield' => False),
            PidTagCarTelephoneNumber        => array('field' => 'phone:car', 'isArray' => True, 'subfield' => False),
            PidTagAssistantTelephoneNumber  => array('field' => 'phone:assistant', 'isArray' => True, 'subfield' => False),
            PidTagOtherTelephoneNumber      => array('field' => 'phone:other', 'isArray' => True, 'subfield' => False),

            PidTagHomeAddressStreet             => array('field' => 'address:home', 'isArray' => False, 'street'),
            PidTadLidWorkAddressStreet          => array('field' => 'address:work', 'isArray' => False, 'street'),
            PidTagOtherAddressStreet            => array('field' => 'address:other', 'isArray' => False, 'street'),
            PidTagHomeAddressCity               => array('field' => 'address:home', 'isArray' => False, 'locality'),
            PidLidWorkAddressCity               => array('field' => 'address:work', 'isArray' => False, 'locality'),
            PidTagOtherAddressCity              => array('field' => 'address:other', 'isArray' => False, 'locality'),
            TagHomeAddressPostalCode            => array('field' => 'address:home', 'isArray' => False, 'zipcode'),
            PidLidWorkAddressPostalCode         => array('field' => 'address:work', 'isArray' => False, 'zipcode'),
            PidTagOtherAddressPostalCode        => array('field' => 'address:other', 'isArray' => False, 'zipcode'),
            PidTagHomeAddressStateOrProvince    => array('field' => 'address:home', 'isArray' => False, 'region'),
            PidLidWorkAddressState              => array('field' => 'address:work', 'isArray' => False, 'region'),
            PidTagOtherAddressStateOrProvince   => array('field' => 'address:other', 'isArray' => False, 'region'),
            PidTagHomeAddressCountry            => array('field' => 'address:home', 'isArray' => False, 'country'),
            PidLidWorkAddressCountry            => array('field' => 'address:work', 'isArray' => False, 'country'),
            PidTagOtherAddressCountry           => array('field' => 'address:other', 'isArray' => False, 'country'),

            PidLidInstantMessagingAddress => array('field' => 'im:other', 'isArray' => False, 'subfield' => False),

            PidTagPersonalHomePage  => array('field' => 'website:home', 'isArray' => True, 'subfield' => False),
            PidTagBusinessHomePage  => array('field' => 'website:work', 'isArray' => True, 'subfield' => False),

            PidTagBody => array('field' => 'notes', 'isArray' => False, 'subfield' => False),

            PidTagAttachDataBinary => array('field' => 'photo', 'isArray' => False, 'subfield' => False),
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

    public static function getProperties($fetchedContact, $full_contact_properties)
    {
        $contactProperties = call_user_func_array(
                array($fetchedContact, 'get'),
                $full_contact_properties);


        return $contactProperties;
    }

    public static function oc2RcParseProps($properties)
    {
        $contact = array();

        $i = 0;
        foreach ($properties as $prop => $field) {
            $ocProp = self::$full_contact_properties[$i];
            $rcubeProps = self::$oc2RcPropTranslation[$ocProp];

            if ($field) {
                if ($rcubeProps['subfield']) {
                    $key = self::parseOcProp2RcKey($ocProp);
                    $value = self::parseOcProp2RcValue($ocProp, $field);
                    $contact[$key][$rcubeProps['subfield']] = $value;
                } else {
                    $key = self::parseOcProp2RcKey($ocProp);
                    $value = self::parseOcProp2RcValue($ocProp, $field);
                    $contact[$key] = $value;
                }
            }

            $i++;
        }

        return $contact;
    }

    public static function parseRc2OcProp($rcubeKey)
    {
        $ocProp = 0;

        foreach (self::$oc2RcPropTranslation as $ochange => $rcube) {
            if (strpos($rcube, $rcubeKey)) {
                $ocProp = $ochange;
                break;
            }
        }

        return $ocProp;
    }


    private static function parseOcProp2RcKey($key)
    {
        $rcubeProps = self::$oc2RcPropTranslation[$key];

        return $rcubeProps['field'];
    }

    private static function parseOcProp2RcValue($key, $value)
    {
        $rcubeProps = self::$oc2RcPropTranslation[$key];

        if (!$rcubeProps['subfield'])
            if ($rcubeProps['isArray'])
                return array($value);

        return $value;
    }
}
?>
