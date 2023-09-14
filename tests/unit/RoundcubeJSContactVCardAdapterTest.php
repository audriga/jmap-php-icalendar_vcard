<?php

namespace OpenXPort\Test\VCard;

use OpenXPort\Adapter\RoundcubeJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\Audriga\Card;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Mapper\RoundcubeJSContactVCardMapper;
use OpenXPort\Test\VCard\TestUtils;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;

/**
 * Roundcube-specific converting between vCard <-> JSContact
 */
final class RoundcubeJSContactVCardAdapterTest extends TestCase
{
    /** @var \Sabre\VObject\Component\VCard */
    protected $vCard = null;

    /** @var \OpenXPort\Adapter\RoundcubeJSContactVCardAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\RoundcubeJSContactVCardMapper */
    protected $mapper = null;

    /** @var array */
    protected $vCardData = null;

    /** @var \OpenXPort\Jmap\JSContact\Card */
    protected $jsContactCard = null;

    public function setUp(): void
    {
        require_once("TestUtils.php");

        $this->adapter = new RoundcubeJSContactVCardAdapter();
        $this->mapper = new RoundcubeJSContactVCardMapper();
    }

    public function tearDown(): void
    {
        $this->vCard = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->vCardData = null;
        $this->jsContactCard = null;
    }

    /**
     * This test aims to check that in the case of Roundcube the value 'other' is not added to 'contexts'
     * of 'phones' entries when 'contexts' is null.
     * It also checks that the 'features' property remains intact for entries of 'phones'.
     */
    public function testCorrectRoundcubeRoundtripPhones()
    {
        $this->jsContactCard = new Card();
        $pagerPhoneEntry = new Phone();
        $pagerPhoneEntry->setAtType("Phone");
        $pagerPhoneEntry->setPhone("123-pager");
        $pagerPhoneEntry->setFeatures(["pager" => true]);

        $pagerOtherPhoneEntry = new Phone();
        $pagerOtherPhoneEntry->setAtType("Phone");
        $pagerOtherPhoneEntry->setPhone("123-other");

        $this->jsContactCard->setPhones([
            "123-pager" => $pagerPhoneEntry,
            "123-other" => $pagerOtherPhoneEntry
        ]);

        $jsContactData = array("c1" => json_decode(json_encode($this->jsContactCard)));

        $this->vCardData = $this->mapper->mapFromJmap($jsContactData, $this->adapter);

        $resultingJsContactCard = $this->mapper->mapToJmap(reset($this->vCardData), $this->adapter)[0];

        $this->assertEquals(
            array_values($this->jsContactCard->getPhones()),
            array_values($resultingJsContactCard->getPhones())
        );
    }

    /**
     * Make sure that no exception is thrown for each of the config options and that they do map some jscontact result.
     */
    public function testConfigCleanVCard(): void
    {
        $this->vCard = file_get_contents(__DIR__ . "/../resources/rc-vcard.vcf");

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), $this->adapter);

        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('ignoreInvalidLines'));

        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('ignoreInvalidVCards'));

        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('strict', true));

        $this->assertNotNull($this->jsContactCard);
    }

    /**
     * Check that the right exception is thrown for an invalid vCard and that it is not if the line is ignored.
     */
    public function testConfigInvalidLine(): void
    {
        $this->vCard = file_get_contents(__DIR__ . "/../resources/rc-vcard-invalid-line.vcf");

        $this->expectException(ParseException::class);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), $this->adapter);

        $this->assertNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('ignoreInvalidLines'));

        $this->assertNotNull($this->jsContactCard);
    }

    /**
     * Check that the right exceptions are thrown and that they are not thrown if the entire card gets ignored.
     */
    public function testConfigInvalidVCard(): void
    {
        $this->vCard = file_get_contents(__DIR__ . "/../resources/rc-vcard-invalid-card.vcf");

        $this->assertNull($this->jsContactCard);

        $this->expectException(ParseException::class);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('ignoreInvalidLines', true));

        $this->assertNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(array("c1" => $this->vCard), new RoundcubeJSContactVCardAdapter('ignoreInvalidCards'));
        
        $this->assertNotNull($this->jsContactCard);
    }
}
