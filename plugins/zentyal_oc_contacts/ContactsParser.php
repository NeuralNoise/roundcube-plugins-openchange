<?php
class ContactsParser
{
    public static function initialize()
    {
        return TRUE;
    }

    public static $contact_field_translation = array(
        'id'                    => 'ID',
        'card_name'             => 'name',
        'topic'                 => 'nickname',
        'full_name'             => 'full_name',
        'given_name'            => 'firstname',
        'surname'               => 'surname',
        'title'                 => 'title',
        'department'            => 'department',
        'company'               => 'organization',
        'email'                 => 'email',
        'office_phone'          => 'phone',
        'home_phone'            => 'home_phone',
        'mobile_phone'          => 'mobile_phone',
        'business_fax'          => 'business_fax',
        'business_home_page'    => 'business_home_page',
        'postal_address'        => 'address',
        'street_address'        => 'street',
        'locality'              => 'locality',
        'state'                 => 'state',
        'country'               => 'country',
    );

    public static function contact_oc2rc($contact)
    {
        $result_contact = array();

        foreach ($contact as $key => $value) {
            if (array_key_exists($key, self::$contact_field_translation)) {
                $result_contact[self::$contact_field_translation[$key]] = $value;
            }
        }

        return $result_contact;
    }
}
?>
