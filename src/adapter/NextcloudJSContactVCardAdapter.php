<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Util\JSContactVCardAdapterUtil;

/**
 * Nextcloud-specific adapter to convert between vCard <-> JSContact.
 * Overrides methods of the generic adapter if Roundcube deviates.
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
    public function getOnlineServices()
    {
        $jsContactOnlineProperty = parent::getOnlineServices();

        $socialProps = [];

        if (is_null($this->vCard->__get("X-SOCIALPROFILE"))) {
            return $jsContactOnlineProperty;
        }
        foreach ($this->vCard->__get("X-SOCIALPROFILE") as $vCardProp) {
            if (isset($vCardProp)) {
                array_push($socialProps, $vCardProp);
            }
        }

        // This is basically the same as "SOCIALPROFILE" in parent but for X-SOCIALPROFILE.
        foreach ($socialProps as $vCardSocialProperty) {
            $vCardSocialPropertyValue = $vCardSocialProperty->getValue();

            if (isset($vCardSocialPropertyValue) && !empty($vCardSocialPropertyValue)) {
                if (
                    isset($vCardSocialProperty['VALUE']) &&
                    !empty($vCardSocialProperty['VALUE']) &&
                    $vCardSocialProperty['VALUE'] == "text"
                ) {
                    $jsContactSocialEntry = new OnlineService($vCardSocialPropertyValue, "username");
                } else {
                    $jsContactSocialEntry = new OnlineService($vCardSocialPropertyValue, "uri");
                }

                if (isset($vCardSocialProperty['PREF']) && !empty($vCardSocialProperty['PREF'])) {
                    $jsContactSocialEntry->setPref($vCardSocialProperty['PREF']);
                }

                if (isset($vCardSocialProperty['SERVICE-TYPE']) && !empty($vCardSocialProperty['SERVICE-TYPE'])) {
                    $jsContactSocialEntry->setService($vCardSocialProperty['SERVICE-TYPE']);
                }

                $jsContactSocialEntry->setContexts(
                    JSContactVCardAdapterUtil::convertFromVCardType($vCardSocialProperty)
                );

                // Since "online" is a map and key creation for the map keys is not specified, we use
                // the MD5 hash of the IMPP property's value to create the key of the entry in "online"
                $jsContactOnlineProperty[md5($vCardSocialPropertyValue)] = $jsContactSocialEntry;
            }
        }

        return $jsContactOnlineProperty;
    }
}
