<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Util\AdapterUtil;

/**
 * Nextcloud-specific adapter to convert between vCard and JSContact.
 *
 * Nextcloud may use X-SOCIALPROFILE instead of SOCIALPROFILE.
 * This adapter extends the generic online-service handling to support that
 * proprietary property on import and export.
 */
class NextcloudJSContactVCardAdapter extends JSContactVCardAdapter
{
    /**
     * Reads standard online services first, then also reads Nextcloud's X-SOCIALPROFILE.
     *
     * @param ContactCard $card
     */
    public function getOnlineToJmap(ContactCard $card)
    {
        parent::getOnlineToJmap($card);

        $services = $card->getOnlineServices() ?: [];
        $index = count($services) + 1;

        $xSocialProfiles = $this->vcard->__get('X-SOCIALPROFILE');
        if (!AdapterUtil::isSetAndNotNull($xSocialProfiles) || empty($xSocialProfiles)) {
            return;
        }

        foreach ($xSocialProfiles as $prop) {
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }

            $service = new OnlineService();

            // Nextcloud may store username-style values as VALUE=text.
            if (
                isset($prop['VALUE'])
                && strtolower(trim((string) $prop['VALUE'])) === 'text'
            ) {
                $service->setUser($value);
            } else {
                $service->setUri($value);
            }

            if (isset($prop['SERVICE-TYPE']) && trim((string) $prop['SERVICE-TYPE']) !== '') {
                $service->setService((string) $prop['SERVICE-TYPE']);
            }

            $this->applyCommonContextAndPref($service, $prop);

            // Mark origin so export can preserve Nextcloud's X-SOCIALPROFILE.
            $service->setLabel('X-SOCIALPROFILE');

            $services['os' . $index++] = $service;
        }

        if (!empty($services)) {
            $card->setOnlineServices($services);
        }
    }

    /**
     * Writes standard online services first, then also writes Nextcloud-specific X-SOCIALPROFILE
     * for entries explicitly marked as such.
     *
     * @param ContactCard $card
     */
    public function setOnlineFromJmap(ContactCard $card)
    {
        parent::setOnlineFromJmap($card);

        $services = $card->getOnlineServices();
        if (!is_array($services) || empty($services)) {
            return;
        }

        foreach ($services as $service) {
            if (!($service instanceof OnlineService)) {
                continue;
            }

            $label = strtoupper(trim((string) $service->getLabel()));
            if ($label !== 'X-SOCIALPROFILE') {
                continue;
            }

            $value = $this->determineOnlineExportValue($service);
            if ($value === null || $value === '') {
                continue;
            }

            $params = array();

            $serviceType = $service->getService();
            if (is_string($serviceType) && $serviceType !== '') {
                $params['SERVICE-TYPE'] = $serviceType;
            }

            $types = $this->contextsToVcardTypeParam($service);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($service);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            // If it is a username-style profile, preserve VALUE=text.
            $user = $service->getUser();
            $uri = $service->getUri();

            if (
                is_string($user) && $user !== ''
                && (!is_string($uri) || $uri === '')
            ) {
                $params['VALUE'] = 'text';
            }

            $this->vcard->add('X-SOCIALPROFILE', $value, $params);
        }
    }
}