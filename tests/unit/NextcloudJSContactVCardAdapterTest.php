<?php

namespace OpenXPort\Test\VCard;

use OpenXPort\Adapter\NextcloudJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;
use \OpenXPort\Jmap\JSContact\Name;
use \OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Mapper\JSContactVCardMapper;
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

    /** @var \OpenXPort\Jmap\JSContact\ContactCard */
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

    public function testReadNextcloudSpecificXSocialProfile()
    {
        $this->mapVCard();

        $this->assertInstanceOf(ContactCard::class, $this->jsContactCard);

        $onlineServices = $this->jsContactCard->getOnlineServices() ?: array();
        $this->assertNotEmpty($onlineServices);

        $usernames = array();
        $uris = array();
        $labels = array();

        foreach ($onlineServices as $id => $service) {
            $user = $service->getUser();
            $uri = $service->getUri();
            $label = method_exists($service, 'getLabel') ? $service->getLabel() : null;

            if ($user !== null && $user !== '') {
                array_push($usernames, $user);
            }

            if ($uri !== null && $uri !== '') {
                array_push($uris, $uri);
            }

            if ($label !== null && $label !== '') {
                array_push($labels, $label);
            }
        }

        // Assert that for an empty IM in vCard we don't have anything mapped in JMAP
        $this->assertContains(
            "https://github.com/apache/james-project",
            array_merge($usernames, $uris)
        );

        $this->assertContains('X-SOCIALPROFILE', $labels);
    }

    public function testNextcloudSocialProfileRoundtrip()
    {
        $vCardString = file_get_contents(__DIR__ . '/../resources/nextcloud_socialprofile.vcf');
        $this->assertNotFalse($vCardString, 'Failed to read nextcloud_socialprofile.vcf');
        $this->assertStringContainsString('BEGIN:VCARD', $vCardString);
        $this->assertStringContainsString('END:VCARD', $vCardString);
        
        $this->vCard = Reader::read($vCardString);
        $this->vCardData = array("1" => array("vCard" => $this->vCard->serialize()));

        // Convert vCard -> JSContact
        $this->adapter->setVCard(reset($this->vCardData)["vCard"]);
        $card = new ContactCard();
        $this->adapter->getOnlineServicesToJmap($card);

        $onlineServices = $card->getOnlineServices();
        $this->assertNotEmpty($onlineServices, "Online services should not be empty");

        $twitterService = null;
        foreach ($onlineServices as $service) {
            if ($service->getService() === 'twitter') {
                $twitterService = $service;
                break;
            }
        }
        $this->assertNotNull($twitterService, "Twitter service should exist");
        $this->assertEquals('johndoe', $twitterService->getUser(), "Twitter username should be 'johndoe'");

        // Convert JSContact -> vCard (roundtrip)
        $this->adapter->reset();
        $this->adapter->setOnlineServicesFromJmap($card);

        $socialProfiles = $this->adapter->getVCard();
        $this->assertStringContainsString('X-SOCIALPROFILE', $socialProfiles);
        $this->assertStringContainsString('twitter', strtolower($socialProfiles));
        $this->assertStringContainsString('johndoe', $socialProfiles);
    }

    /**
     * Test JSContact to vCard conversion
     * Read JSContact from JSON file and convert to vCard
     */
    public function testJSContactToNextcloudVCard()
    {
        $jsonString = file_get_contents(__DIR__ . '/../resources/jscontactcard_nc.json');
        $this->assertNotFalse($jsonString, 'Failed to read jscontactcard_nc.json');
        
        $jsonData = json_decode($jsonString, true);
        $this->assertNotNull($jsonData, 'Failed to decode JSON');
        $this->assertIsArray($jsonData, 'JSON should decode to array');

        $card = new ContactCard();
        
        $card->setUid($jsonData['uid']);
        
        $name = new Name();
        $name->setFull($jsonData['name']['full']);
        $card->setName($name);
        
        $services = array();
        foreach ($jsonData['onlineServices'] as $key => $serviceData) {
            $service = new OnlineService();
            $service->setService($serviceData['service']);
            $service->setUser($serviceData['user']);
            $service->setLabel($serviceData['label']);
            $services[$key] = $service;
        }
        $card->setOnlineServices($services);

        // JSContact -> vCard
        $this->adapter->reset();
        $this->adapter->setOnlineServicesFromJmap($card);
        
        $vCardResult = $this->adapter->getVCard();
        $this->assertNotEmpty($vCardResult, "vCard should not be empty");
        $this->assertStringContainsString('X-SOCIALPROFILE', $vCardResult);
        $this->assertStringContainsString('twitter', strtolower($vCardResult));
        $this->assertStringContainsString('janesmith', $vCardResult);
    }
}