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
     * Nextcloud uses X-SOCIALPROFILE instead of SOCIALPROFILE
     *
     * TODO Not sure if we also need writing logic here
     *
     * Overrides getOnlineServices from parent
     */
    public function getOnlineServices(ContactCard $card)
    {
        // First call parent to get standard properties
        parent::getOnlineServices($card);

        // Get existing services or initialize empty array
        $jsContactOnlineProperty = $card->getOnlineServices();
        if (!is_array($jsContactOnlineProperty)) {
            $jsContactOnlineProperty = array();
        }

        // Check if X-SOCIALPROFILE exists
        $socialProps = array();
        if (is_null($this->vCard->__get("X-SOCIALPROFILE"))) {
            return;
        }

        foreach ($this->vCard->__get("X-SOCIALPROFILE") as $vCardProp) {
            if (isset($vCardProp)) {
                array_push($socialProps, $vCardProp);
            }
        }

        // Calculate starting index for new services
        $index = count($jsContactOnlineProperty) + 1;

        // This is basically the same as "SOCIALPROFILE" in parent but for X-SOCIALPROFILE.
        foreach ($socialProps as $vCardSocialProperty) {
            $vCardSocialPropertyValue = $vCardSocialProperty->getValue();

            if (isset($vCardSocialPropertyValue) && !empty($vCardSocialPropertyValue)) {
                $jsContactSocialEntry = new OnlineService();

                // Determine if it's username or uri based on VALUE parameter
                if (
                    isset($vCardSocialProperty['VALUE']) &&
                    !empty($vCardSocialProperty['VALUE']) &&
                    $vCardSocialProperty['VALUE'] == "text"
                ) {
                    $jsContactSocialEntry->setUser($vCardSocialPropertyValue);
                } else {
                    // Check if it's a URL or just username
                    if (filter_var($vCardSocialPropertyValue, FILTER_VALIDATE_URL)) {
                        $jsContactSocialEntry->setUri($vCardSocialPropertyValue);
                    } else {
                        $jsContactSocialEntry->setUser($vCardSocialPropertyValue);
                    }
                }

                // Set preference if present
                if (isset($vCardSocialProperty['PREF']) && !empty($vCardSocialProperty['PREF'])) {
                    $jsContactSocialEntry->setPref($vCardSocialProperty['PREF']);
                }

                // Set service type if present
                if (isset($vCardSocialProperty['SERVICE-TYPE']) && !empty($vCardSocialProperty['SERVICE-TYPE'])) {
                    $jsContactSocialEntry->setService($vCardSocialProperty['SERVICE-TYPE']);
                }

                // Extract service type from TYPE parameter as well (e.g., TYPE=twitter)
                if (empty($jsContactSocialEntry->getService()) && isset($vCardSocialProperty['TYPE'])) {
                    $types = $vCardSocialProperty['TYPE']->getParts();
                    if (is_array($types) && !empty($types)) {
                        $serviceType = strtolower(trim($types[0]));
                        // Only set if it looks like a service name (not work/home)
                        if (!in_array($serviceType, array('work', 'home', 'other'))) {
                            $jsContactSocialEntry->setService($serviceType);
                        }
                    }
                }

                // Convert contexts from TYPE parameter
                $contexts = Util::vCardTypeParamToContexts($vCardSocialProperty);
                if (!empty($contexts)) {
                    $jsContactSocialEntry->setContexts($contexts);
                }

                // Set label to indicate this came from X-SOCIALPROFILE
                $jsContactSocialEntry->setLabel('X-SOCIALPROFILE');

                // Since "online" is a map and key creation for the map keys is not specified, we use
                // the getMapKeyFromPropValue utility or MD5 hash fallback
                $key = Util::getMapKeyFromPropValue(
                    $vCardSocialProperty,
                    $vCardSocialPropertyValue,
                    'os',
                    $index,
                    $jsContactOnlineProperty
                );
                $jsContactOnlineProperty[$key] = $jsContactSocialEntry;
                $index++;
            }
        }

        // Update the card with all online services
        if (!empty($jsContactOnlineProperty)) {
            $card->setOnlineServices($jsContactOnlineProperty);
        }
    }

    /**
     * This function maps the JSContact "onlineServices" property to vCard properties including
     * Nextcloud-specific X-SOCIALPROFILE for entries explicitly marked as such
     *
     * @param ContactCard $card The ContactCard containing online services
     */
    public function setOnlineServices(ContactCard $card)
    {
        // First call parent to handle standard properties
        parent::setOnlineServices($card);

        $services = $card->getOnlineServices();
        if (!is_array($services) || empty($services)) {
            return;
        }

        foreach ($services as $service) {
            if (!($service instanceof OnlineService)) {
                continue;
            }

            $label = $service->getLabel();
            if (!is_string($label) || strtoupper(trim($label)) !== 'X-SOCIALPROFILE') {
                continue;
            }

            $value = Util::getOnlineServiceExportValue($service);
            if ($value === null || $value === '') {
                continue;
            }

            $params = array();

            $serviceType = $service->getService();
            if (is_string($serviceType) && $serviceType !== '') {
                $params['SERVICE-TYPE'] = $serviceType;
            }

            $types = Util::contextsToVcardTypeParam($service);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = Util::prefToVcardParam($service);
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
