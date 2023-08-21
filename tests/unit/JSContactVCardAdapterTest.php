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
        $this->adapter = new JSContactVCardAdapter();
        $this->mapper = new JSContactVCardMapper();
    }

    public function tearDown(): void
    {
        $this->vCard = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->vCardData = null;
        $this->jsContactCard = null;
    }

    private function mapVCard($path = null)
    {
        if (!is_null($path)) {
            $this->vCard = Reader::read(fopen(__DIR__ . $path, 'r'));
        } else {
            $this->vCard = Reader::read(fopen(__DIR__ . '/../resources/test_vcard_v3.vcf', 'r'));
        }

        $this->vCardData = array("1" => array("vCard" => $this->vCard->serialize()));
        $this->jsContactCard = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];
    }

    public function testCorrectJSContactObjectTypeMapping()
    {
        $this->mapVCard();

        $this->assertInstanceOf('OpenXPort\Jmap\JSContact\Card', $this->jsContactCard);
    }

    public function testCorrectAddressMapping()
    {
        $this->mapVCard();

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
        $this->mapVCard();

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
        $this->mapVCard();

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
        $this->mapVCard();

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
        $this->mapVCard();

        $this->assertEquals($this->jsContactCard->getId(), $this->jsContactCard->getUid());
    }

    public function testCorrectNotesMapping()
    {
        $this->mapVCard();

        // Assert that the value of the JSContact "notes" property is the one we expect
        $this->assertEquals($this->jsContactCard->getNotes(), "Some text \n\n some more text");
    }

    /* *
     * Map JSContact -> vCard -> JSContact
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects
     */
    public function testRoundtrip()
    {
        $jsContactData = json_decode(file_get_contents(__DIR__ . '/../resources/jscontact_basic.json'));

        $vCardData = $this->mapper->mapFromJmap(array("c1" => $jsContactData), $this->adapter);

        $vCardDataReset = reset($vCardData);
        $this->assertNotNull($vCardDataReset["c1"]["vCard"]);
        $this->assertStringContainsString("ORG", $vCardDataReset["c1"]["vCard"]);

        $jsContactDataAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter)[0];

        // Assert that the value of notes is still the same
        $this->assertEquals($jsContactData->notes, $jsContactDataAfter->getNotes());
        $this->assertEquals((array) $jsContactData->categories, $jsContactDataAfter->getCategories());

        $this->assertEquals(
            $jsContactData->organizations->{"9d5ed678fe57bcca610140957afab571"}->name,
            array_values($jsContactDataAfter->getOrganizations())[0]->getName()
        );
        $this->assertNull(array_values($jsContactDataAfter->getOrganizations())[0]->getUnits());
    }

    /* *
     * More complex mapping of JSContact -> vCard -> JSContact
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects
     */
    public function testAdvancedRoundtrip()
    {
        $jsContactData = json_decode(file_get_contents(__DIR__ . '/../resources/jscontact_advanced.json'));

        $vCardData = $this->mapper->mapFromJmap(array("c1" => $jsContactData), $this->adapter);

        $vCardDataReset = reset($vCardData);

        $this->assertNotNull($vCardDataReset["c1"]["vCard"]);
        $this->assertStringContainsString("DERIVED", $vCardDataReset["c1"]["vCard"]);
        $this->assertStringContainsString("IMPP", $vCardDataReset["c1"]["vCard"]);

        $jsContactDataAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter)[0];

        // Assert that fullName gets derived from name (roughly)
        $this->assertGreaterThan(0, strlen($jsContactDataAfter->getFullName()));

        $servicesAsArray = array_values($jsContactDataAfter->getOnlineServices());
        $this->assertEquals("xmpp:alice@example.com", $servicesAsArray[0]->getUser());
        $this->assertEquals("Skype", $servicesAsArray[1]->getService());

        $this->assertEquals(
            $jsContactData->organizations->{"9d5ed678fe57bcca610140957afab571"}->name,
            array_values($jsContactDataAfter->getOrganizations())[0]->getName()
        );
        $this->assertEquals(
            "Cleaning department",
            array_values($jsContactDataAfter->getOrganizations())[0]->getUnits()[0]
        );
    }

    /* *
     * Roundtripping of Jmap-specific properties
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects
     */
    public function testJmapRoundtrip()
    {
        $jsContactData = json_decode(file_get_contents(__DIR__ . '/../resources/jscontact_jmap_specific.json'));

        $vCardData = $this->mapper->mapFromJmap(array("c1" => $jsContactData), $this->adapter);

        $vCardDataReset = reset($vCardData);

        $this->assertNotNull($vCardDataReset["c1"]["vCard"]);

        $jsContactDataAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter)[0];

        $this->assertEquals("i-am-jmap-specific", $jsContactDataAfter->getAddressBookId());
    }

    /* *
     * Mapping MS-Exchange-specific vCards
     */
    public function testCorrectMsExchangeMapping()
    {
        $this->mapVCard('/../resources/ms_exchange.vcf');

        $this->assertEquals("SomeFullName", $this->jsContactCard->getFullName());
    }

    /* *
     * Mapping of two cards JSContact -> vCard -> JSContact
     * TODO Once we add a mapper from stdClass to our JmapObjects we should be able to compare the whole objects
     */
    public function testMultipleRoundtrip()
    {
        $jsContactData = json_decode(file_get_contents(__DIR__ . '/../resources/jscontact_two_cards.json'));

        $vCardData = $this->mapper->mapFromJmap(
            array("c1" => $jsContactData[0], "c2" => $jsContactData[1]),
            $this->adapter
        );

        $vCardDataReset = array("c1" => reset($vCardData[0]), "c2" => reset($vCardData[1]));

        $this->assertStringContainsString("Forrest Gump", $vCardDataReset["c1"]["vCard"]);

        $jsContactDataAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter);

        $this->assertEquals("Forrest Gump", $jsContactDataAfter[0]->getFullName());
        $this->assertEquals("Kamala Harris", $jsContactDataAfter[1]->getFullName());
    }
}
