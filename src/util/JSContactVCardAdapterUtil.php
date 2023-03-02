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

    /**
     * Collect vCard properties of a certain type
     *
     * @return array containing vCard properties that are truly set.
     */
    public static function collectVCardProps($vCardPropStr, $vCardChildren, $vCard)
    {
        $res = null;

        if (in_array($vCardPropStr, $vCardChildren)) {
            $vCardPropsFound = $vCard[$vCardPropStr];

            foreach ($vCardPropsFound as $vCardProp) {
                if (isset($vCardProp)) {
                    array_push($res, $vCardProp);
                }
            }
        }

        return $res;
    }

    /**
     * Convert from vCardType to JSContact context
     *
     * @return array<string, boolean> converted contexts
     */
    public static function convertFromVcardType($vCardProp)
    {
        if (isset($vCardProp['TYPE']) && !empty($vCardProp['TYPE'])) {
            $jsContactContexts = [];

            foreach ($vCardProp['TYPE'] as $paramValue) {
                switch ($paramValue) {
                    case 'home':
                        $jsContactContexts['private'] = true;
                        break;

                    case 'work':
                        $jsContactContexts['work'] = true;
                        break;

                    case 'other':
                        $jsContactContexts = null;
                        break;

                    default:
                        self::$logger = Logger::getInstance();
                        self::$logger->warning("Unknown vCard TYPE parameter value encountered for vCard property " .
                            $vCardProp . " : " . $paramValue);
                        break;
                }

                return AdapterUtil::isSetNotNullAndNotEmpty($jsContactImppContexts) ? $jsContactImppContexts : null;
            }
        }
    }

    /**
     * Convert from JSContact context to vCard Type
     *
     * @return array vCard types
     * TODO this does not work for multiple contexts
     */
    public static function convertFromJscontactContexts($contexts)
    {
        $types = [];

        if (isset($contexts) && !empty($contexts)) {
            foreach ($contexts as $context => $bool) {
                switch ($context) {
                    case 'private':
                        array_push($types, 'home');
                        break;

                    case 'work':
                        array_push($types, 'work');
                        break;

                    default:
                        self::$logger = Logger::getInstance();
                        self::$logger->error("Unknown value for the \"contexts\" property of a
                            OnlineService in JSContact.onlineServices property encountered during
                            conversion to IMPP vCard property.
                            Encountered value is: " . $onlineObjectContext);
                        break;
                }
            }
        } else { // In case $onlineObjectContexts is null, we set the vCard type to be 'other'
            return ['other'];
        }
        return $types;
    }
}
