<?php

namespace OpenXPort\Util;

use InvalidArgumentException;

/**
 * Utility class used by JSContactVCardAdapters to convert property values.
 */
class JSContactVCardAdapterUtil
{
    protected static $logger;

    public static function convertFromNameToN($jsContactName)
    {
        if (is_null($jsContactName)) {
            return array();
        }

        $jsContactNameComponents = $jsContactName->components;

        $vCardPrefix = null;
        $vCardGivenName = null;
        $vCardFamilyName = null;
        $vCardAdditionalName = null;
        $vCardSuffix = null;

        if (isset($jsContactNameComponents) && !empty($jsContactNameComponents)) {
            foreach ($jsContactNameComponents as $jsContactNameComponent) {
                if (isset($jsContactNameComponent) && !empty($jsContactNameComponent)) {
                    $jsContactNameComponentType = $jsContactNameComponent->type;
                    if (isset($jsContactNameComponentType) && !empty($jsContactNameComponentType)) {
                        $jsContactNameComponentValue = $jsContactNameComponent->value;

                        if (isset($jsContactNameComponentValue) && !empty($jsContactNameComponentValue)) {
                            switch ($jsContactNameComponentType) {
                                case 'prefix':
                                    $vCardPrefix = $jsContactNameComponentValue;
                                    break;

                                case 'given':
                                    $vCardGivenName = $jsContactNameComponentValue;
                                    break;

                                case 'surname':
                                    $vCardFamilyName = $jsContactNameComponentValue;
                                    break;

                                // TODO remove once we move to actual JSContact RFC
                                case 'additional':
                                    $vCardAdditionalName = $jsContactNameComponentValue;
                                    break;

                                case 'middle':
                                    $vCardAdditionalName = $jsContactNameComponentValue;
                                    break;

                                case 'suffix':
                                    $vCardSuffix = $jsContactNameComponentValue;
                                    break;

                                default:
                                    throw new InvalidArgumentException("Unknown value for the \"type\" property
                                    of object NameComponent encountered during conversion to the vCard
                                    N property. Encountered value is: " . $jsContactNameComponentType);
                                    break;
                            }
                        }
                    }
                }
            }
        }
        return array($vCardFamilyName, $vCardGivenName, $vCardAdditionalName, $vCardPrefix, $vCardSuffix);
    }
}
