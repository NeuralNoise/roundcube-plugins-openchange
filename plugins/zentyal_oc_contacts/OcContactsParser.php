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

    public static $simpleContactProperties = array(
        PidLidFileUnder, PidLidEmail1EmailAddress,
    );

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
        PidTagHomeAddressStreet,PidLidWorkAddressStreet, PidTagOtherAddressStreet,
        PidTagHomeAddressCity,PidLidWorkAddressCity,PidTagOtherAddressCity,
        PidTagHomeAddressPostalCode,PidLidWorkAddressPostalCode,
        PidTagOtherAddressPostalCode,PidTagHomeAddressStateOrProvince,
        PidLidWorkAddressState,PidTagOtherAddressStateOrProvince,
        PidTagHomeAddressCountry,PidLidWorkAddressCountry,
        PidTagOtherAddressCountry,PidLidInstantMessagingAddress,
        PidTagPersonalHomePage,PidTagBusinessHomePage, PidTagBody,
        //PidTagAttachDataBinary
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
            PidTagGender                => array('field' => 'gender', 'isArray' => False, 'subfield' => False, 'parsingFunc' => 'parseGender'),
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

            PidTagHomeAddressStreet             => array('field' => 'address:home', 'isArray' => True, 'subfield' => 'street'),
            PidTagHomeAddressCity               => array('field' => 'address:home', 'isArray' => True, 'subfield' => 'locality'),
            PidTagHomeAddressPostalCode         => array('field' => 'address:home', 'isArray' => True, 'subfield' => 'zipcode'),
            PidTagHomeAddressStateOrProvince    => array('field' => 'address:home', 'isArray' => True, 'subfield' => 'region'),
            PidTagHomeAddressCountry            => array('field' => 'address:home', 'isArray' => True, 'subfield' => 'country'),

            PidLidWorkAddressStreet             => array('field' => 'address:work', 'isArray' => True, 'subfield' => 'street'),
            PidLidWorkAddressCity               => array('field' => 'address:work', 'isArray' => True, 'subfield' => 'locality'),
            PidLidWorkAddressPostalCode         => array('field' => 'address:work', 'isArray' => True, 'subfield' => 'zipcode'),
            PidLidWorkAddressState              => array('field' => 'address:work', 'isArray' => True, 'subfield' => 'region'),
            PidLidWorkAddressCountry            => array('field' => 'address:work', 'isArray' => True, 'subfield' => 'country'),

            PidTagOtherAddressStreet            => array('field' => 'address:other', 'isArray' => True, 'subfield' => 'street'),
            PidTagOtherAddressCity              => array('field' => 'address:other', 'isArray' => True, 'subfield' => 'locality'),
            PidTagOtherAddressPostalCode        => array('field' => 'address:other', 'isArray' => True, 'subfield' => 'zipcode'),
            PidTagOtherAddressStateOrProvince   => array('field' => 'address:other', 'isArray' => True, 'subfield' => 'region'),
            PidTagOtherAddressCountry           => array('field' => 'address:other', 'isArray' => True, 'subfield' => 'country'),

            PidLidInstantMessagingAddress => array('field' => 'im:other', 'isArray' => True, 'subfield' => False),

            PidTagPersonalHomePage  => array('field' => 'website:home', 'isArray' => True, 'subfield' => False),
            PidTagBusinessHomePage  => array('field' => 'website:work', 'isArray' => True, 'subfield' => False),

            PidTagBody => array('field' => 'notes', 'isArray' => False, 'subfield' => False),

            //PidTagAttachDataBinary => array('field' => 'photo', 'isArray' => False, 'subfield' => False),
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

    public static function getProperties($fetchedContact, $properties)
    {
        $contactProperties = call_user_func_array(array($fetchedContact, 'get'), $properties);

        return $contactProperties;
    }

    public static function setProperties($fetchedContact, $properties)
    {
        $setResult = call_user_func_array(array($fetchedContact, 'set'), $properties);

       return $setResult;
    }

    public static function createWithProperties($contacts, $properties)
    {
        return call_user_func_array(array($contacts, 'createMessage'), $properties);
    }

    public static function deleteContacts($ocContacts, $ids)
    {
        if (!is_array($ids))
            $ids = array($ids);

        return call_user_func_array(array($ocContacts, 'deleteMessages'), $ids);
    }

    public static function oc2RcParseProps($ocContact, $properties)
    {
        $contact = array();

        foreach ($properties as $prop => $field) {
            $rcubeProps = self::$oc2RcPropTranslation[$prop];

            if ($field) {
                if ($rcubeProps['subfield']) {
                    $key = self::parseOcProp2RcKey($prop);
                    $value = self::parseOcProp2RcValue($prop, $field);
                    $contact[$key][$rcubeProps['subfield']] = $value;
                } else {
                    $key = self::parseOcProp2RcKey($prop);
                    $value = self::parseOcProp2RcValue($prop, $field);
                    $contact[$key] = $value;
                }
            }
        }

        $contact['notes'] = self::parseNotesOc2Rc($ocContact, $contact['notes']);

        return $contact;
    }

    public static function parseRc2OcProp($rcubeField, $value)
    {
        $property = array();

        list($ocProp, $rcubeProps) = self::parseRc2OcKey($rcubeField);

        if ($rcubeProps['isArray'])
            $value = $value[0];

        if ($rcubeProps['subfield'] != False) {
            foreach ($value as $subfield => $subvalue) {
                list($ocProp, $rcubeProps) = self::parseRc2OcKey($rcubeField, $subfield);
                if ($value){
                    $value = self::parseRc2OcValue($rcubeProps, $subvalue, $subfield);
                    array_push($property, $ocProp, $value);
                }
            }
        } else {
            if ($value) {
                $value = self::parseRc2OcValue($rcubeProps, $value);
                array_push($property, $ocProp, $value);
            }
        }

        return $property;
    }

    public static function parseRc2OcKey($rcubeField, $subfield=False)
    {
        $ocProp = 0;

        foreach (self::$oc2RcPropTranslation as $prop => $rcubeProps) {
            if ($rcubeField == $rcubeProps['field']) {
                if ($subfield) {
                    if ($subfield == $rcubeProps['subfield']){
                        $ocProp = $prop;
                        break;
                    } else {
                        continue;
                    }
                }

                $ocProp = $prop;
                break;
            }
        }

        return array($ocProp, $rcubeProps);
    }

    public static function parseRc2OcValue($rcubeProps, $value, $subfield=False)
    {
        if (is_array($value))
            $value = $value[0];

        if (array_key_exists('parsingFunc', $rcubeProps)) {
            $func = $rcubeProps['parsingFunc'] . "Rc2Oc";
            $value = call_user_func_array(array(self, $func), array($value));
        }

        return $value;
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

    /* Single fields value parsing */
    private static function parseGenderRc2Oc($genderRC)
    {
        switch($genderRC) {
            case "female":    return 1;
            case "male":    return 2;
        }

        return 0;
    }

    /*  PidTagBody string returned sometimes has some addings at the
     *  beginning or at the ending. We try to take them out of the field
     */
    private static function parseNotesOc2Rc($ocContact, $notes)
    {
        $notes = ltrim($notes, ')');
        $exploded = explode("\r\n\n", $notes, -1);

        return join($exploded, "\n");
    }

    public static function parsePhotoOc2Rc($ocContact)
    {
        $photo = "";
        $hasPicture = $ocContact->get(PidLidHasPicture);

        if ($hasPicture){
            $attachmentTable = $ocContact->getAttachmentTable();
            $attachments = $attachmentTable->getAttachments();

            foreach ($attachments as $attach) {
                $photo = $attach->getAsBase64(PidTagAttachDataBinary);
                break;
            }

            unset($attachments);
            unset($attachmentTable);
        }

        return $photo;
    }
}
?>
