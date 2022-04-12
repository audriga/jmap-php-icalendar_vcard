<?php

# TODO run against JSContact instead of JMAP for Contacts
namespace OpenXPort\Test\VCard;

use PHPUnit\Framework\TestCase;
use Sabre\VObject;

use function PHPUnit\Framework\assertEquals;

final class VCardAdapterTest extends TestCase
{
    /**
     * @var VCard
     */
    protected $vCard = null;

    /**
     * @var VCardAdapter
     */
    protected $adapter = null;

    /**
     * @var VCardMapper
     */
    protected $mapper = null;

    /**
     * @var array
     */
    protected $vCardData = null;

    /**
     * @var OpenXPort\Jmap\Contact\Contact
     */
    protected $jmapContact = null;

    public function setUp(): void
    {
        // Skip this test class, since it's obsolete, as it relates to the older JMAP for Contacts standard
        $this->markTestSkipped();
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard.vcf', 'r')
        );

        $this->adapter = new \OpenXPort\Adapter\VCardOxpAdapter();
        $this->mapper = new \OpenXPort\Mapper\VCardMapper();

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];
    }

    public function tearDown(): void
    {
        $this->vCard = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->vCardData = null;
        $this->jmapContact = null;
    }

    public function testCorrectContactObjectTypeMapping()
    {
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\Contact', $this->jmapContact);
    }

    public function testCorrectNameMapping()
    {
        $jmapContactLastName = $this->jmapContact->getLastName();
        $jmapContactFirstName = $this->jmapContact->getFirstName();
        $jmapContactMiddleName = $this->jmapContact->getMiddlename();
        $jmapContactPrefix = $this->jmapContact->getPrefix();
        $jmapContactSuffix = $this->jmapContact->getSuffix();

        $this->assertNotNull($jmapContactLastName);
        $this->assertNotNull($jmapContactFirstName);
        $this->assertNotNull($jmapContactMiddleName);
        $this->assertNotNull($jmapContactPrefix);
        $this->assertNotNull($jmapContactSuffix);

        $this->assertEquals('Gump', $jmapContactLastName);
        $this->assertEquals('Forrest', $jmapContactFirstName);
        $this->assertEquals('MiddleName', $jmapContactMiddleName);
        $this->assertEquals('Mr.', $jmapContactPrefix);
        $this->assertEquals('Suffix', $jmapContactSuffix);
    }

    public function testEmptyNameMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an empty name (vCard "N") property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactLastName = $this->jmapContact->getLastName();
        $jmapContactFirstName = $this->jmapContact->getFirstName();
        $jmapContactMiddleName = $this->jmapContact->getMiddlename();
        $jmapContactPrefix = $this->jmapContact->getPrefix();
        $jmapContactSuffix = $this->jmapContact->getSuffix();

        $this->assertEquals('', $jmapContactLastName);
        $this->assertEquals('', $jmapContactFirstName);
        $this->assertEquals('', $jmapContactMiddleName);
        $this->assertEquals('', $jmapContactPrefix);
        $this->assertEquals('', $jmapContactSuffix);
    }

    public function testCorrectNicknameMapping()
    {
        $jmapContactNickname = $this->jmapContact->getNickname();

        $this->assertNotNull($jmapContactNickname);
        $this->assertEquals('forresty', $jmapContactNickname);
    }

    public function testEmptyNicknameMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an empty nickname property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactNickname = $this->jmapContact->getNickname();

        $this->assertEquals('', $jmapContactNickname);
    }

    public function testCorrectJobTitleMapping()
    {
        $jmapContactJobTitle = $this->jmapContact->getJobTitle();

        $this->assertNotNull($jmapContactJobTitle);
        $this->assertNotEmpty($jmapContactJobTitle);
        $this->assertEquals('Shrimp Man', $jmapContactJobTitle);
    }

    public function testEmptyJobTitleMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an empty jobTitle property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactJobTitle = $this->jmapContact->getJobTitle();

        $this->assertEmpty($jmapContactJobTitle);
    }

    public function testCorrectOrganizationMapping()
    {
        $jmapContactOrganization = $this->jmapContact->getCompany();

        $this->assertNotNull($jmapContactOrganization);
        $this->assertNotEmpty($jmapContactOrganization);
        $this->assertEquals('Bubba Gump Shrimp Co.', $jmapContactOrganization);
    }

    public function testEmptyOrganizationMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an empty organization property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactOrganization = $this->jmapContact->getCompany();

        $this->assertEmpty($jmapContactOrganization);
    }

    public function testCorrectDisplaynameMapping()
    {
        $jmapContactDisplayname = $this->jmapContact->getDisplayname();

        $this->assertNotNull($jmapContactDisplayname);
        $this->assertNotEmpty($jmapContactDisplayname);
        $this->assertEquals('Forrest Gump', $jmapContactDisplayname);
    }

    public function testEmptyDisplaynameMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an empty displayname (vCard "FN") property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactDisplayname = $this->jmapContact->getDisplayname();

        $this->assertEmpty($jmapContactDisplayname);
    }

    public function testCorrectBirthdayMapping()
    {
        $jmapContactBirthday = $this->jmapContact->getBirthday();

        $this->assertNotNull($jmapContactBirthday);
        $this->assertNotEmpty($jmapContactBirthday);
        $this->assertEquals('1995-05-05', $jmapContactBirthday);
    }

    public function testWrongBirthdayMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a wrongly-formatted birthday property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactBirthday = $this->jmapContact->getBirthday();

        $this->assertNotNull($jmapContactBirthday);
        $this->assertNotEmpty($jmapContactBirthday);
        // Expect a default JMAP value of "0000-00-00" due to incapability of correct parsing
        $this->assertEquals('0000-00-00', $jmapContactBirthday);
    }

    public function testCorrectAnniversaryMapping()
    {
        $jmapContactAnniversary = $this->jmapContact->getAnniversary();

        $this->assertNotNull($jmapContactAnniversary);
        $this->assertNotEmpty($jmapContactAnniversary);
        $this->assertEquals('2005-10-10', $jmapContactAnniversary);
    }

    public function testWrongAnniversaryMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a wrongly-formatted anniversary property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactAnniversary = $this->jmapContact->getAnniversary();

        $this->assertNotNull($jmapContactAnniversary);
        $this->assertNotEmpty($jmapContactAnniversary);
        // Expect a default JMAP value of "0000-00-00" due to incapability of correct parsing
        $this->assertEquals('0000-00-00', $jmapContactAnniversary);
    }

    public function testCorrectGenderMapping()
    {
        $jmapContactGender = $this->jmapContact->getGender();

        $this->assertNotNull($jmapContactGender);
        $this->assertNotEmpty($jmapContactGender);
        $this->assertEquals('male', $jmapContactGender);
    }

    public function testWrongGenderMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains an invalid gender property
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapContactGender = $this->jmapContact->getGender();

        $this->assertNull($jmapContactGender);
    }

    public function testCorrectAddressMapping()
    {
        $jmapContactWorkAddress = $this->jmapContact->getAddresses()[0];
        $jmapContactHomeAddress = $this->jmapContact->getAddresses()[1];

        // Assert that the JMAP addresses mapped from the vCard addresses are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\Address', $jmapContactWorkAddress);
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\Address', $jmapContactHomeAddress);

        // Assert that the JMAP address types are correct
        $this->assertEquals('work', $jmapContactWorkAddress->getType());
        $this->assertEquals('home', $jmapContactHomeAddress->getType());

        // Assert that the JMAP address labels are correct
        $this->assertEquals(null, $jmapContactWorkAddress->getLabel());
        $this->assertEquals(null, $jmapContactHomeAddress->getLabel());

        // Assert that the JMAP address street components are correct
        $this->assertEquals('100 Waters Edge', $jmapContactWorkAddress->getStreet());
        $this->assertEquals('42 Plantation St.', $jmapContactHomeAddress->getStreet());

        // Assert that the JMAP address locality components are correct
        $this->assertEquals('Baytown', $jmapContactWorkAddress->getLocality());
        $this->assertEquals('Baytown', $jmapContactHomeAddress->getLocality());

        // Assert that the JMAP address region components are correct
        $this->assertEquals('LA', $jmapContactWorkAddress->getRegion());
        $this->assertEquals('LA', $jmapContactHomeAddress->getRegion());

        // Assert that the JMAP address postcode components are correct
        $this->assertEquals('30314', $jmapContactWorkAddress->getPostcode());
        $this->assertEquals('30314', $jmapContactHomeAddress->getPostcode());

        // Assert that the JMAP address country components are correct
        $this->assertEquals('United States of America', $jmapContactWorkAddress->getCountry());
        $this->assertEquals('United States of America', $jmapContactHomeAddress->getCountry());

        // Assert that the JMAP address "isDefault" property is correct
        $this->assertEquals(false, $jmapContactWorkAddress->getIsDefault());
        $this->assertEquals(false, $jmapContactHomeAddress->getIsDefault());
    }

    public function testUnknownAddressTypeMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a single address without an address type (i.e. "home", "work", etc.)
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapAddressWithDefaultType = $this->jmapContact->getAddresses()[0];

        // Assert that the JMAP address mapped from the vCard address is of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\Address', $jmapAddressWithDefaultType);

        // Verify that the address type is set to the value of "other" by default
        $this->assertEquals('other', $jmapAddressWithDefaultType->getType());
    }

    public function testCorrectEmailMapping()
    {
        $jmapContactHomeEmail = $this->jmapContact->getEmails()[0];
        $jmapContactWorkEmail = $this->jmapContact->getEmails()[1];

        // Assert that the JMAP emails mapped from the vCard emails are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactHomeEmail);
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactWorkEmail);

        // Assert that the JMAP email types are correct
        $this->assertEquals('personal', $jmapContactHomeEmail->getType());
        $this->assertEquals('work', $jmapContactWorkEmail->getType());

        // Assert that the JMAP email labels are correct
        // (expect null, since no labels are set in our test vCard)
        $this->assertEquals(null, $jmapContactHomeEmail->getLabel());
        $this->assertEquals(null, $jmapContactWorkEmail->getLabel());

        // Assert that the JMAP email values are correct
        $this->assertEquals('forrestgump@example.com', $jmapContactHomeEmail->getValue());
        $this->assertEquals('forrestgump-work@example.com', $jmapContactWorkEmail->getValue());

        // Assert that the JMAP email "isDefault" properties are correct
        // (expect false here, since our test vCard does not have any default email addresses)
        $this->assertEquals(false, $jmapContactHomeEmail->getIsDefault());
        $this->assertEquals(false, $jmapContactWorkEmail->getIsDefault());
    }

    public function testUnknownEmailTypeMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a single email address without an email address type (i.e. "home", "work", etc.)
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapEmailWithDefaultType = $this->jmapContact->getEmails()[0];

        // Assert that the JMAP email mapped from the vCard email is of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapEmailWithDefaultType);

        // Verify that the email type is set to the value of "other" by default
        $this->assertEquals('other', $jmapEmailWithDefaultType->getType());
    }

    public function testCorrectPhoneMapping()
    {
        $jmapContactWorkPhone = $this->jmapContact->getPhones()[0];
        $jmapContactHomePhone = $this->jmapContact->getPhones()[1];

        // Assert that the JMAP phones mapped from the vCard phones are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactWorkPhone);
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactHomePhone);

        // Assert that the JMAP phone types are correct
        $this->assertEquals('work', $jmapContactWorkPhone->getType());
        $this->assertEquals('home', $jmapContactHomePhone->getType());

        // Assert that the JMAP phone labels are correct
        // (expect null, since no labels are set in our test vCard)
        $this->assertEquals(null, $jmapContactWorkPhone->getLabel());
        $this->assertEquals(null, $jmapContactHomePhone->getLabel());

        // Assert that the JMAP phone values are correct
        $this->assertEquals('(111) 555-1212', $jmapContactWorkPhone->getValue());
        $this->assertEquals('(404) 555-1212', $jmapContactHomePhone->getValue());

        // Assert that the JMAP phone "isDefault" properties are correct
        // (expect false here, since our test vCard does not have any default phones)
        $this->assertEquals(false, $jmapContactWorkPhone->getIsDefault());
        $this->assertEquals(false, $jmapContactHomePhone->getIsDefault());
    }

    public function testUnknownPhoneTypeMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a single phone without a phone type (i.e. "home", "work", etc.)
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        $jmapPhoneWithDefaultType = $this->jmapContact->getPhones()[0];

        // Assert that the JMAP phone mapped from the vCard phone is of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapPhoneWithDefaultType);

        // Verify that the phone type is set to the value of "other" by default
        $this->assertEquals('other', $jmapPhoneWithDefaultType->getType());
    }

    public function testCorrectWebsiteMapping()
    {
        $jmapContactWebsite = $this->jmapContact->getOnline()[0];

        // Assert that the JMAP website mapped from the vCard website is of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactWebsite);

        // Assert that the JMAP website type is correct
        // (it should always be "uri")
        $this->assertEquals('uri', $jmapContactWebsite->getType());

        // Assert that the JMAP phone label is correct
        // (expect null, since no label is set in our test vCard)
        $this->assertEquals(null, $jmapContactWebsite->getLabel());

        // Assert that the JMAP website value is correct
        $this->assertEquals('http://forrestgump.org/', $jmapContactWebsite->getValue());

        // Assert that the JMAP website "isDefault" property is correct
        // (expect false here, since our test vCard does not have any default website)
        $this->assertEquals(false, $jmapContactWebsite->getIsDefault());
    }

    public function testEmptyWebsiteMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a single website without a value
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        // Assert that for an empty website in vCard we don't have anything mapped in JMAP
        $this->assertEmpty($this->jmapContact->getOnline());
    }

    public function testCorrectImMapping()
    {
        $jmapContactIm = $this->jmapContact->getOnline()[1];

        // Assert that the JMAP IM mapped from the vCard IM is of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\Contact\ContactInformation', $jmapContactIm);

        // Assert that the JMAP IM type is correct
        // (it should always be "username")
        $this->assertEquals('username', $jmapContactIm->getType());

        // Assert that the JMAP IM label is correct
        // (expect null, since no label is set in our test vCard)
        $this->assertEquals(null, $jmapContactIm->getLabel());

        // Assert that the JMAP IM value is correct
        $this->assertEquals('xmpp:forrestgump@example.org', $jmapContactIm->getValue());

        // Assert that the JMAP IM "isDefault" property is correct
        // (expect false here, since our test vCard does not have any default IM)
        $this->assertEquals(false, $jmapContactIm->getIsDefault());
    }

    public function testEmptyImMapping()
    {
        // Change $this->vCard to be a different vCard for this test case
        // This other vCard contains a single IM without a value
        $this->vCard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_2.vcf', 'r')
        );

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jmapContact = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        // Assert that for an empty IM in vCard we don't have anything mapped in JMAP
        $this->assertEmpty($this->jmapContact->getOnline());
    }
}
