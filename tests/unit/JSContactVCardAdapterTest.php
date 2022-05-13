<?php

namespace OpenXPort\Test\VCard;

use OpenXPort\Adapter\JSContactVCardAdapter;
use OpenXPort\Mapper\JSContactVCardMapper;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

/**
 * Generic converting between vCard <-> JSContact
 */
final class JSContactVCardAdapterTest extends TestCase
{
    /** @var \Sabre\VObject\Component\VCard */
    protected $vCard = null;

    /** @var \OpenXPort\Adapter\JSContactVCardAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\JSContactVCardMapper */
    protected $mapper = null;

    /** @var array */
    protected $vCardData = null;

    /** @var \OpenXPort\Jmap\JSContact\Card */
    protected $jsContactCard = null;

    public function setUp(): void
    {
        $this->vCard = Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard_v3.vcf', 'r')
        );

        $this->adapter = new JSContactVCardAdapter();
        $this->mapper = new JSContactVCardMapper();

        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jsContactCard = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];
    }

    public function tearDown(): void
    {
        $this->vCard = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->vCardData = null;
        $this->jsContactCard = null;
    }

    public function testCorrectJSContactObjectTypeMapping()
    {
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Card', $this->jsContactCard);
    }

    public function testCorrectAddressMapping()
    {
        $jsContactAddressIndices = array_keys($this->jsContactCard->getAddresses());
        $jsContactWorkAddress = $this->jsContactCard->getAddresses()[$jsContactAddressIndices[0]];
        $jsContactHomeAddress = $this->jsContactCard->getAddresses()[$jsContactAddressIndices[1]];

        // Assert that the JSContact addresses mapped from the vCard addresses are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Address', $jsContactWorkAddress);
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Address', $jsContactHomeAddress);

        // Assert that the @type property is properly set
        $this->assertEquals('Address', $jsContactWorkAddress->getAtType());
        $this->assertEquals('Address', $jsContactHomeAddress->getAtType());

        // Assert that the JSContact address types are correct
        $this->assertEquals(
            ['work' => true],
            $jsContactWorkAddress->getContexts()
        );

        $this->assertEquals(
            ['private' => true],
            $jsContactHomeAddress->getContexts()
        );

        // Assert correctness of the addresses' street components
        $this->assertEquals("100 Waters Edge", $jsContactWorkAddress->getStreet()[0]->getValue());
        $this->assertEquals("42 Plantation St.", $jsContactHomeAddress->getStreet()[0]->getValue());

        // Assert correctness of the locality property
        $this->assertEquals("Baytown", $jsContactWorkAddress->getLocality());
        $this->assertEquals("Baytown", $jsContactHomeAddress->getLocality());

        // Assert correctness of the region property
        $this->assertEquals("LA", $jsContactWorkAddress->getRegion());
        $this->assertEquals("LA", $jsContactHomeAddress->getRegion());

        // Assert correctness of the country property
        $this->assertEquals("United States of America", $jsContactWorkAddress->getCountry());
        $this->assertEquals("United States of America", $jsContactHomeAddress->getCountry());

        // Assert correctness of the postcode property
        $this->assertEquals("30314", $jsContactWorkAddress->getPostcode());
        $this->assertEquals("30314", $jsContactHomeAddress->getPostcode());
    }

    public function testCorrectEmailMapping()
    {
        $jsContactEmailIndices = array_keys($this->jsContactCard->getEmails());
        $jsContactHomeEmail = $this->jsContactCard->getEmails()[$jsContactEmailIndices[0]];
        $jsContactWorkEmail = $this->jsContactCard->getEmails()[$jsContactEmailIndices[1]];

        // Assert that the JSContact email addresses are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\EmailAddress', $jsContactWorkEmail);
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\EmailAddress', $jsContactHomeEmail);

        // Assert correctness of the @type property
        $this->assertEquals('EmailAddress', $jsContactHomeEmail->getAtType());
        $this->assertEquals('EmailAddress', $jsContactWorkEmail->getAtType());

        // Assert that email address values are correct
        $this->assertEquals('forrestgump@example.com', $jsContactHomeEmail->getEmail());
        $this->assertEquals('forrestgump-work@example.com', $jsContactWorkEmail->getEmail());

        // Assert that the email address contexts are correct
        $this->assertEquals(
            ['private' => true],
            $jsContactHomeEmail->getContexts()
        );
        $this->assertEquals(
            ['work' => true],
            $jsContactWorkEmail->getContexts()
        );
    }

    public function testCorrectPhoneMapping()
    {
        $jsContactPhoneIndices = array_keys($this->jsContactCard->getPhones());
        $jsContactWorkPhone = $this->jsContactCard->getPhones()[$jsContactPhoneIndices[0]];
        $jsContactHomePhone = $this->jsContactCard->getPhones()[$jsContactPhoneIndices[1]];

        // Assert that the JSContact phones are of the correct type
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Phone', $jsContactWorkPhone);
        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Phone', $jsContactHomePhone);

        // Assert correctness of the @type property
        $this->assertEquals('Phone', $jsContactWorkPhone->getAtType());
        $this->assertEquals('Phone', $jsContactHomePhone->getAtType());

        // Assert that the phone values are correct
        $this->assertEquals('(111) 555-1212', $jsContactWorkPhone->getPhone());
        $this->assertEquals('(404) 555-1212', $jsContactHomePhone->getPhone());

        // Assert that the phone contexts are correct
        $this->assertEquals(
            ['work' => true],
            $jsContactWorkPhone->getContexts()
        );
        $this->assertEquals(
            ['private' => true],
            $jsContactHomePhone->getContexts()
        );
    }

    public function testDifferentPhoneTypes()
    {
        $jsContactPhoneIndices = array_keys($this->jsContactCard->getPhones());
        $jsContactCardSpecialPhone = $this->jsContactCard->getPhones()[$jsContactPhoneIndices[2]];

        $this->assertEquals(
            ['private' => true],
            $jsContactCardSpecialPhone->getContexts()
        );

        $this->assertEquals(
            ['pager' => true],
            $jsContactCardSpecialPhone->getFeatures()
        );

        $this->assertEquals(
            'blabla,blabla2',
            $jsContactCardSpecialPhone->getLabel()
        );
    }

    public function testIdEqualsUid()
    {
        $this->assertEquals($this->jsContactCard->getId(), $this->jsContactCard->getUid());
    }

    public function testCorrectNotesMapping()
    {
        $this->vCardData = array("1" => $this->vCard->serialize());
        $this->jsContactCard = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];

        // Assert that the value of the JSContact "notes" property is the one we expect
        $this->assertEquals($this->jsContactCard->getNotes(), "Some text \n\n some more text");
    }
}
