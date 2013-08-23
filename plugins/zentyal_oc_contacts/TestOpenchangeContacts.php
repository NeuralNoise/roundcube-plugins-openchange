<?php

require_once('ArrayContactsMock.php');
require_once('ContactsParser.php');

class TestOpenchangeContacts extends PHPUnit_Framework_TestCase
{
    public function testMockedArray()
    {
        $mocked_contacts = new ArrayContactsMock();

        $this->assertEquals(0, count($mocked_contacts->getContacts()));

        $contact = $mocked_contacts->addContact();
        $this->assertNotEmpty($contact);
        $this->assertEquals(1, count($mocked_contacts->getContacts()));
        $contact = $mocked_contacts->addContact(array('card_name' => 'Super Cow'));
        $this->assertEquals('Super Cow', $contact['card_name']);
        $this->assertEquals(2, count($mocked_contacts->getContacts()));
    }

    public function testContactsParser()
    {
        $mocked_contacts = new ArrayContactsMock();
        $contact = $mocked_contacts->addContact();
        $contact = $mocked_contacts->addContact();
        $contact = $mocked_contacts->addContact();

        $this->assertTrue(ContactsParser::initialize());
        $this->assertEquals(true, is_array(ContactsParser::contact_oc2rc($contact)));
    }
}
?>
