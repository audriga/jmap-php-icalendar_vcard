<?php

namespace OpenXPort\Tests\Unit;

use OpenXPort\Adapter\RoundcubeJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Mapper\RoundcubeJSContactVCardMapper;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip tests for RoundcubeJSContactVCardAdapter.
 */
final class RoundcubeJSContactVCardAdapterTest extends TestCase
{
    /** @var string|\Sabre\VObject\Component\VCard|null */
    protected $vCard = null;

    /** @var RoundcubeJSContactVCardAdapter|null */
    protected $adapter = null;

    /** @var RoundcubeJSContactVCardMapper|null */
    protected $mapper = null;

    /** @var array|null */
    protected $vCardData = null;

    /** @var ContactCard|null */
    protected $jsContactCard = null;

    protected function setUp(): void
    {
        $this->adapter = new RoundcubeJSContactVCardAdapter();
        $this->mapper = new RoundcubeJSContactVCardMapper();
    }

    protected function tearDown(): void
    {
        $this->vCard = null;
        $this->adapter = null;
        $this->mapper = null;
        $this->vCardData = null;
        $this->jsContactCard = null;
    }

    /**
     * Test that phone features and contexts survive a roundtrip through vCard.
     *
     * Verifies that the "pager" feature is preserved and that a phone without
     * explicit contexts does not gain an unwanted "other" context during conversion.
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

        // mapFromJmap() returns:
        // [
        //     [
        //         'c1' => <vcard string or payload>
        //     ]
        // ]
        //
        // Flatten that into the plain map expected by mapToJmap():
        // [
        //     'c1' => <vcard string>
        // ]
        $unwrapped = [];
        foreach ($this->vCardData as $entry) {
            foreach ($entry as $id => $payload) {
                $unwrapped[$id] = is_array($payload) && array_key_exists('vCard', $payload)
                    ? $payload['vCard']
                    : $payload;
            }
        }

        $resultingCards = $this->mapper->mapToJmap($unwrapped, $this->adapter);

        $this->assertNotEmpty($resultingCards);
        $this->assertInstanceOf(ContactCard::class, $resultingCards[0]);

        $resultingCard = $resultingCards[0];

        $this->assertEquals(
            array_values($this->jsContactCard->getPhones()),
            array_values($resultingCard->getPhones())
        );
    }

    /**
     * Make sure that no exception is thrown for each config option
     * and that each returns some JSContact result.
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
        $this->assertNotEmpty($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidLines')
        );
        $this->assertNotNull($this->jsContactCard);
        $this->assertNotEmpty($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidVCards')
        );
        $this->assertNotNull($this->jsContactCard);
        $this->assertNotEmpty($this->jsContactCard);

        $this->jsContactCard = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('strict', true)
        );
        $this->assertNotNull($this->jsContactCard);
        $this->assertNotEmpty($this->jsContactCard);
    }

    /**
     * Check that no exception is thrown when the adapter is configured
     * to ignore invalid lines and that a result is still returned.
     */
    public function testConfigInvalidLineIgnored()
    {
        $this->vCard = file_get_contents(__DIR__ . '/../resources/rc-vcard-invalid-line.vcf');
        $this->assertNotFalse($this->vCard, 'Failed to read rc-vcard-invalid-line.vcf');

        $tolerantAdapter = new RoundcubeJSContactVCardAdapter('ignoreInvalidLines');

        $result = $this->mapper->mapToJmap(['c1' => $this->vCard], $tolerantAdapter);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Check that no exception is thrown when the adapter is configured
     * to ignore invalid vCards entirely and that a result is still returned.
     */
    public function testConfigInvalidVCardIgnoredWithIgnoreInvalidVCards()
    {
        $this->vCard = file_get_contents(__DIR__ . '/../resources/rc-vcard-invalid-card.vcf');
        $this->assertNotFalse($this->vCard, 'Failed to read rc-vcard-invalid-card.vcf');

        $result = $this->mapper->mapToJmap(
            ['c1' => $this->vCard],
            new RoundcubeJSContactVCardAdapter('ignoreInvalidVCards')
        );

        $this->assertNotNull($result);
    }

    public function testMinimalRoundcubeVCardFromFileMapsToContactCard()
    {
        $vCard = file_get_contents(__DIR__ . '/../resources/rc_vcard_basic.vcf');
        $this->assertNotFalse($vCard, 'Failed to read rc_vcard_basic.vcf');

        $mapper = new RoundcubeJSContactVCardMapper();
        $adapter = new RoundcubeJSContactVCardAdapter();

        $result = $mapper->mapToJmap(
            ['c1' => $vCard],
            $adapter
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ContactCard::class, $result[0]);

        $card = $result[0];

        $this->assertSame('c1', $card->getUid());

        $name = $card->getName();
        $this->assertNotNull($name);

        $emails = $card->getEmails();
        $this->assertIsArray($emails);
        $this->assertNotEmpty($emails);

        $firstEmail = reset($emails);
        $this->assertNotFalse($firstEmail);
        $this->assertSame('jane.doe@example.com', $firstEmail->getAddress());

        $phones = $card->getPhones();
        $this->assertIsArray($phones);
        $this->assertNotEmpty($phones);

        $firstPhone = reset($phones);
        $this->assertNotFalse($firstPhone);
        $this->assertSame('+49-170-555-0101', $firstPhone->getNumber());
    }

    public function testComplexRoundcubeVCardRoundtripFromFile()
    {
        $vCard = file_get_contents(__DIR__ . '/../resources/rc_vcard_advanced.vcf');
        $this->assertNotFalse($vCard, 'Failed to read rc_vcard_advanced.vcf');
        $this->assertStringContainsString('BEGIN:VCARD', $vCard);
        $this->assertStringContainsString('END:VCARD', $vCard);

        //vCard -> JSContact
        $cards = $this->mapper->mapToJmap(
            ['rc-complex-001' => $vCard],
            $this->adapter
        );

        $this->assertIsArray($cards);
        $this->assertCount(1, $cards);
        $this->assertInstanceOf(ContactCard::class, $cards[0]);

        $card = $cards[0];

        // Imported card fields
        $this->assertSame('rc-complex-001', $card->getUid());
        $this->assertSame(
            '-//Roundcube Webmail//NONSGML Roundcube Contact//EN',
            $card->getProdId()
        );
        $this->assertSame('2026-03-16T12:00:00Z', $card->getUpdated());

        // Maiden name
        $this->assertSame(
            'Öster',
            $card->getProperty('audriga.eu/roundcube:maidenName')
        );

        // Name
        $name = $card->getName();
        $this->assertNotNull($name);
        $this->assertSame('Dr. Jörg Åström', $name->getFull());

        $components = $name->getComponents() ?: [];
        $this->assertCount(3, $components);
        $this->assertSame('title', $components[0]->getKind());
        $this->assertSame('Dr.', $components[0]->getValue());
        $this->assertSame('given', $components[1]->getKind());
        $this->assertSame('Jörg', $components[1]->getValue());
        $this->assertSame('surname', $components[2]->getKind());
        $this->assertSame('Åström', $components[2]->getValue());

        // Nickname
        $nicknames = $card->getNicknames() ?: [];
        $this->assertCount(1, $nicknames);
        $nickname = reset($nicknames);
        $this->assertNotFalse($nickname);
        $this->assertSame('Jörgi', $nickname->getName());

        // Organization
        $organizations = $card->getOrganizations() ?: [];
        $this->assertCount(1, $organizations);
        $organization = reset($organizations);
        $this->assertNotFalse($organization);
        $this->assertSame('Äcme GmbH', $organization->getName());
        $this->assertSame(
            ['Forschung und Entwicklung', 'Forschung'],
            $organization->getUnits()
        );

        // Title
        $titles = $card->getTitles() ?: [];
        $this->assertCount(1, $titles);
        $title = reset($titles);
        $this->assertNotFalse($title);
        $this->assertSame('Leitender Entwickler', $title->getName());
        $this->assertSame('title', $title->getKind());

        // SpeakToAs / gender
        $speakToAs = $card->getSpeakToAs();
        $this->assertNotNull($speakToAs);
        $this->assertSame('male', $speakToAs->getGrammaticalGender());

        // Emails
        $emails = $card->getEmails() ?: [];
        $this->assertCount(2, $emails);

        $emailValues = [];
        foreach ($emails as $email) {
            $emailValues[] = $email->getAddress();
        }
        $this->assertContains('joerg.aestroem@example.com', $emailValues);
        $this->assertContains('joerg.astrom@work.example', $emailValues);

        // Phones
        $phones = $card->getPhones() ?: [];
        $this->assertCount(3, $phones);

        $phoneMap = [];
        foreach ($phones as $phone) {
            $phoneMap[$phone->getNumber()] = $phone;
        }

        $this->assertArrayHasKey('+49-170-555-0101', $phoneMap);
        $this->assertArrayHasKey('+49-30-555-0102', $phoneMap);
        $this->assertArrayHasKey('123-pager', $phoneMap);

        $mobileFeatures = $phoneMap['+49-170-555-0101']->getFeatures() ?: [];
        $this->assertTrue($mobileFeatures['mobile'] ?? false);
        $this->assertTrue($mobileFeatures['voice'] ?? false);

        $homeContexts = $phoneMap['+49-30-555-0102']->getContexts() ?: [];
        $homeFeatures = $phoneMap['+49-30-555-0102']->getFeatures() ?: [];
        $this->assertTrue($homeContexts['private'] ?? false);
        $this->assertTrue($homeFeatures['voice'] ?? false);

        $pagerFeatures = $phoneMap['123-pager']->getFeatures() ?: [];
        $this->assertTrue($pagerFeatures['pager'] ?? false);

        // Address
        $addresses = $card->getAddresses() ?: [];
        $this->assertCount(1, $addresses);

        $address = reset($addresses);
        $this->assertNotFalse($address);
        $this->assertInstanceOf(\OpenXPort\Jmap\JSContact\Address::class, $address);

        $addressComponents = $address->getComponents() ?: [];
        $this->assertCount(4, $addressComponents);

        $this->assertSame('name', $addressComponents[0]->getValue());
        $this->assertSame('Münzstraße 12', $addressComponents[0]->getKind());
        $this->assertSame('locality', $addressComponents[1]->getValue());
        $this->assertSame('Berlin', $addressComponents[1]->getKind());
        $this->assertSame('postcode', $addressComponents[2]->getValue());
        $this->assertSame('10178', $addressComponents[2]->getKind());
        $this->assertSame('country', $addressComponents[3]->getValue());
        $this->assertSame('Germany', $addressComponents[3]->getKind());

        $addressContexts = $address->getContexts() ?: [];
        $this->assertTrue($addressContexts['private'] ?? false);

        // Notes
        $notes = $card->getNoteObjects() ?: [];
        $this->assertCount(1, $notes);
        $note = reset($notes);
        $this->assertNotFalse($note);
        $this->assertSame(
            'Roundcube test contact with UTF-8 characters: ä ö ü ß é Å.',
            $note->getNote()
        );

        // Online services
        $online = $card->getOnlineServices() ?: [];
        $this->assertCount(4, $online);

        $onlineByLabelOrUri = [];
        foreach ($online as $entry) {
            $key = $entry->getLabel() ?: $entry->getUri();
            $onlineByLabelOrUri[$key] = $entry;
        }

        $this->assertArrayHasKey('https://example.com/~joerg', $onlineByLabelOrUri);
        $this->assertArrayHasKey('X-AIM', $onlineByLabelOrUri);
        $this->assertArrayHasKey('X-JABBER', $onlineByLabelOrUri);
        $this->assertArrayHasKey('X-SKYPE-USERNAME', $onlineByLabelOrUri);

        $this->assertSame('joergaim', $onlineByLabelOrUri['X-AIM']->getUri());
        $this->assertSame('aim', $onlineByLabelOrUri['X-AIM']->getService());
        $this->assertSame('joerg@jabber.example', $onlineByLabelOrUri['X-JABBER']->getUri());
        $this->assertSame('jabber', $onlineByLabelOrUri['X-JABBER']->getService());
        $this->assertSame('joerg.astrom.skype', $onlineByLabelOrUri['X-SKYPE-USERNAME']->getUser());
        $this->assertSame('skype', $onlineByLabelOrUri['X-SKYPE-USERNAME']->getService());
        $this->assertSame('skype', $onlineByLabelOrUri['X-SKYPE-USERNAME']->getService());

        // Anniversaries
        $anniversaries = $card->getAnniversaries() ?: [];
        $this->assertCount(1, $anniversaries);
        $anniversary = reset($anniversaries);
        $this->assertNotFalse($anniversary);
        $this->assertSame('birth', $anniversary->getKind());
        $this->assertSame('1988-04-12', $anniversary->getDate());

        // Relations
        $relatedTo = $card->getRelatedTo() ?: [];
        $this->assertCount(3, $relatedTo);
        $this->assertArrayHasKey('Renée Manager', $relatedTo);
        $this->assertArrayHasKey('Björk Assistant', $relatedTo);
        $this->assertArrayHasKey('Zoë Åström', $relatedTo);
        $this->assertTrue($relatedTo['Renée Manager']->getRelation()['manager'] ?? false);
        $this->assertTrue($relatedTo['Björk Assistant']->getRelation()['assistant'] ?? false);
        $this->assertTrue($relatedTo['Zoë Åström']->getRelation()['spouse'] ?? false);

        // JSContact -> vCard
        $exported = $this->mapper->mapFromJmap(
            ['rc-complex-001' => $card],
            $this->adapter
        );

        $this->assertIsArray($exported);
        $this->assertNotEmpty($exported);

        $unwrapped = [];
        foreach ($exported as $entry) {
            foreach ($entry as $id => $payload) {
                $unwrapped[$id] = is_array($payload) && array_key_exists('vCard', $payload)
                    ? $payload['vCard']
                    : $payload;
            }
        }

        $this->assertArrayHasKey('rc-complex-001', $unwrapped);
        $this->assertIsString($unwrapped['rc-complex-001']);

        $exportedVCard = $unwrapped['rc-complex-001'];
        $unfoldedVCard = preg_replace("/\r\n[ \t]/", '', $exportedVCard);
        $this->assertNotNull($unfoldedVCard);

        $this->assertStringContainsString('BEGIN:VCARD', $unfoldedVCard);
        $this->assertStringContainsString('END:VCARD', $unfoldedVCard);
        $this->assertStringContainsString('FN:Dr. Jörg Åström', $unfoldedVCard);
        $this->assertStringContainsString('N:Åström;Jörg;;Dr.;', $unfoldedVCard);
        $this->assertStringContainsString('NICKNAME:Jörgi', $unfoldedVCard);
        $this->assertStringContainsString('joerg.aestroem@example.com', $unfoldedVCard);
        $this->assertStringContainsString('joerg.astrom@work.example', $unfoldedVCard);
        $this->assertStringContainsString('+49-170-555-0101', $unfoldedVCard);
        $this->assertStringContainsString('+49-30-555-0102', $unfoldedVCard);
        $this->assertStringContainsString('123-pager', $unfoldedVCard);
        $this->assertStringContainsString('Äcme GmbH', $unfoldedVCard);
        $this->assertStringContainsString('Leitender Entwickler', $unfoldedVCard);
        $this->assertStringContainsString('https://example.com/~joerg', $unfoldedVCard);
        $this->assertStringContainsString('X-MAIDENNAME:Öster', $unfoldedVCard);
        $this->assertTrue(
            str_contains($unfoldedVCard, '19880412') || str_contains($unfoldedVCard, '1988-04-12')
        );
        $this->assertStringContainsString('ADR;', $unfoldedVCard);
        $this->assertStringContainsString('Münzstraße 12', $unfoldedVCard);
        $this->assertStringContainsString('Berlin', $unfoldedVCard);
        $this->assertStringContainsString('10178', $unfoldedVCard);
        $this->assertStringContainsString('Germany', $unfoldedVCard);
        $this->assertStringContainsString('X-GENDER:male', $unfoldedVCard);
        $this->assertStringContainsString('X-AIM:joergaim', $unfoldedVCard);
        $this->assertStringContainsString('X-JABBER:joerg@jabber.example', $unfoldedVCard);
        $this->assertStringContainsString('X-SKYPE-USERNAME:joerg.astrom.skype', $unfoldedVCard);
        $this->assertStringContainsString('X-MANAGER:Renée Manager', $unfoldedVCard);
        $this->assertStringContainsString('X-ASSISTANT:Björk Assistant', $unfoldedVCard);
        $this->assertStringContainsString('X-SPOUSE:Zoë Åström', $unfoldedVCard);
        $this->assertStringContainsString('X-DEPARTMENT:', $unfoldedVCard);

        $roundtripped = $this->mapper->mapToJmap($unwrapped, $this->adapter);

        $this->assertIsArray($roundtripped);
        $this->assertCount(1, $roundtripped);
        $this->assertInstanceOf(ContactCard::class, $roundtripped[0]);

        $rtCard = $roundtripped[0];

        $this->assertSame('rc-complex-001', $rtCard->getUid());
        $this->assertSame($card->getProdId(), $rtCard->getProdId());
        $this->assertSame($card->getUpdated(), $rtCard->getUpdated());
        $this->assertSame(
            'Öster',
            $rtCard->getProperty('audriga.eu/roundcube:maidenName')
        );

        // Roundtrip checks
        $rtEmails = $rtCard->getEmails() ?: [];
        $this->assertCount(2, $rtEmails);

        $rtEmailValues = [];
        foreach ($rtEmails as $email) {
            $rtEmailValues[] = $email->getAddress();
        }
        $this->assertContains('joerg.aestroem@example.com', $rtEmailValues);
        $this->assertContains('joerg.astrom@work.example', $rtEmailValues);

        $rtPhones = $rtCard->getPhones() ?: [];
        $this->assertCount(3, $rtPhones);

        $rtPhoneValues = [];
        foreach ($rtPhones as $phone) {
            $rtPhoneValues[] = $phone->getNumber();
        }
        $this->assertContains('+49-170-555-0101', $rtPhoneValues);
        $this->assertContains('+49-30-555-0102', $rtPhoneValues);
        $this->assertContains('123-pager', $rtPhoneValues);

        $rtPagerPhone = null;
        foreach ($rtPhones as $phone) {
            if ($phone->getNumber() === '123-pager') {
                $rtPagerPhone = $phone;
                break;
            }
        }
        $this->assertNotNull($rtPagerPhone);
        $rtFeatures = $rtPagerPhone->getFeatures() ?: [];
        $this->assertTrue($rtFeatures['pager'] ?? false);

        $rtAddresses = $rtCard->getAddresses() ?: [];
        $this->assertCount(1, $rtAddresses);

        $rtOrganizations = $rtCard->getOrganizations() ?: [];
        $this->assertNotEmpty($rtOrganizations);

        $rtTitles = $rtCard->getTitles() ?: [];
        $this->assertNotEmpty($rtTitles);

        $rtNotes = $rtCard->getNoteObjects() ?: [];
        $this->assertCount(1, $rtNotes);
        $rtNote = reset($rtNotes);
        $this->assertNotFalse($rtNote);
        $this->assertSame(
            'Roundcube test contact with UTF-8 characters: ä ö ü ß é Å.',
            $rtNote->getNote()
        );

        $rtOnline = $rtCard->getOnlineServices() ?: [];
        $this->assertNotEmpty($rtOnline);

        $rtAnniversaries = $rtCard->getAnniversaries() ?: [];
        $this->assertNotEmpty($rtAnniversaries);

        $rtRelatedTo = $rtCard->getRelatedTo() ?: [];
        $this->assertNotEmpty($rtRelatedTo);

        $this->assertEquals(
            array_values($card->getEmails() ?: []),
            array_values($rtCard->getEmails() ?: [])
        );
        $this->assertEquals(
            array_values($card->getPhones() ?: []),
            array_values($rtCard->getPhones() ?: [])
        );
        $this->assertEquals(
            array_values($card->getOrganizations() ?: []),
            array_values($rtCard->getOrganizations() ?: [])
        );
        $this->assertEquals(
            array_values($card->getTitles() ?: []),
            array_values($rtCard->getTitles() ?: [])
        );
        $this->assertEquals(
            array_values($card->getAnniversaries() ?: []),
            array_values($rtCard->getAnniversaries() ?: [])
        );
        $this->assertEquals(
            array_values($card->getNoteObjects() ?: []),
            array_values($rtCard->getNoteObjects() ?: [])
        );
        $this->assertEquals(
            array_values($card->getRelatedTo() ?: []),
            array_values($rtCard->getRelatedTo() ?: [])
        );
    }

    public function testJsContactJsonFileRoundtripToRoundcubeVCard()
    {
        $json = file_get_contents(__DIR__ . '/../resources/jscontactcard_advanced.json');
        $this->assertNotFalse($json, 'Failed to read jscontactcard_advanced.json');

        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Failed to decode jscontactcard_advanced.json');

        $card = new ContactCard();

        // uid / updated
        $card->setUid((string) ($data['uid'] ?? '1'));
        $card->setUpdated($data['updated'] ?? null);

        // Name
        if (isset($data['name']) && is_array($data['name'])) {
            $name = new \OpenXPort\Jmap\JSContact\Name();
            $components = [];

            foreach (($data['name']['components'] ?? []) as $componentData) {
                $component = new \OpenXPort\Jmap\JSContact\NameComponent();

                $type = $componentData['type'] ?? null;
                $value = $componentData['value'] ?? null;

                $kindMap = [
                    'prefix'  => 'title',
                    'given'   => 'given',
                    'surname' => 'surname',
                    'middle'  => 'given2',
                    'suffix'  => 'credential',
                ];

                $component->setKind($kindMap[$type] ?? $type);
                $component->setValue($value);

                $components[] = $component;
            }

            $name->setComponents($components);
            $name->setIsOrdered(true);
            $name->setFull('Mr. John Quinlan Public Esq.');

            $card->setName($name);
        }

        // Online services
        if (isset($data['onlineServices']) && is_array($data['onlineServices'])) {
            $services = [];

            foreach ($data['onlineServices'] as $id => $serviceData) {
                $service = new \OpenXPort\Jmap\JSContact\OnlineService();

                if (isset($serviceData['service'])) {
                    $service->setService(strtolower((string) $serviceData['service']));
                }

                if (isset($serviceData['user'])) {
                    if (($serviceData['type'] ?? null) === 'impp') {
                        $service->setUri((string) $serviceData['user']);
                    } else {
                        $service->setUser((string) $serviceData['user']);
                    }
                }

                if (isset($serviceData['pref'])) {
                    $service->setPref((int) $serviceData['pref']);
                }

                $services[$id] = $service;
            }

            $card->setOnlineServices($services);
        }

        // Organizations
        if (isset($data['organizations']) && is_array($data['organizations'])) {
            $organizations = [];

            foreach ($data['organizations'] as $id => $orgData) {
                $organization = new \OpenXPort\Jmap\JSContact\Organization();
                $organization->setName($orgData['name'] ?? null);
                $organization->setUnits($orgData['units'] ?? []);
                $organizations[$id] = $organization;
            }

            $card->setOrganizations($organizations);
        }

        // Anniversaries
        if (isset($data['anniversaries']) && is_array($data['anniversaries'])) {
            $anniversaries = [];

            foreach ($data['anniversaries'] as $id => $annData) {
                $anniversary = new \OpenXPort\Jmap\JSContact\Anniversary();

                $anniversary->setKind('wedding');
                $anniversary->setLabel('anniversary');
                $anniversary->setDate($annData['date'] ?? null);

                $anniversaries[] = $anniversary;
            }

            $card->setAnniversaries($anniversaries);
        }

        // JSContact -> Roundcube vCard
        $exported = $this->mapper->mapFromJmap(
            ['c1' => $card],
            $this->adapter
        );

        $this->assertIsArray($exported);
        $this->assertNotEmpty($exported);

        $unwrapped = [];
        foreach ($exported as $entry) {
            foreach ($entry as $id => $payload) {
                $unwrapped[$id] = is_array($payload) && array_key_exists('vCard', $payload)
                    ? $payload['vCard']
                    : $payload;
            }
        }

        $this->assertArrayHasKey('c1', $unwrapped);
        $this->assertIsString($unwrapped['c1']);

        $exportedVCard = $unwrapped['c1'];
        $unfoldedVCard = preg_replace("/\r\n[ \t]/", '', $exportedVCard);
        $this->assertNotNull($unfoldedVCard);

        $this->assertStringContainsString('BEGIN:VCARD', $unfoldedVCard);
        $this->assertStringContainsString('END:VCARD', $unfoldedVCard);
        $this->assertStringContainsString('UID:1', $unfoldedVCard);
        $this->assertStringContainsString('REV:20080424T195243Z', $unfoldedVCard);
        $this->assertStringContainsString('FN:Mr. John Quinlan Public Esq.', $unfoldedVCard);
        $this->assertStringContainsString('N:Public;John;Quinlan;Mr.;Esq.', $unfoldedVCard);
        $this->assertStringContainsString('ORG:Bubba Gump Shrimp Co.', $unfoldedVCard);
        $this->assertStringContainsString('X-DEPARTMENT:Cleaning department', $unfoldedVCard);
        $this->assertTrue(
            str_contains($unfoldedVCard, 'ANNIVERSARY;VALUE=date:20230228')
            || str_contains($unfoldedVCard, 'ANNIVERSARY:20230228')
        );

        $this->assertStringContainsString('alice@example.com', $unfoldedVCard);
        $this->assertStringContainsString('PupkinV', $unfoldedVCard);

        //vCard -> JSContact
        $roundtripped = $this->mapper->mapToJmap($unwrapped, $this->adapter);

        $this->assertIsArray($roundtripped);
        $this->assertCount(1, $roundtripped);
        $this->assertInstanceOf(ContactCard::class, $roundtripped[0]);

        $rtCard = $roundtripped[0];

        $this->assertSame('1', $rtCard->getUid());
        $this->assertSame('2008-04-24T19:52:43Z', $rtCard->getUpdated());

        $rtName = $rtCard->getName();
        $this->assertNotNull($rtName);
        $this->assertSame('Mr. John Quinlan Public Esq.', $rtName->getFull());

        $rtOrganizations = $rtCard->getOrganizations() ?: [];
        $this->assertCount(1, $rtOrganizations);
        $rtOrg = reset($rtOrganizations);
        $this->assertNotFalse($rtOrg);
        $this->assertSame('Bubba Gump Shrimp Co.', $rtOrg->getName());
        $this->assertSame(['Cleaning department'], $rtOrg->getUnits());

        $rtAnniversaries = $rtCard->getAnniversaries() ?: [];
        $this->assertNotEmpty($rtAnniversaries);

        $rtOnline = $rtCard->getOnlineServices() ?: [];
        $this->assertNotEmpty($rtOnline);
    }
}