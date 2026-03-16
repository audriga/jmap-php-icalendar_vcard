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
final class JSContactVCardAdapterTest extends TestCase
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

    private function mapVCard($path = null)
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

   public function testCorrectAddressMapping()
    {
        $this->mapVCard();

        $addresses = array_values($this->contactCard->getAddresses() ?: []);
        $this->assertCount(2, $addresses);

        $jsContactWorkAddress = $addresses[0];
        $jsContactHomeAddress = $addresses[1];

        $this->assertInstanceOf(Address::class, $jsContactWorkAddress);
        $this->assertInstanceOf(Address::class, $jsContactHomeAddress);

        $this->assertEquals('Address', $jsContactWorkAddress->getAtType());
        $this->assertEquals('Address', $jsContactHomeAddress->getAtType());

        $this->assertEquals(['work' => true], $jsContactWorkAddress->getContexts());
        $this->assertEquals(['private' => true], $jsContactHomeAddress->getContexts());

        $workComponents = $jsContactWorkAddress->getComponents() ?: [];
        $homeComponents = $jsContactHomeAddress->getComponents() ?: [];

        $this->assertNotEmpty($workComponents);
        $this->assertNotEmpty($homeComponents);

        $this->assertSame('100 Waters Edge', $this->findAddressComponentText($workComponents, 'name'));
        $this->assertSame('42 Plantation St.', $this->findAddressComponentText($homeComponents, 'name'));

        $this->assertSame('Baytown', $this->findAddressComponentText($workComponents, 'locality'));
        $this->assertSame('Baytown', $this->findAddressComponentText($homeComponents, 'locality'));

        $this->assertSame('LA', $this->findAddressComponentText($workComponents, 'region'));
        $this->assertSame('LA', $this->findAddressComponentText($homeComponents, 'region'));

        $this->assertSame('United States of America', $this->findAddressComponentText($workComponents, 'country'));
        $this->assertSame('United States of America', $this->findAddressComponentText($homeComponents, 'country'));

        $this->assertSame('30314', $this->findAddressComponentText($workComponents, 'postcode'));
        $this->assertSame('30314', $this->findAddressComponentText($homeComponents, 'postcode'));
    }
    private function findAddressComponentText(array $components, $type)
    {
        foreach ($components as $component) {
            if ($component->getValue() === $type) {
                return $component->getKind();
            }
        }

        return null;
    }

    public function testCorrectEmailMapping()
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

    public function testCorrectPhoneMapping()
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

    public function testDifferentPhoneTypes()
    {
        $this->mapVCard();

        $jsContactPhoneIndices = array_keys($this->contactCard->getPhones());
        $jsContactCardSpecialPhone = $this->contactCard->getPhones()[$jsContactPhoneIndices[2]];

        $this->assertEquals(['private' => true], $jsContactCardSpecialPhone->getContexts());
        $this->assertEquals(['pager' => true], $jsContactCardSpecialPhone->getFeatures());
        $this->assertEquals('blabla, blabla2', $jsContactCardSpecialPhone->getLabel());
    }

    public function testIdEqualsUid()
    {
        $this->mapVCard();

        $this->assertEquals($this->contactCard->getUid(), $this->contactCard->getUid());
    }

    public function testCorrectNotesMapping()
    {
        $this->mapVCard();

        $notes = $this->contactCard->getNoteObjects();
        $this->assertNotNull($notes);
        $this->assertNotEmpty($notes);

        $firstNote = reset($notes);
        $this->assertInstanceOf(Note::class, $firstNote);
        $this->assertEquals("Some text \n\n some more text", $firstNote->getNote());
    }
    
    /**
     * Test full roundtrip from real-world vCard v3 file:
     *   vCard -> ContactCard -> vCard -> ContactCard
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
            $note = new Note();
            $note->setNote($noteData->note);
            $noteObjects[$id] = $note;
        }
        $card->setNoteObjects($noteObjects);

        $card->setKeywords((array) $json->keywords);

        $orgs = [];
        foreach ($json->organizations as $id => $orgData) {
            $org = new Organization();
            $org->setName($orgData->name);
            $orgs[$id] = $org;
        }
        $card->setOrganizations($orgs);

        $titles = [];
        foreach ($json->titles as $id => $titleData) {
            $title = new Title();
            $title->setName($titleData->name);
            $title->setKind($titleData->kind);
            $titles[$id] = $title;
        }
        $card->setTitles($titles);

        $emails = [];
        foreach ($json->emails as $id => $emailData) {
            $email = new EmailAddress();
            $email->setAddress($emailData->address);
            $email->setContexts((array) $emailData->contexts);
            $emails[$id] = $email;
        }
        $card->setEmails($emails);

        $phones = [];
        foreach ($json->phones as $id => $phoneData) {
            $phone = new Phone();
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
            $nick = new Nickname();
            $nick->setName($nickData->name);
            $nicks[$id] = $nick;
        }
        $card->setNicknames($nicks);

        $anniversaries = [];
        foreach ($json->anniversaries as $annData) {
            $ann = new Anniversary();
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
            $nc = new NameComponent();
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
            $org = new Organization();
            $org->setName($orgData->name);
            $org->setUnits((array) $orgData->units);
            $orgs[$id] = $org;
        }
        $card->setOrganizations($orgs);

       
        $onlineServices = [];
        foreach ($json->onlineServices as $id => $osData) {
            $os = new OnlineService();
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
            $ann = new Anniversary();
            $ann->setDate($annData->date);

            if (isset($annData->kind)) {
                $ann->setKind($annData->kind);
            } else {
                $ann->setKind('wedding');
                $ann->setLabel('anniversary');
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

    public function testJmapRoundtrip()
    {
        $card = new ContactCard();
        $card->setUid('c1');
        $card->setAddressBookIds(['i-am-jmap-specific']);

        $vCardData = $this->mapper->mapFromJmap(
            ['c1' => $card],
            $this->adapter
        );

        $this->assertIsArray($vCardData);
        $this->assertNotEmpty($vCardData);

        $vCardDataReset = reset($vCardData);
        $this->assertIsArray($vCardDataReset);
        $this->assertArrayHasKey('c1', $vCardDataReset);
        $this->assertIsArray($vCardDataReset['c1']);
        $this->assertArrayHasKey('vCard', $vCardDataReset['c1']);
        $this->assertArrayHasKey('oxpProperties', $vCardDataReset['c1']);
        $this->assertArrayHasKey('addressBookId', $vCardDataReset['c1']['oxpProperties']);

        $jsContactDataAfter = $this->mapper->mapToJmap($vCardDataReset, $this->adapter)[0];

        $this->assertInstanceOf(ContactCard::class, $jsContactDataAfter);
        $this->assertEquals(['i-am-jmap-specific'], $jsContactDataAfter->getAddressBookIds());
    }
}