<?php

namespace OpenXPort\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenXPort\Adapter\RoundcubeJSContactVCardAdapter;
use OpenXPort\Mapper\RoundcubeJSContactVCardMapper;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\Name;
use OpenXPort\Jmap\JSContact\NameComponent;
use OpenXPort\Jmap\JSContact\Nickname;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\OrgUnit;
use OpenXPort\Jmap\JSContact\Title;
use OpenXPort\Jmap\JSContact\Note;
use OpenXPort\Jmap\JSContact\EmailAddress;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Jmap\JSContact\PersonalInformation;
use Sabre\VObject;


/**
 * Round-trip tests for RoundcubeJSContactVCardAdapter.
 */
final class RoundcubeJSContactVCardAdapterTest extends TestCase
{
    // Helpers
   /** @var \Sabre\VObject\Component\VCard */
    protected $vCard = null;

    /** @var \OpenXPort\Adapter\RoundcubeJSContactVCardAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\RoundcubeJSContactVCardMapper */
    protected $mapper = null;

    /** @var array */
    protected $vCardData = null;

    /** @var \OpenXPort\Jmap\JSContact\ContactCard */
    protected $jsContactCard = null;

    public function setUp(): void
    {
        $this->adapter       = new RoundcubeJSContactVCardAdapter();
        $this->mapper        = new RoundcubeJSContactVCardMapper();
    }

    public function tearDown(): void
    {
        $this->vCard         = null;
        $this->adapter       = null;
        $this->mapper        = null;
        $this->vCardData     = null;
        $this->jsContactCard = null;
    }

    /**
 * Test that phone features and contexts survive a roundtrip through vCard.
 *
 * Verifies that 'pager' feature is preserved and that null contexts do not
 * get polluted with 'other' during conversion.
 */
    public function testCorrectRoundcubeRoundtripPhones()
    {
        $this->jsContactCard = new ContactCard();

        $pagerPhone = new Phone();
        $pagerPhone->setNumber('123-pager');
        $pagerPhone->setFeatures(['pager' => true]);

        $otherPhone = new Phone();
        $otherPhone->setNumber('123-other');

        $this->jsContactCard->setPhones([
            '123-pager' => $pagerPhone,
            '123-other' => $otherPhone,
        ]);

        $this->vCardData = $this->mapper->mapFromJmap(
            ['c1' => $this->jsContactCard],
            $this->adapter
        );

        $vCardDataReset = reset($this->vCardData);

        // mapFromJmap wraps output as ['c1' => ['vCard' => string, ...]].
        // Unwrap to a plain contactId => vCardString map for mapToJmap.
        $unwrapped = [];
        foreach ($vCardDataReset as $id => $payload) {
            $unwrapped[$id] = is_array($payload) ? $payload['vCard'] : $payload;
        }

        $resultingCard = $this->mapper->mapToJmap($unwrapped, $this->adapter)[0];

        $this->assertEquals(
            array_values($this->jsContactCard->getPhones()),
            array_values($resultingCard->getPhones())
        );
    }

    /**
     * Make sure that no exception is thrown for each of the config options
     * and that they do map some jscontact result.
     */
    public function testConfigCleanVCard()
    {
        $this->vCard = file_get_contents(__DIR__ . '/../resources/rc-vcard.vcf');
        $this->assertNotFalse($this->vCard, 'Failed to read rc-vcard.vcf');

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            $this->adapter
        );
        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidLines')
        );
        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidVCards')
        );
        $this->assertNotNull($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('strict', true)
        );
        $this->assertNotNull($this->jsContactCard);
    }

    /**
     * Check that no exception is thrown when the adapter is configured to ignore invalid lines,
     * and that a result is still returned.
     */
    public function testConfigInvalidLineIgnored()
    {
        $this->vCard = file_get_contents(__DIR__ . '/../resources/rc-vcard-invalid-line.vcf');

        $tolerantAdapter = new RoundcubeJSContactVCardAdapter('ignoreInvalidLines');

        $result = $this->mapper->mapToJmap(['c1' => $this->vCard], $tolerantAdapter);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Check that no exception is thrown when the adapter is configured to ignore
     * invalid cards entirely, and that a result is still returned.
     */
    /**
     * Check that no exception is thrown when the adapter is configured to ignore
     * invalid vCards entirely (ignoreInvalidVCards), and that a result is returned.
     */
    public function testConfigInvalidVCardIgnoredWithIgnoreInvalidVCards()
    {
        $this->vCard = file_get_contents(__DIR__ . '/../resources/rc-vcard-invalid-card.vcf');

        $result = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidVCards')
        );

        $this->assertNotNull($result);
    }
}