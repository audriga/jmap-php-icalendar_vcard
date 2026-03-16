<?php

namespace OpenXPort\Tests\Unit;

use OpenXPort\Adapter\NextcloudJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Mapper\JSContactVCardMapper;
use PHPUnit\Framework\TestCase;

/**
 * Nextcloud-specific tests for converting between vCard and JSContact.
 */
final class NextcloudJSContactVCardAdapterTest extends TestCase
{
    /** @var string|null */
    protected $vCard = null;

    /** @var NextcloudJSContactVCardAdapter|null */
    protected $adapter = null;

    /** @var JSContactVCardMapper|null */
    protected $mapper = null;

    /** @var ContactCard|null */
    protected $jsContactCard = null;

    protected function setUp(): void
    {
        $this->adapter = new NextcloudJSContactVCardAdapter();
        $this->mapper  = new JSContactVCardMapper();
    }

    protected function tearDown(): void
    {
        $this->vCard         = null;
        $this->adapter       = null;
        $this->mapper        = null;
        $this->jsContactCard = null;
    }

    /**
     * Reads a vCard fixture file and maps it into JSContact.
     *
     * @param string|null $path Relative path from this test file.
     */
    private function mapVCard($path = null)
    {
        $fixturePath = $path !== null
            ? __DIR__ . $path
            : __DIR__ . '/../resources/nextcloud_vcard.vcf';

        $this->vCard = file_get_contents($fixturePath);
        $this->assertNotFalse($this->vCard, 'Failed to read vCard fixture: ' . $fixturePath);

        $cards = $this->mapper->mapToJmap(
            ['1' => $this->vCard],
            $this->adapter
        );

        $this->assertIsArray($cards);
        $this->assertNotEmpty($cards);
        $this->assertInstanceOf(ContactCard::class, $cards[0]);

        $this->jsContactCard = $cards[0];
    }

    public function testReadNextcloudSpecificXSocialProfile()
    {
        $this->mapVCard();

        $this->assertInstanceOf(ContactCard::class, $this->jsContactCard);

        $onlineServices = $this->jsContactCard->getOnlineServices() ?: [];
        $this->assertNotEmpty($onlineServices);

        $users = [];
        $uris = [];
        $labels = [];

        foreach ($onlineServices as $service) {
            $user = $service->getUser();
            $uri = $service->getUri();
            $label = method_exists($service, 'getLabel') ? $service->getLabel() : null;

            if ($user !== null && $user !== '') {
                $users[] = $user;
            }

            if ($uri !== null && $uri !== '') {
                $uris[] = $uri;
            }

            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        // Nextcloud-specific X-SOCIALPROFILE should be imported.
        $this->assertContains(
            'https://github.com/apache/james-project',
            array_merge($users, $uris)
        );

        // Our Nextcloud adapter marks imported X-SOCIALPROFILE entries with this label.
        $this->assertContains('X-SOCIALPROFILE', $labels);
    }
}