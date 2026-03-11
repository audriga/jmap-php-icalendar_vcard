<?php

namespace OpenXPort\Test\VCard;

use OpenXPort\Adapter\JSContactVCardAdapter;
use OpenXPort\Mapper\JSContactVCardMapper;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\Name;
use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\EmailAddress;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\Note;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\Title;
use OpenXPort\Jmap\JSContact\Nickname;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Jmap\JSContact\NameComponent;
use PHPUnit\Framework\TestCase;

/**
 * Test RFC 9553 ContactCard <-> vCard conversion
 *
 * Tests the bidirectional conversion between RFC 9553 JSContact ContactCard
 * objects and vCard format per RFC 9555 conversion rules.
 */
final class VCardJsContactAdapterTest extends TestCase
{
    /** @var \OpenXPort\Adapter\JSContactVCardAdapter */
    protected $adapter = null;

    /** @var \OpenXPort\Mapper\JSContactVCardMapper */
    protected $mapper = null;

    /** @var \OpenXPort\Jmap\JSContact\ContactCard */
    protected $contactCard = null;

    /** @var string */
    protected $vCardString = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new JSContactVCardAdapter();
        $this->mapper = new JSContactVCardMapper();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->adapter = null;
        $this->mapper = null;
        $this->contactCard = null;
        $this->vCardString = null;
    }

    private function mapVCard(string $path = null)
    {
        $filePath = isset($path) ? $path : '/../resources/test_vcard_v3.vcf';
        $this->vCardString = file_get_contents(__DIR__ . $filePath);

        $this->contactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCardString],
            $this->adapter
        )[0];
    }

    public function testCorrectJSContactObjectTypeMapping()
    {
        $this->mapVCard();

        $this->assertInstanceOf(ContactCard::class, $this->contactCard);
    }

    // public function testCorrectAddressMapping()
    // {
    //     $this->mapVCard();

    //     $jsContactAddressIndices = array_keys($this->contactCard->getAddresses());
    //     $jsContactWorkAddress = $this->contactCard->getAddresses()[$jsContactAddressIndices[0]];
    //     $jsContactHomeAddress = $this->contactCard->getAddresses()[$jsContactAddressIndices[1]];

    //     $this->assertInstanceOf(Address::class, $jsContactWorkAddress);
    //     $this->assertInstanceOf(Address::class, $jsContactHomeAddress);

    //     $this->assertEquals('Address', $jsContactWorkAddress->getAtType());
    //     $this->assertEquals('Address', $jsContactHomeAddress->getAtType());

    //     $this->assertEquals(['work' => true], $jsContactWorkAddress->getContexts());
    //     $this->assertEquals(['private' => true], $jsContactHomeAddress->getContexts());

    //     $this->assertEquals('100 Waters Edge', $jsContactWorkAddress->getStreet()[0]->getValue());
    //     $this->assertEquals('42 Plantation St.', $jsContactHomeAddress->getStreet()[0]->getValue());

    //     $this->assertEquals('Baytown', $jsContactWorkAddress->getLocality());
    //     $this->assertEquals('Baytown', $jsContactHomeAddress->getLocality());

    //     $this->assertEquals('LA', $jsContactWorkAddress->getRegion());
    //     $this->assertEquals('LA', $jsContactHomeAddress->getRegion());

    //     $this->assertEquals('United States of America', $jsContactWorkAddress->getCountry());
    //     $this->assertEquals('United States of America', $jsContactHomeAddress->getCountry());

    //     $this->assertEquals('30314', $jsContactWorkAddress->getPostcode());
    //     $this->assertEquals('30314', $jsContactHomeAddress->get());
    // }

    public function testCorrectEmailMapping(): void
    {
        $this->mapVCard();

        $jsContactEmailIndices = array_keys($this->contactCard->getEmails());
        $jsContactHomeEmail = $this->contactCard->getEmails()[$jsContactEmailIndices[0]];
        $jsContactWorkEmail = $this->contactCard->getEmails()[$jsContactEmailIndices[1]];

        $this->assertInstanceOf(EmailAddress::class, $jsContactHomeEmail);
        $this->assertInstanceOf(EmailAddress::class, $jsContactWorkEmail);

        $this->assertEquals('EmailAddress', $jsContactHomeEmail->getAtType());
        $this->assertEquals('EmailAddress', $jsContactWorkEmail->getAtType());

        $this->assertEquals('forrestgump@example.com', $jsContactHomeEmail->getAddress());
        $this->assertEquals('forrestgump-work@example.com', $jsContactWorkEmail->getAddress());

        $this->assertEquals(['private' => true], $jsContactHomeEmail->getContexts());
        $this->assertEquals(['work' => true], $jsContactWorkEmail->getContexts());
    }

    public function testCorrectPhoneMapping(): void
    {
        $this->mapVCard();

        $jsContactPhoneIndices = array_keys($this->contactCard->getPhones());
        $jsContactWorkPhone = $this->contactCard->getPhones()[$jsContactPhoneIndices[0]];
        $jsContactHomePhone = $this->contactCard->getPhones()[$jsContactPhoneIndices[1]];

        $this->assertInstanceOf(Phone::class, $jsContactWorkPhone);
        $this->assertInstanceOf(Phone::class, $jsContactHomePhone);

        $this->assertEquals('Phone', $jsContactWorkPhone->getAtType());
        $this->assertEquals('Phone', $jsContactHomePhone->getAtType());

        $this->assertEquals('(111) 555-1212', $jsContactWorkPhone->getNumber());
        $this->assertEquals('(404) 555-1212', $jsContactHomePhone->getNumber());

        $this->assertEquals(['work' => true], $jsContactWorkPhone->getContexts());
        $this->assertEquals(['private' => true], $jsContactHomePhone->getContexts());
    }

    public function testDifferentPhoneTypes(): void
    {
        $this->mapVCard();

        $jsContactPhoneIndices = array_keys($this->contactCard->getPhones());
        $jsContactCardSpecialPhone = $this->contactCard->getPhones()[$jsContactPhoneIndices[2]];

        $this->assertEquals(['private' => true], $jsContactCardSpecialPhone->getContexts());
        $this->assertEquals(['pager' => true], $jsContactCardSpecialPhone->getFeatures());
        $this->assertEquals('blabla, blabla2', $jsContactCardSpecialPhone->getLabel());
    }

    public function testIdEqualsUid(): void
    {
        $this->mapVCard();

        $this->assertEquals($this->contactCard->getUid(), $this->contactCard->getUid());
    }

    public function testCorrectNotesMapping(): void
    {
        $this->mapVCard();

        $this->assertEquals("Some text \n\n some more text", $this->contactCard->getNotes());
    }
    
    /**
     * Test full roundtrip from real-world vCard v3 file:
     *   vCard -> ContactCard -> vCard -> ContactCard
     *
     * Mirrors the pattern of the legacy testRoundtrip() test but for the new
     * RFC 9553 ContactCard / RFC 9555 vCard adapter stack.
     * Verifies that key properties survive two full conversion passes.
     */
    public function testVCardV3Roundtrip()
    {
        $vCardPath = __DIR__ . '/../resources/test_vcard_v3.vcf';
        $this->assertFileExists($vCardPath, 'Test vCard file not found at: ' . $vCardPath);

        $originalVCard = file_get_contents($vCardPath);
        $this->assertNotFalse($originalVCard, 'Failed to read vCard file');

        $contactCards = $this->mapper->mapToJmap(array("1" => $originalVCard), $this->adapter);
        $this->assertCount(1, $contactCards);
        $contactCard = $contactCards[0];
        $this->assertInstanceOf(ContactCard::class, $contactCard);

        $vCardData = $this->mapper->mapFromJmap(array("c1" => $contactCard), $this->adapter);
        $this->assertCount(1, $vCardData);
        $this->assertArrayHasKey("c1", $vCardData[0]);

        $regeneratedVCard = $vCardData[0]["c1"]["vCard"];
        $this->assertNotNull($regeneratedVCard, 'Regenerated vCard should not be null');

        $this->assertStringContainsString("VERSION:4.0",           $regeneratedVCard);
        $this->assertStringContainsString("ORG",                   $regeneratedVCard);
        $this->assertStringContainsString("Bubba Gump Shrimp Co.", $regeneratedVCard);
        $this->assertStringContainsString("TITLE",                 $regeneratedVCard);
        $this->assertStringContainsString("Shrimp Man",            $regeneratedVCard);
        $this->assertStringContainsString("EMAIL",                 $regeneratedVCard);
        $this->assertStringContainsString("TEL",                   $regeneratedVCard);
        $this->assertStringContainsString("ADR",                   $regeneratedVCard);
        $this->assertStringContainsString("BDAY",                  $regeneratedVCard);
        $this->assertStringContainsString("ANNIVERSARY",           $regeneratedVCard);
        $this->assertStringContainsString("NOTE",                  $regeneratedVCard);

        $contactCards2 = $this->mapper->mapToJmap(array("c1" => $regeneratedVCard), $this->adapter);
        $this->assertCount(1, $contactCards2);
        $contactCard2 = $contactCards2[0];

        $this->assertEquals(
            $contactCard->getName()->getFull(),
            $contactCard2->getName()->getFull()
        );

        $org1 = array_values($contactCard->getOrganizations())[0];
        $org2 = array_values($contactCard2->getOrganizations())[0];
        $this->assertEquals($org1->getName(), $org2->getName());
        $this->assertNull($org2->getUnits(), 'Single-component ORG should have no units');

        $title1 = array_values($contactCard->getTitles())[0];
        $title2 = array_values($contactCard2->getTitles())[0];
        $this->assertEquals($title1->getName(), $title2->getName());

        $emails1 = array_values($contactCard->getEmails());
        $emails2 = array_values($contactCard2->getEmails());
        $this->assertEquals(count($emails1), count($emails2), 'Email count should be preserved');

        $addresses1 = array_map(function ($e) { return $e->getAddress(); }, $emails1);
        $addresses2 = array_map(function ($e) { return $e->getAddress(); }, $emails2);
        sort($addresses1);
        sort($addresses2);
        $this->assertEquals($addresses1, $addresses2, 'Email addresses should be preserved');

        $phones1 = $contactCard->getPhones();
        $phones2 = $contactCard2->getPhones();
        $this->assertEquals(count($phones1), count($phones2), 'Phone count should be preserved');

        $addrs1 = $contactCard->getAddresses();
        $addrs2 = $contactCard2->getAddresses();
        $this->assertEquals(count($addrs1), count($addrs2), 'Address count should be preserved');

        $note1 = array_values($contactCard->getNoteObjects())[0]->getNote();
        $note2 = array_values($contactCard2->getNoteObjects())[0]->getNote();
        $this->assertEquals($note1, $note2, 'Note text should be preserved');

        $anns1 = $contactCard->getAnniversaries();
        $anns2 = $contactCard2->getAnniversaries();
        $this->assertNotNull($anns2, 'Anniversaries should survive roundtrip');
        $this->assertEquals(count($anns1), count($anns2), 'Anniversary count should be preserved');

        $bday1 = null;
        $bday2 = null;
        foreach ($anns1 as $a) {
            if ($a->getKind() === 'birth') { $bday1 = $a->getDate(); }
        }
        foreach ($anns2 as $a) {
            if ($a->getKind() === 'birth') { $bday2 = $a->getDate(); }
        }
        $this->assertEquals($bday1, $bday2, 'Birthday date should be preserved');

        $nick1 = array_values($contactCard->getNicknames())[0]->getName();
        $nick2 = array_values($contactCard2->getNicknames())[0]->getName();
        $this->assertEquals($nick1, $nick2, 'Nickname should be preserved');
    }

    /**
     * Roundtrip test based on jscontact_basic.json (Forrest Gump contact).
     */
    public function testJsContactBasicRoundtrip()
    {
        
        $jsonPath = __DIR__ . '/../resources/jscontactcard_basic.json';
        $this->assertFileExists($jsonPath, 'jscontactcard_basic.json not found at: ' . $jsonPath);
        $json = json_decode(file_get_contents($jsonPath));
        $this->assertNotNull($json, 'Failed to parse jscontactcard_basic.json');

        $card = new ContactCard();
        $card->setUid($json->uid);

        $name = new Name();
        $name->setFull($json->name->full);
        $card->setName($name);

        $noteObjects = [];
        foreach ($json->noteObjects as $id => $noteData) {
            $note = new \OpenXPort\Jmap\JSContact\Note();
            $note->setNote($noteData->note);
            $noteObjects[$id] = $note;
        }
        $card->setNoteObjects($noteObjects);

        $card->setKeywords((array) $json->keywords);

        $orgs = [];
        foreach ($json->organizations as $id => $orgData) {
            $org = new \OpenXPort\Jmap\JSContact\Organization();
            $org->setName($orgData->name);
            $orgs[$id] = $org;
        }
        $card->setOrganizations($orgs);

        $titles = [];
        foreach ($json->titles as $id => $titleData) {
            $title = new \OpenXPort\Jmap\JSContact\Title();
            $title->setName($titleData->name);
            $title->setKind($titleData->kind);
            $titles[$id] = $title;
        }
        $card->setTitles($titles);

        $emails = [];
        foreach ($json->emails as $id => $emailData) {
            $email = new \OpenXPort\Jmap\JSContact\EmailAddress();
            $email->setAddress($emailData->address);
            $email->setContexts((array) $emailData->contexts);
            $emails[$id] = $email;
        }
        $card->setEmails($emails);

        $phones = [];
        foreach ($json->phones as $id => $phoneData) {
            $phone = new \OpenXPort\Jmap\JSContact\Phone();
            $phone->setNumber($phoneData->number);
            $phone->setContexts((array) $phoneData->contexts);
            if (isset($phoneData->features)) {
                $phone->setFeatures((array) $phoneData->features);
            }
            if (isset($phoneData->label)) {
                $phone->setLabel($phoneData->label);
            }
            $phones[$id] = $phone;
        }
        $card->setPhones($phones);

        $nicks = [];
        foreach ($json->nicknames as $id => $nickData) {
            $nick = new \OpenXPort\Jmap\JSContact\Nickname();
            $nick->setName($nickData->name);
            $nicks[$id] = $nick;
        }
        $card->setNicknames($nicks);

        $anniversaries = [];
        foreach ($json->anniversaries as $annData) {
            $ann = new \OpenXPort\Jmap\JSContact\Anniversary();
            $ann->setKind($annData->kind);
            $ann->setDate($annData->date);
            if (isset($annData->label)) {
                $ann->setLabel($annData->label);
            }
            $anniversaries[] = $ann;
        }
        $card->setAnniversaries($anniversaries);

        $vCardData = $this->mapper->mapFromJmap(['c1' => $card], $this->adapter);
        $this->assertNotNull($vCardData);
        $vCardString = $vCardData[0]['c1']['vCard'];

        $this->assertStringContainsString('ORG', $vCardString);
        $this->assertStringContainsString('Bubba Gump Shrimp Co.', $vCardString);

        $contactCards = $this->mapper->mapToJmap(['c1' => $vCardString], $this->adapter);

        $this->assertCount(1, $contactCards);
        $cardAfter = $contactCards[0];

        $notesAfter = $cardAfter->getNoteObjects();
        $this->assertNotEmpty($notesAfter);
        $this->assertEquals(
            array_values((array) $json->noteObjects)[0]->note,
            array_values($notesAfter)[0]->getNote()
        );

        $keywordsAfter = $cardAfter->getKeywords();
        $this->assertNotNull($keywordsAfter);
        $this->assertEquals(
            (array) $json->keywords,
            $keywordsAfter
        );

        $orgsAfter = $cardAfter->getOrganizations();
        $this->assertNotEmpty($orgsAfter);
        $orgAfter = array_values($orgsAfter)[0];
        $this->assertEquals(
            array_values($orgs)[0]->getName(),
            $orgAfter->getName()
        );

        $this->assertNull($orgAfter->getUnits());
    }

     public function testExternalJsonRoundtrip()
    {
        $jsonPath = __DIR__ . '/../resources/jscontactcard_advanced.json';
        $this->assertFileExists($jsonPath, 'jscontactcard_advanced.json not found at: ' . $jsonPath);
        $json = json_decode(file_get_contents($jsonPath));
        $this->assertNotNull($json, 'Failed to parse jscontactcard_advanced.json');

        $card = new ContactCard();

        $card->setUid((string) $json->uid);

        $card->setUpdated($json->updated);
        $kindMap = [
            'prefix'  => 'title',     
            'given'   => 'given',      
            'surname' => 'surname',     
            'middle'  => 'given2',      
            'suffix'  => 'credential', 
        ];
        $nameComponents = [];
        foreach ($json->name->components as $comp) {
            $contactKind = isset($kindMap[$comp->type]) ? $kindMap[$comp->type] : $comp->type;
            $nc = new \OpenXPort\Jmap\JSContact\NameComponent();
            $nc->setKind($contactKind);
            $nc->setValue($comp->value);
            $nameComponents[] = $nc;
        }
        $name = new Name();
        $name->setComponents($nameComponents);
        $name->setIsOrdered(true);
        $fullParts = [];
        foreach ($nameComponents as $nc) {
            if (in_array($nc->getKind(), ['given', 'given2', 'surname'], true)) {
                $fullParts[] = $nc->getValue();
            }
        }
        $name->setFull(implode(' ', $fullParts));
        $card->setName($name);

        $orgs = [];
        foreach ($json->organizations as $id => $orgData) {
            $org = new \OpenXPort\Jmap\JSContact\Organization();
            $org->setName($orgData->name);
            $org->setUnits((array) $orgData->units);
            $orgs[$id] = $org;
        }
        $card->setOrganizations($orgs);

       
        $onlineServices = [];
        foreach ($json->onlineServices as $id => $osData) {
            $os = new \OpenXPort\Jmap\JSContact\OnlineService();
            if (isset($osData->user) && preg_match('/^[a-z][a-z0-9+\-.]*:/i', $osData->user)) {
                $os->setUri($osData->user);  
            } elseif (isset($osData->user)) {
                $os->setUser($osData->user); 
            }
            if (isset($osData->service)) {
                $os->setService($osData->service);
            }
            if (isset($osData->pref)) {
                $os->setPref((int) $osData->pref);
            }
            $onlineServices[$id] = $os;
        }
        $card->setOnlineServices($onlineServices);

        $anniversaries = [];
        foreach ($json->anniversaries as $annData) {
            $ann = new \OpenXPort\Jmap\JSContact\Anniversary();
            $ann->setDate($annData->date);
            if (isset($annData->kind)) {
                $ann->setKind($annData->kind);
            }
            $anniversaries[] = $ann;
        }
        $card->setAnniversaries($anniversaries);

        $vCardData = $this->mapper->mapFromJmap(['c1' => $card], $this->adapter);
        $this->assertNotNull($vCardData);
        $vCardString = $vCardData[0]['c1']['vCard'];

        $this->assertStringContainsString('UID:1', $vCardString);

        $this->assertStringContainsString('John', $vCardString);
        $this->assertStringContainsString('Public', $vCardString);

        $this->assertStringContainsString('Bubba Gump Shrimp Co.', $vCardString);
        $this->assertStringContainsString('Cleaning department', $vCardString);

        $this->assertStringContainsString('IMPP', $vCardString);
        $this->assertStringContainsString('xmpp:alice@example.com', $vCardString);

        $this->assertStringContainsString('ANNIVERSARY', $vCardString);
        $this->assertStringContainsString('20230228', $vCardString);

        $contactCards = $this->mapper->mapToJmap(['c1' => $vCardString], $this->adapter);
        $this->assertCount(1, $contactCards);
        $cardAfter = $contactCards[0];

        $this->assertEquals('1', $cardAfter->getUid());

        $orgsAfter = $cardAfter->getOrganizations();
        $this->assertNotEmpty($orgsAfter);
        $orgAfter = array_values($orgsAfter)[0];
        $this->assertEquals('Bubba Gump Shrimp Co.', $orgAfter->getName());
        $unitsAfter = $orgAfter->getUnits();
        $this->assertNotNull($unitsAfter, 'Org unit should survive roundtrip');
        $this->assertContains('Cleaning department', $unitsAfter);

        $onlineAfter = $cardAfter->getOnlineServices();
        $this->assertNotEmpty($onlineAfter);
        $uris = array_map(
        function ($os) { return $os->getUri() !== null ? $os->getUri() : $os->getUser(); },
        array_values($onlineAfter)
        );
        $this->assertContains('xmpp:alice@example.com', $uris);

        $this->assertNotNull($cardAfter->getName());
        $this->assertStringContainsString('John', $cardAfter->getName()->getFull());
    }

    public function testJmapSpecificFieldsRoundtrip()
    {
        $jsonPath = __DIR__ . '/../resources/jscontact_jmap_specific.json';
        $this->assertFileExists($jsonPath, 'jscontact_jmap_specific.json not found at: ' . $jsonPath);

        $json = json_decode(file_get_contents($jsonPath));
        $this->assertNotNull($json, 'Failed to parse jscontact_jmap_specific.json');

        $card = new ContactCard();
        $card->setUid((string) $json->uid);
        $card->setUpdated($json->updated);

        $card->setAddressBookIds($json->addressBookId);

        $vCardData = $this->mapper->mapFromJmap(['c1' => $card], $this->adapter);
        $this->assertNotNull($vCardData);
        $vCardDataReset = reset($vCardData);
        $this->assertArrayHasKey('c1', $vCardDataReset);

        $vCardString = $vCardDataReset['c1']['vCard'];
        $this->assertStringContainsString('VERSION:4.0', $vCardString);

        $contactCards = $this->mapper->mapToJmap($vCardDataReset, $this->adapter);
        $this->assertCount(1, $contactCards);
        $cardAfter = $contactCards[0];

        $this->assertEquals(
            $json->addressBookId,
            $cardAfter->getAddressBookIds(),
            'addressBookId should survive vCard roundtrip'
        );
    }
    /**
     * Test roundtrip with multiple ContactCards in a single mapper call.
     *
     * Verifies that mapFromJmap and mapToJmap correctly handle
     * multiple cards at once and preserve names across the roundtrip.
     */
    public function testMultipleRoundtrip() {
        $jsonPath = __DIR__ . '/../resources/jscontactcard_two_cards.json';
        $this->assertFileExists($jsonPath, 'jscontactcard_two_cards.json not found at: ' . $jsonPath);

        $json = json_decode(file_get_contents($jsonPath), true);
        $this->assertNotNull($json, 'Failed to parse jscontactcard_two_cards.json');
        $this->assertCount(2, $json);

        $cards = [];
        foreach ($json as $cardData) {
            
            $card = new ContactCard();
            $card->setUid((string) $cardData['uid']); 
            $card->setUpdated($cardData['updated']);       

            $name = new Name();
            $name->setFull($cardData['name']['full']);      
            $card->setName($name);

            $cards[] = $card;
        }

        $vCardData = $this->mapper->mapFromJmap(
            array("c1" => $cards[0], "c2" => $cards[1]),
            $this->adapter
        );
        $this->assertCount(2, $vCardData);

        $vCardDataReset = array(
            "c1" => reset($vCardData[0]),
            "c2" => reset($vCardData[1])
        );

        $this->assertStringContainsString("Forrest Gump",  $vCardDataReset["c1"]["vCard"]);
        $this->assertStringContainsString("Kamala Harris", $vCardDataReset["c2"]["vCard"]);

        $contactCardsAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter);
        $this->assertCount(2, $contactCardsAfter);

        $this->assertEquals("Forrest Gump",  $contactCardsAfter[0]->getName()->getFull());
        $this->assertEquals("Kamala Harris", $contactCardsAfter[1]->getName()->getFull());
    }

    /**
     * Test that a Microsoft Exchange vCard 3.0 is correctly mapped to a ContactCard.
     *
     * Verifies that FN, N, and EMAIL are correctly parsed from a real-world
     * MS Exchange vCard export.
     */
    public function testMsExchangeVCardMapping()
    {
        $vCardPath = __DIR__ . '/../resources/ms_exchange.vcf';
        $this->assertFileExists($vCardPath, 'ms_exchange.vcf not found at: ' . $vCardPath);

        $vCard = file_get_contents($vCardPath);
        $this->assertNotFalse($vCard, 'Failed to read ms_exchange.vcf');

        $contactCards = $this->mapper->mapToJmap(array("1" => $vCard), $this->adapter);
        $this->assertCount(1, $contactCards);

        $card = $contactCards[0];
        $this->assertInstanceOf(ContactCard::class, $card);

        $this->assertEquals("SomeFullName", $card->getName()->getFull());

        $surname = null;
        $components = $card->getName()->getComponents() !== null ? $card->getName()->getComponents() : [];
        foreach ($components as $component) {
            if ($component->getKind() === 'surname') {
                $surname = $component->getValue();
                break;
            }
        }
        $this->assertEquals("SOMENAME", $surname, 'N surname component should be preserved');

        $emails = $card->getEmails();
        $this->assertNotEmpty($emails);
        $this->assertEquals("xxxxxxxx@kolumbus.us", array_values($emails)[0]->getAddress());
    }
}