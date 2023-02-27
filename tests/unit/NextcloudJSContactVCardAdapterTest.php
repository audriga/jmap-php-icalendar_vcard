<?php

namespace OpenXPort\Test\VCard;

use OpenXPort\Adapter\NextcloudJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\Audriga\Card;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Mapper\JSContactVCardMapper;
use OpenXPort\Test\VCard\TestUtils;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

/**
 * Nextcloud-specific converting between vCard <-> JSContact
 */
final class NextcloudJSContactVCardAdapterTest extends TestCase
{
    /** @var \Sabre\VObject\Component\VCard */
    protected $vCard = null;

    /** @var \OpenXPort\Adapter\NextcloudJSContactVCardAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\JSContactVCardMapper */
    protected $mapper = null;

    /** @var array */
    protected $vCardData = null;

    /** @var \OpenXPort\Jmap\JSContact\Card */
    protected $jsContactCard = null;

    public function setUp(): void
    {
        $this->adapter = new NextcloudJSContactVCardAdapter();
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
            $this->vCard = Reader::read(fopen(__DIR__ . '/../resources/nextcloud_vcard.vcf', 'r'));
        }

        $this->vCardData = array("1" => array("vCard" => $this->vCard->serialize()));
        $this->jsContactCard = $this->mapper->mapToJmap($this->vCardData, $this->adapter)[0];
    }

    public function testReadNextcloudSpecific()
    {
        $this->mapVCard();

        $usernames = [];
        foreach ($this->jsContactCard->getOnlineServices() as $id => $service) {
            array_push($usernames, $service->getUser());
        }
        // Assert that for an empty IM in vCard we don't have anything mapped in JMAP
        $this->assertContains(
            "https://github.com/apache/james-project",
            $usernames
        );
    }
}
