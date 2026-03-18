<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\JSContactVCardAdapterUtil as Util;

/**
 * Nextcloud-specific adapter to convert between vCard <-> JSContact.
 * Overrides methods of the generic adapter if Nextcloud deviates.
 */
class NextcloudJSContactVCardAdapter extends JSContactVCardAdapter
{
    /**
     * This function maps the vCard "IMPP", "SOCIALPROFILE", "URL" and Nextcloud-specific
     * "X-SOCIALPROFILE" property to the JSContact "onlineServices" property
     *
     * Note: Nextcloud uses X-SOCIALPROFILE with TYPE parameter to specify the service
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getOnlineServices($card)
    {
        parent::getOnlineServices($card);

        $services = $card->getOnlineServices() ?: array();
        $index = count($services) + 1;

        $xSocialProfiles = $this->vCard->__get('X-SOCIALPROFILE');
        if (!AdapterUtil::isSetAndNotNull($xSocialProfiles) || empty($xSocialProfiles)) {
            return;
        }

        foreach ($xSocialProfiles as $prop) {
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }

            $service = new OnlineService();

            // Set the user/uri value
            if (
                isset($prop['VALUE'])
                && strtolower(trim((string) $prop['VALUE'])) === 'text'
            ) {
                $service->setUser($value);
            } else {
                // Check if it's a URL or just username
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $service->setUri($value);
                } else {
                    $service->setUser($value);
                }
            }

            // Extract service type from TYPE parameter (e.g., TYPE=twitter)
            $serviceType = null;
            if (isset($prop['TYPE'])) {
                $types = $prop['TYPE']->getParts();
                if (is_array($types) && !empty($types)) {
                    $serviceType = strtolower(trim($types[0]));
                }
            }

            // Also check SERVICE-TYPE parameter as fallback
            if (empty($serviceType) && isset($prop['SERVICE-TYPE'])) {
                $serviceType = strtolower(trim((string) $prop['SERVICE-TYPE']));
            }

            if (!empty($serviceType)) {
                $service->setService($serviceType);
            }

            $contexts = $this->vCardTypeParamToContexts($prop);
            if (!empty($contexts)) {
                $service->setContexts($contexts);
            }

            $pref = $this->vCardPrefParamToInt($prop);
            if ($pref !== null) {
                $service->setPref($pref);
            }

            $service->setLabel('X-SOCIALPROFILE');

            $key = Util::getMapKeyFromPropValue($prop, $value, 'os', $index, $services);
            $services[$key] = $service;
        }

        if (!empty($services)) {
            $card->setOnlineServices($services);
        }
    }

    /**
     * This function maps the JSContact "onlineServices" property to vCard properties including
     * Nextcloud-specific X-SOCIALPROFILE for entries explicitly marked as such
     *
     * @param ContactCard $card The ContactCard containing online services
     */
    public function setOnlineServices($card)
    {
        parent::setOnlineServices($card);

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

            $value = Util::getOnlineServiceExportValue($service);
            if ($value === null || $value === '') {
                continue;
            }

            $params = [];

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

            $user = $service->getUser();
            $uri = $service->getUri();

            if (
                is_string($user) && $user !== ''
                && (!is_string($uri) || $uri === '')
            ) {
                $params['VALUE'] = 'text';
            }

            $this->vCard->add('X-SOCIALPROFILE', $value, $params);
        }
    }
}
