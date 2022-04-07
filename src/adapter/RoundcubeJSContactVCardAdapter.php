<?php

namespace OpenXPort\Adapter;

use InvalidArgumentException;
use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\Resource;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\Logger;

class RoundcubeJSContactVCardAdapter extends JSContactVCardAdapter
{
    /**
     * This function maps the vCard "BDAY", "BIRTHPLACE", "DEATHDATE", "DEATHPLACE", "ANNIVERSARY"
     * and "X-ANNIVERSARY" properties to the JSContact "anniversaries" property
     *
     * @return array<string, Anniversary>|null The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    // TODO: Should we format anniversaries to contain only date or date with time as well?
    // Currently it's without time
    public function getAnniversaries()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactAnniversariesProperty = null;

        // BDAY property mapping
        if (in_array("BDAY", $this->vCardChildren)) {
            $vCardBirthdayProperty = $this->vCard->BDAY;

            if (isset($vCardBirthdayProperty)) {
                $vCardBirthdayPropertyValue = $vCardBirthdayProperty->getValue();

                // Only if the vCard BDAY property indeed has a value, we transform it as a date string to
                // follow JSContact's date format and set it as value for "date" in an Anniversary object
                // which in turn is an element of "anniversaries" in JSContact
                if (isset($vCardBirthdayPropertyValue) && !empty($vCardBirthdayPropertyValue)) {
                    // Restructure the date string value to follow JSContact's format
                    $jsContactBirthdayPropertyValue = AdapterUtil::parseDateTime(
                        $vCardBirthdayPropertyValue,
                        'Y-m-d',
                        'Y-m-d'
                    );

                    // In case we couldn't parse the BDAY value to JSContact's date format (i.e., it's null),
                    // set the JSContact value to all zeros (default value)
                    if (is_null($jsContactBirthdayPropertyValue)) {
                        $jsContactBirthdayPropertyValue = "0000-00-00";
                    }

                    $jsContactBirthday = new Anniversary();
                    $jsContactBirthday->setAtType("Anniversary");
                    $jsContactBirthday->setType("birth");
                    $jsContactBirthday->setDate($jsContactBirthdayPropertyValue);

                    // In case BIRTHPLACE is present in the vCard, set it as "place" within the JSContact
                    // birthday Anniversary object
                    if (in_array("BIRTHPLACE", $this->vCardChildren)) {
                        $vCardBirthdayPlaceProperty = $this->vCard->BIRTHPLACE;

                        if (isset($vCardBirthdayPlaceProperty)) {
                            $vCardBirthdayPlacePropertyValue = $vCardBirthdayPlaceProperty->getValue();

                            if (isset($vCardBirthdayPlacePropertyValue) && !empty($vCardBirthdayPlacePropertyValue)) {
                                $jsContactBirthdayPlace = new Address();
                                $jsContactBirthdayPlace->setAtType("Address");

                                // If place is geo URL, then add it to "coordinates" prop of address,
                                // else add it to "fullAddress"
                                if (str_starts_with($vCardBirthdayPlacePropertyValue, "geo:")) {
                                    $jsContactBirthdayPlace->setCoordinates($vCardBirthdayPlacePropertyValue);
                                } else {
                                    $jsContactBirthdayPlace->setFullAddress($vCardBirthdayPlacePropertyValue);
                                }

                                $jsContactBirthday->setPlace($jsContactBirthdayPlace);
                            }

                            // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                            // If yes, then provide an error log with some information that they're not supported
                            if (
                                isset($vCardBirthdayPlaceProperty['ALTID'])
                                && !empty($vCardBirthdayPlaceProperty['ALTID'])
                            ) {
                                $this->logger = Logger::getInstance();
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter ALTID encountered
                                    for vCard property BIRTHPLACE"
                                );
                            }

                            if (
                                isset($vCardBirthdayPlaceProperty['LANGUAGE'])
                                && !empty($vCardBirthdayPlaceProperty['LANGUAGE'])
                            ) {
                                $this->logger = Logger::getInstance();
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter LANGUAGE encountered
                                    for vCard property BIRTHPLACE"
                                );
                            }
                        }
                    }

                    // Since "anniversaries" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the BDAY property's value to create the key of the entry in "anniversaries"
                    $jsContactAnniversariesProperty[md5($vCardBirthdayPropertyValue)] = $jsContactBirthday;
                }
            }
        }

        // DEATHDATE property mapping
        if (in_array("DEATHDATE", $this->vCardChildren)) {
            $vCardDeathdateProperty = $this->vCard->DEATHDATE;

            if (isset($vCardDeathdateProperty)) {
                $vCardDeathdatePropertyValue = $vCardDeathdateProperty->getValue();

                // Only if the vCard DEATHDATE property indeed has a value, we transform it as a date string to
                // follow JSContact's date format and set it as value for "date" in an Anniversary object
                // which in turn is an element of "anniversaries" in JSContact
                if (isset($vCardDeathdatePropertyValue) && !empty($vCardDeathdatePropertyValue)) {
                    // Restructure the date string value to follow JSContact's format
                    $jsContactDeathdatePropertyValue = AdapterUtil::parseDateTime(
                        $vCardDeathdatePropertyValue,
                        'Y-m-d',
                        'Y-m-d'
                    );

                    // In case we couldn't parse the DEATHDATE value to JSContact's date format (i.e., it's null),
                    // set the JSContact value to all zeros (default value)
                    if (is_null($jsContactDeathdatePropertyValue)) {
                        $jsContactDeathdatePropertyValue = "0000-00-00";
                    }

                    $jsContactDeathdate = new Anniversary();
                    $jsContactDeathdate->setAtType("Anniversary");
                    $jsContactDeathdate->setType("death");
                    $jsContactDeathdate->setDate($jsContactDeathdatePropertyValue);

                    // In case DEATHPLACE is present in the vCard, set it as "place" within the JSContact
                    // deathdate Anniversary object
                    if (in_array("DEATHPLACE", $this->vCardChildren)) {
                        $vCardDeathdatePlaceProperty = $this->vCard->DEATHPLACE;

                        if (isset($vCardDeathdatePlaceProperty)) {
                            $vCardDeathdatePlacePropertyValue = $vCardDeathdatePlaceProperty->getValue();

                            if (isset($vCardDeathdatePlacePropertyValue) && !empty($vCardDeathdatePlacePropertyValue)) {
                                $jsContactDeathdatePlace = new Address();
                                $jsContactDeathdatePlace->setAtType("Address");

                                // If place is geo URL, then add it to "coordinates" prop of address,
                                // else add it to "fullAddress"
                                if (str_starts_with($vCardDeathdatePlacePropertyValue, "geo:")) {
                                    $jsContactDeathdatePlace->setCoordinates($vCardDeathdatePlacePropertyValue);
                                } else {
                                    $jsContactDeathdatePlace->setFullAddress($vCardDeathdatePlacePropertyValue);
                                }

                                $jsContactDeathdate->setPlace($jsContactDeathdatePlace);
                            }

                            // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                            // If yes, then provide an error log with some information that they're not supported
                            if (
                                isset($vCardDeathdatePlaceProperty['ALTID'])
                                && !empty($vCardDeathdatePlaceProperty['ALTID'])
                            ) {
                                $this->logger = Logger::getInstance();
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter ALTID encountered
                                    for vCard property DEATHPLACE"
                                );
                            }

                            if (
                                isset($vCardDeathdatePlaceProperty['LANGUAGE'])
                                && !empty($vCardDeathdatePlaceProperty['LANGUAGE'])
                            ) {
                                $this->logger = Logger::getInstance();
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter LANGUAGE encountered
                                    for vCard property DEATHPLACE"
                                );
                            }
                        }
                    }

                    // Since "anniversaries" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the DEATHDATE property's value to create the key of the entry in "anniversaries"
                    $jsContactAnniversariesProperty[md5($vCardDeathdatePropertyValue)] = $jsContactDeathdate;
                }
            }
        }

        // ANNIVERSARY property mapping
        if (in_array("ANNIVERSARY", $this->vCardChildren)) {
            $vCardAnniversaryProperty = $this->vCard->ANNIVERSARY;

            if (isset($vCardAnniversaryProperty)) {
                $vCardAnniversaryPropertyValue = $vCardAnniversaryProperty->getValue();

                // Only if the vCard ANNIVERSARY property indeed has a value, we transform it as a date string to
                // follow JSContact's date format and set it as value for "date" in an Anniversary object
                // which in turn is an element of "anniversaries" in JSContact
                if (isset($vCardAnniversaryPropertyValue) && !empty($vCardAnniversaryPropertyValue)) {
                    // Restructure the date string value to follow JSContact's format
                    $jsContactAnniversaryPropertyValue = AdapterUtil::parseDateTime(
                        $vCardAnniversaryPropertyValue,
                        'Y-m-d',
                        'Y-m-d'
                    );

                    // In case we couldn't parse the ANNIVERSARY value to JSContact's date format (i.e., it's null),
                    // set the JSContact value to all zeros (default value)
                    if (is_null($jsContactAnniversaryPropertyValue)) {
                        $jsContactAnniversaryPropertyValue = "0000-00-00";
                    }

                    $jsContactAnniversary = new Anniversary();
                    $jsContactAnniversary->setAtType("Anniversary");

                    // For ANNIVERSARY, we're supposed to set the corresponding JSContact Anniversary object's "label"
                    // to some meaningful value. In this case, we just use the value of "anniversary", since we don't
                    // have any further specifics about what to include in the value.
                    // Note: "type" of the JSContact Anniversary object is not set here.
                    $jsContactAnniversary->setLabel("anniversary");
                    $jsContactAnniversary->setDate($jsContactAnniversaryPropertyValue);

                    // Since "anniversaries" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the ANNIVERSARY property value to create the key of the entry in "anniversaries"
                    $jsContactAnniversariesProperty[md5($vCardAnniversaryPropertyValue)] = $jsContactAnniversary;
                }
            }
        }

        // X-ANNIVERSARY property mapping
        // Note: The X-ANNIVERSARY vCard property is Roundcube-specific
        if (in_array("X-ANNIVERSARY", $this->vCardChildren)) {
            $vCardXAnniversaryProperty = $this->vCard->__get("X-ANNIVERSARY");

            if (isset($vCardXAnniversaryProperty)) {
                $vCardXAnniversaryPropertyValue = $vCardXAnniversaryProperty->getValue();

                // Only if the vCard X-ANNIVERSARY property indeed has a value, we transform it as a date string to
                // follow JSContact's date format and set it as value for "date" in an Anniversary object
                // which in turn is an element of "anniversaries" in JSContact
                if (isset($vCardXAnniversaryPropertyValue) && !empty($vCardXAnniversaryPropertyValue)) {
                    // Restructure the date string value to follow JSContact's format
                    $jsContactXAnniversaryPropertyValue = AdapterUtil::parseDateTime(
                        $vCardXAnniversaryPropertyValue,
                        'Y-m-d',
                        'Y-m-d'
                    );

                    // In case we couldn't parse the X-ANNIVERSARY value to JSContact's date format (i.e., it's null),
                    // set the JSContact value to all zeros (default value)
                    if (is_null($jsContactXAnniversaryPropertyValue)) {
                        $jsContactXAnniversaryPropertyValue = "0000-00-00";
                    }

                    $jsContactXAnniversary = new Anniversary();
                    $jsContactXAnniversary->setAtType("Anniversary");

                    // For X-ANNIVERSARY, we're supposed to set the corresponding JSContact Anniversary object's "label"
                    // to some meaningful value. In this case, we just use the value of "x-anniversary", since we don't
                    // have any further specifics about what to include in the value.
                    // Note: "type" of the JSContact Anniversary object is not set here.
                    // TODO: Most probably we shouldn't enforce "label" to be always set
                    $jsContactXAnniversary->setLabel("x-anniversary");
                    $jsContactXAnniversary->setDate($jsContactXAnniversaryPropertyValue);

                    // Since "anniversaries" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the X-ANNIVERSARY property value
                    // to create the key of the entry in "anniversaries"
                    $jsContactAnniversariesProperty[md5($vCardXAnniversaryPropertyValue)] = $jsContactXAnniversary;
                }
            }
        }

        return $jsContactAnniversariesProperty;
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard X-ANNIVERSARY property to it
     * Note: The vCard X-ANNIVERSARY property is Roundcube-specific
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setXAnniversary($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryType = $jsContactAnniversary->type;
                $jsContactAnniversaryValue = $jsContactAnniversary->date;

                if (!isset($jsContactAnniversaryType)) {
                    if (isset($jsContactAnniversaryValue) && !empty($jsContactAnniversaryValue)) {
                        $vCardXAnniversaryValue = AdapterUtil::parseDateTime(
                            $jsContactAnniversaryValue,
                            'Y-m-d\TH:i:s\Z',
                            'Y-m-d',
                            'Y-m-d'
                        );

                        if (is_null($vCardXAnniversaryValue)) {
                            throw new InvalidArgumentException(
                                "Couldn't parse JSContact anniversary date to vCard X-ANNIVERSARY.
                                JSContact date encountered is: " . $jsContactAnniversaryValue
                            );
                            return;
                        }

                        $this->vCard->add("X-ANNIVERSARY", $vCardXAnniversaryValue);
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard "TEL" property to the JSContact "phones" property
     *
     * @return array<string, Phone>|null The "addresses" JSContact property as a map of IDs to Phone objects
     */
    public function getPhones()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactPhonesProperty = null;

        // TEL property mapping
        if (in_array("TEL", $this->vCardChildren)) {
            $vCardPhoneProperties = $this->vCard->TEL;

            foreach ($vCardPhoneProperties as $vCardPhoneProperty) {
                if (isset($vCardPhoneProperty)) {
                    $vCardPhonePropertyValue = $vCardPhoneProperty->getValue();

                    if (isset($vCardPhonePropertyValue) && !empty($vCardPhonePropertyValue)) {
                        $jsContactPhone = new Phone();
                        $jsContactPhone->setAtType("Phone");
                        $jsContactPhone->setPhone($vCardPhonePropertyValue);

                        // Map the TYPE parameter to "contexts" or "features"
                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardPhoneProperty['TYPE'])) {
                            $jsContactPhoneContexts = [];
                            $jsContactPhoneFeatures = [];
                            $jsContactPhoneLabels = [];

                            // The TYPE parameter can have multiple values and hence be an array. That's why we iterate
                            // over its values below for conversion purposes
                            foreach ($vCardPhoneProperty['TYPE'] as $paramValue) {
                                // The 'home' and 'work' TYPE values are put into the "contexts" property
                                // The rest of the phone-related values are put into the "features" property
                                // Finally, anything else (i.e., unknown values) is put into the "labels" property
                                switch ($paramValue) {
                                    case 'home':
                                    case 'home2':
                                        $jsContactPhoneContexts['private'] = true;
                                        break;

                                    case 'work':
                                    case 'work2':
                                        $jsContactPhoneContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactPhoneContexts = null;
                                        break;

                                    case 'text':
                                        $jsContactPhoneFeatures['text'] = true;
                                        break;

                                    case 'voice':
                                        $jsContactPhoneFeatures['voice'] = true;
                                        break;

                                    case 'fax':
                                    case 'homefax':
                                    case 'workfax':
                                        $jsContactPhoneFeatures['fax'] = true;
                                        break;

                                    case 'cell':
                                    case 'CELL':
                                        $jsContactPhoneFeatures['cell'] = true;
                                        break;

                                    case 'video':
                                        $jsContactPhoneFeatures['video'] = true;
                                        break;

                                    case 'pager':
                                        $jsContactPhoneFeatures['pager'] = true;
                                        break;

                                    case 'textphone':
                                        $jsContactPhoneFeatures['textphone'] = true;
                                        break;

                                    default:
                                        $jsContactPhoneLabels[] = $paramValue;
                                        break;
                                }
                            }

                            $jsContactPhone->setContexts(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactPhoneContexts)
                                ? $jsContactPhoneContexts
                                : null
                            );

                            $jsContactPhone->setFeatures(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactPhoneFeatures)
                                ? $jsContactPhoneFeatures
                                : null
                            );

                            $jsContactPhone->setLabel(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactPhoneLabels)
                                ? implode(",", $jsContactPhoneLabels)
                                : null
                            );
                        }

                        // Map the PREF parameter to "pref"
                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardPhoneProperty['PREF'])) {
                            $jsContactPhone->setPref($vCardPhoneProperty['PREF']);
                        }
                    }

                    // Since "phones" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the vCard TEL's value as the key for the JSContact Phone
                    // object that corresponds to this key in "phones"
                    $jsContactPhonesProperty[md5($vCardPhonePropertyValue)] = $jsContactPhone;
                }
            }
        }

        return $jsContactPhonesProperty;
    }

    /**
     * This function translates all necessary vCard properties to the JSContact "online" property
     *
     * The vCard properties that map to "online" are:
     *  * SOURCE
     *  * IMPP
     *  * LOGO
     *  * CONTACT-URI
     *  * ORG-DIRECTORY
     *  * SOUND
     *  * URL
     *  * KEY
     *  * FBURL
     *  * CALADRURI
     *  * CALURI
     *  * X-AIM
     *  * X-ICQ
     *  * X-MSN
     *  * X-YAHOO
     *  * X-JABBER
     *  * X-SKYPE-USERNAME
     *
     * @return array<string, Resource>|null The "online" JSContact property as a map of IDs to Resource objects
     */
    public function getOnline()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactOnlineProperty = null;

        // SOURCE property mapping
        if (in_array("SOURCE", $this->vCardChildren)) {
            $vCardSourceProperties = $this->vCard->SOURCE;

            foreach ($vCardSourceProperties as $vCardSourceProperty) {
                if (isset($vCardSourceProperty)) {
                    $vCardSourcePropertyValue = $vCardSourceProperty->getValue();

                    // Only if the vCard SOURCE property indeed has a value, create a
                    // corresponding entry in the JSContact "online" property
                    if (isset($vCardSourcePropertyValue) && !empty($vCardSourcePropertyValue)) {
                        $jsContactSourceEntry = new Resource();
                        $jsContactSourceEntry->setAtType("Resource");
                        $jsContactSourceEntry->setType('uri');
                        $jsContactSourceEntry->setLabel('source');
                        $jsContactSourceEntry->setResource($vCardSourcePropertyValue);

                        if (isset($vCardSourceProperty['PREF']) && !empty($vCardSourceProperty['PREF'])) {
                            $jsContactSourceEntry->setPref($vCardSourceProperty['PREF']);
                        }

                        if (isset($vCardSourceProperty['MEDIATYPE']) && !empty($vCardSourceProperty['MEDIATYPE'])) {
                            $jsContactSourceEntry->setMediaType($vCardSourceProperty['MEDIATYPE']);
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the SOURCE property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardSourcePropertyValue)] = $jsContactSourceEntry;
                    }
                }
            }
        }

        // IMPP property mapping
        if (in_array("IMPP", $this->vCardChildren)) {
            $vCardImppProperties = $this->vCard->IMPP;

            foreach ($vCardImppProperties as $vCardImppProperty) {
                if (isset($vCardImppProperty)) {
                    $vCardImppPropertyValue = $vCardImppProperty->getValue();

                    if (isset($vCardImppPropertyValue) && !empty($vCardImppPropertyValue)) {
                        $jsContactImppEntry = new Resource();
                        $jsContactImppEntry->setAtType("Resource");
                        $jsContactImppEntry->setType("username");
                        $jsContactImppEntry->setLabel("XMPP");
                        $jsContactImppEntry->setResource($vCardImppPropertyValue);

                        if (isset($vCardImppProperty['PREF']) && !empty($vCardImppProperty['PREF'])) {
                            $jsContactImppEntry->setPref($vCardImppProperty['PREF']);
                        }

                        if (isset($vCardImppProperty['TYPE']) && !empty($vCardImppProperty['TYPE'])) {
                            $jsContactImppContexts = [];

                            foreach ($vCardImppProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactImppContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactImppContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactImppContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property IMPP: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactImppEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactImppContexts)
                                    ? $jsContactImppContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the IMPP property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardImppPropertyValue)] = $jsContactImppEntry;
                    }
                }
            }
        }

        // LOGO property mapping
        if (in_array("LOGO", $this->vCardChildren)) {
            $vCardLogoProperties = $this->vCard->LOGO;

            foreach ($vCardLogoProperties as $vCardLogoProperty) {
                if (isset($vCardLogoProperty)) {
                    $vCardLogoPropertyValue = $vCardLogoProperty->getValue();

                    if (isset($vCardLogoPropertyValue) && !empty($vCardLogoPropertyValue)) {
                        $jsContactLogoEntry = new Resource();
                        $jsContactLogoEntry->setAtType("Resource");
                        $jsContactLogoEntry->setType("uri");
                        $jsContactLogoEntry->setLabel("logo");
                        $jsContactLogoEntry->setResource($vCardLogoPropertyValue);

                        if (isset($vCardLogoProperty['PREF']) && !empty($vCardLogoProperty['PREF'])) {
                            $jsContactLogoEntry->setPref($vCardLogoProperty['PREF']);
                        }

                        if (isset($vCardLogoProperty['TYPE']) && !empty($vCardLogoProperty['TYPE'])) {
                            $jsContactLogoContexts = [];

                            foreach ($vCardLogoProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactLogoContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactLogoContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactLogoContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property LOGO: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactLogoEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactLogoContexts)
                                    ? $jsContactLogoContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the LOGO property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardLogoPropertyValue)] = $jsContactLogoEntry;
                    }
                }
            }
        }

        // CONTACT-URI property mapping
        if (in_array("CONTACT-URI", $this->vCardChildren)) {
            $vCardContactUriProperties = $this->vCard->__get("CONTACT-URI");

            foreach ($vCardContactUriProperties as $vCardContactUriProperty) {
                if (isset($vCardContactUriProperty)) {
                    $vCardContactUriPropertyValue = $vCardContactUriProperty->getValue();

                    if (isset($vCardContactUriPropertyValue) && !empty($vCardContactUriPropertyValue)) {
                        $jsContactContactUriEntry = new Resource();
                        $jsContactContactUriEntry->setAtType("Resource");
                        $jsContactContactUriEntry->setType("uri");
                        $jsContactContactUriEntry->setLabel("contact-uri");
                        $jsContactContactUriEntry->setResource($vCardContactUriPropertyValue);

                        if (isset($vCardContactUriProperty['PREF']) && !empty($vCardContactUriProperty['PREF'])) {
                            $jsContactContactUriEntry->setPref($vCardContactUriProperty['PREF']);
                        }

                        if (isset($vCardContactUriProperty['TYPE']) && !empty($vCardContactUriProperty['TYPE'])) {
                            $jsContactContactUriContexts = [];

                            foreach ($vCardContactUriProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactContactUriContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactContactUriContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactContactUriContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property CONTACT-URI: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactContactUriEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactContactUriContexts)
                                    ? $jsContactContactUriContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the CONTACT-URI property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardContactUriPropertyValue)] = $jsContactContactUriEntry;
                    }
                }
            }
        }

        // ORG-DIRECTORY property mapping
        if (in_array("ORG-DIRECTORY", $this->vCardChildren)) {
            $vCardOrgDirectoryProperties = $this->vCard->__get("ORG-DIRECTORY");

            foreach ($vCardOrgDirectoryProperties as $vCardOrgDirectoryProperty) {
                if (isset($vCardOrgDirectoryProperty)) {
                    $vCardOrgDirectoryPropertyValue = $vCardOrgDirectoryProperty->getValue();

                    if (isset($vCardOrgDirectoryPropertyValue) && !empty($vCardOrgDirectoryPropertyValue)) {
                        $jsContactOrgDirectoryEntry = new Resource();
                        $jsContactOrgDirectoryEntry->setAtType("Resource");
                        $jsContactOrgDirectoryEntry->setType("uri");
                        $jsContactOrgDirectoryEntry->setLabel("org-directory");
                        $jsContactOrgDirectoryEntry->setResource($vCardOrgDirectoryPropertyValue);

                        if (isset($vCardOrgDirectoryProperty['PREF']) && !empty($vCardOrgDirectoryProperty['PREF'])) {
                            $jsContactOrgDirectoryEntry->setPref($vCardOrgDirectoryProperty['PREF']);
                        }

                        if (isset($vCardOrgDirectoryProperty['TYPE']) && !empty($vCardOrgDirectoryProperty['TYPE'])) {
                            $jsContactOrgDirectoryContexts = [];

                            foreach ($vCardOrgDirectoryProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactOrgDirectoryContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactOrgDirectoryContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactOrgDirectoryContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property ORG-DIRECTORY: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactOrgDirectoryEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactOrgDirectoryContexts)
                                    ? $jsContactOrgDirectoryContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the ORG-DIRECTORY property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardOrgDirectoryPropertyValue)] = $jsContactOrgDirectoryEntry;
                    }
                }
            }
        }

        // SOUND property mapping
        if (in_array("SOUND", $this->vCardChildren)) {
            $vCardSoundProperties = $this->vCard->SOUND;

            foreach ($vCardSoundProperties as $vCardSoundProperty) {
                if (isset($vCardSoundProperty)) {
                    $vCardSoundPropertyValue = $vCardSoundProperty->getValue();

                    if (isset($vCardSoundPropertyValue) && !empty($vCardSoundPropertyValue)) {
                        $jsContactSoundEntry = new Resource();
                        $jsContactSoundEntry->setAtType("Resource");
                        $jsContactSoundEntry->setType("uri");
                        $jsContactSoundEntry->setLabel("sound");
                        $jsContactSoundEntry->setResource($vCardSoundPropertyValue);

                        if (isset($vCardSoundProperty['PREF']) && !empty($vCardSoundProperty['PREF'])) {
                            $jsContactSoundEntry->setPref($vCardSoundProperty['PREF']);
                        }

                        if (isset($vCardSoundProperty['TYPE']) && !empty($vCardSoundProperty['TYPE'])) {
                            $jsContactSoundContexts = [];

                            foreach ($vCardSoundProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactSoundContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactSoundContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactSoundContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property SOUND: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactSoundEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactSoundContexts)
                                    ? $jsContactSoundContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the SOUND property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardSoundPropertyValue)] = $jsContactSoundEntry;
                    }
                }
            }
        }

        // URL property mapping
        if (in_array("URL", $this->vCardChildren)) {
            $vCardUrlProperties = $this->vCard->URL;

            foreach ($vCardUrlProperties as $vCardUrlProperty) {
                if (isset($vCardUrlProperty)) {
                    $vCardUrlPropertyValue = $vCardUrlProperty->getValue();

                    if (isset($vCardUrlPropertyValue) && !empty($vCardUrlPropertyValue)) {
                        $jsContactUrlEntry = new Resource();
                        $jsContactUrlEntry->setAtType("Resource");
                        $jsContactUrlEntry->setType("uri");
                        $jsContactUrlEntry->setLabel("url");
                        $jsContactUrlEntry->setResource($vCardUrlPropertyValue);

                        if (isset($vCardUrlProperty['PREF']) && !empty($vCardUrlProperty['PREF'])) {
                            $jsContactUrlEntry->setPref($vCardUrlProperty['PREF']);
                        }

                        if (isset($vCardUrlProperty['TYPE']) && !empty($vCardUrlProperty['TYPE'])) {
                            $jsContactUrlContexts = [];

                            foreach ($vCardUrlProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactUrlContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactUrlContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactUrlContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property URL: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactUrlEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactUrlContexts)
                                    ? $jsContactUrlContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the URL property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardUrlPropertyValue)] = $jsContactUrlEntry;
                    }
                }
            }
        }

        // KEY property mapping
        if (in_array("KEY", $this->vCardChildren)) {
            $vCardKeyProperties = $this->vCard->KEY;

            foreach ($vCardKeyProperties as $vCardKeyProperty) {
                if (isset($vCardKeyProperty)) {
                    $vCardKeyPropertyValue = $vCardKeyProperty->getValue();

                    if (isset($vCardKeyPropertyValue) && !empty($vCardKeyPropertyValue)) {
                        $jsContactKeyEntry = new Resource();
                        $jsContactKeyEntry->setAtType("Resource");
                        $jsContactKeyEntry->setType("uri");
                        $jsContactKeyEntry->setLabel("key");
                        $jsContactKeyEntry->setResource($vCardKeyPropertyValue);

                        if (isset($vCardKeyProperty['PREF']) && !empty($vCardKeyProperty['PREF'])) {
                            $jsContactKeyEntry->setPref($vCardKeyProperty['PREF']);
                        }

                        if (isset($vCardKeyProperty['TYPE']) && !empty($vCardKeyProperty['TYPE'])) {
                            $jsContactKeyContexts = [];

                            foreach ($vCardKeyProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactKeyContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactKeyContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactKeyContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property KEY: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactKeyEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactKeyContexts)
                                    ? $jsContactKeyContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the KEY property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardKeyPropertyValue)] = $jsContactKeyEntry;
                    }
                }
            }
        }

        // FBURL property mapping
        if (in_array("FBURL", $this->vCardChildren)) {
            $vCardFbUrlProperties = $this->vCard->FBURL;

            foreach ($vCardFbUrlProperties as $vCardFbUrlProperty) {
                if (isset($vCardFbUrlProperty)) {
                    $vCardFbUrlPropertyValue = $vCardFbUrlProperty->getValue();

                    if (isset($vCardFbUrlPropertyValue) && !empty($vCardFbUrlPropertyValue)) {
                        $jsContactFbUrlEntry = new Resource();
                        $jsContactFbUrlEntry->setAtType("Resource");
                        $jsContactFbUrlEntry->setType("uri");
                        $jsContactFbUrlEntry->setLabel("fburl");
                        $jsContactFbUrlEntry->setResource($vCardFbUrlPropertyValue);

                        if (isset($vCardFbUrlProperty['PREF']) && !empty($vCardFbUrlProperty['PREF'])) {
                            $jsContactFbUrlEntry->setPref($vCardFbUrlProperty['PREF']);
                        }

                        if (isset($vCardFbUrlProperty['TYPE']) && !empty($vCardFbUrlProperty['TYPE'])) {
                            $jsContactFbUrlContexts = [];

                            foreach ($vCardFbUrlProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactFbUrlContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactFbUrlContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactFbUrlContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property FBURL: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactFbUrlEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactFbUrlContexts)
                                    ? $jsContactFbUrlContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the FBURL property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardFbUrlPropertyValue)] = $jsContactFbUrlEntry;
                    }
                }
            }
        }

        // CALADRURI property mapping
        if (in_array("CALADRURI", $this->vCardChildren)) {
            $vCardCalAdrUriProperties = $this->vCard->CALADRURI;

            foreach ($vCardCalAdrUriProperties as $vCardCalAdrUriProperty) {
                if (isset($vCardCalAdrUriProperty)) {
                    $vCardCalAdrUriPropertyValue = $vCardCalAdrUriProperty->getValue();

                    if (isset($vCardCalAdrUriPropertyValue) && !empty($vCardCalAdrUriPropertyValue)) {
                        $jsContactCalAdrUriEntry = new Resource();
                        $jsContactCalAdrUriEntry->setAtType("Resource");
                        $jsContactCalAdrUriEntry->setType("uri");
                        $jsContactCalAdrUriEntry->setLabel("caladruri");
                        $jsContactCalAdrUriEntry->setResource($vCardCalAdrUriPropertyValue);

                        if (isset($vCardCalAdrUriProperty['PREF']) && !empty($vCardCalAdrUriProperty['PREF'])) {
                            $jsContactCalAdrUriEntry->setPref($vCardCalAdrUriProperty['PREF']);
                        }

                        if (isset($vCardCalAdrUriProperty['TYPE']) && !empty($vCardCalAdrUriProperty['TYPE'])) {
                            $jsContactCalAdrUriContexts = [];

                            foreach ($vCardCalAdrUriProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactCalAdrUriContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactCalAdrUriContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactCalAdrUriContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property CALADRURI: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactCalAdrUriEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactCalAdrUriContexts)
                                    ? $jsContactCalAdrUriContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the CALADRURI property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardCalAdrUriPropertyValue)] = $jsContactCalAdrUriEntry;
                    }
                }
            }
        }

        // CALURI property mapping
        if (in_array("CALURI", $this->vCardChildren)) {
            $vCardCalUriProperties = $this->vCard->CALURI;

            foreach ($vCardCalUriProperties as $vCardCalUriProperty) {
                if (isset($vCardCalUriProperty)) {
                    $vCardCalUriPropertyValue = $vCardCalUriProperty->getValue();

                    if (isset($vCardCalUriPropertyValue) && !empty($vCardCalUriPropertyValue)) {
                        $jsContactCalUriEntry = new Resource();
                        $jsContactCalUriEntry->setAtType("Resource");
                        $jsContactCalUriEntry->setType("uri");
                        $jsContactCalUriEntry->setLabel("caluri");
                        $jsContactCalUriEntry->setResource($vCardCalUriPropertyValue);

                        if (isset($vCardCalUriProperty['PREF']) && !empty($vCardCalUriProperty['PREF'])) {
                            $jsContactCalUriEntry->setPref($vCardCalUriProperty['PREF']);
                        }

                        if (isset($vCardCalUriProperty['TYPE']) && !empty($vCardCalUriProperty['TYPE'])) {
                            $jsContactCalUriContexts = [];

                            foreach ($vCardCalUriProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactCalUriContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactCalUriContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactCalUriContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property CALURI: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactCalUriEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactCalUriContexts)
                                    ? $jsContactCalUriContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the CALURI property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardCalUriPropertyValue)] = $jsContactCalUriEntry;
                    }
                }
            }
        }

        // X-AIM property mapping
        // Note: The vCard X-AIM property is Roundcube-specific
        if (in_array("X-AIM", $this->vCardChildren)) {
            $vCardXAimProperties = $this->vCard->__get("X-AIM");

            foreach ($vCardXAimProperties as $vCardXAimProperty) {
                if (isset($vCardXAimProperty)) {
                    $vCardXAimPropertyValue = $vCardXAimProperty->getValue();

                    if (isset($vCardXAimPropertyValue) && !empty($vCardXAimPropertyValue)) {
                        $jsContactXAimEntry = new Resource();
                        $jsContactXAimEntry->setAtType("Resource");
                        $jsContactXAimEntry->setType("username");
                        $jsContactXAimEntry->setLabel("X-AIM");
                        $jsContactXAimEntry->setResource($vCardXAimPropertyValue);

                        if (isset($vCardXAimProperty['PREF']) && !empty($vCardXAimProperty['PREF'])) {
                            $jsContactXAimEntry->setPref($vCardXAimProperty['PREF']);
                        }

                        if (isset($vCardXAimProperty['TYPE']) && !empty($vCardXAimProperty['TYPE'])) {
                            $jsContactXAimContexts = [];

                            foreach ($vCardXAimProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXAimContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXAimContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXAimContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-AIM: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXAimEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXAimContexts)
                                    ? $jsContactXAimContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-AIM property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXAimPropertyValue)] = $jsContactXAimEntry;
                    }
                }
            }
        }

        // X-ICQ property mapping
        // Note: The vCard X-ICQ property is Roundcube-specific
        if (in_array("X-ICQ", $this->vCardChildren)) {
            $vCardXIcqProperties = $this->vCard->__get("X-ICQ");

            foreach ($vCardXIcqProperties as $vCardXIcqProperty) {
                if (isset($vCardXIcqProperty)) {
                    $vCardXIcqPropertyValue = $vCardXIcqProperty->getValue();

                    if (isset($vCardXIcqPropertyValue) && !empty($vCardXIcqPropertyValue)) {
                        $jsContactXIcqEntry = new Resource();
                        $jsContactXIcqEntry->setAtType("Resource");
                        $jsContactXIcqEntry->setType("username");
                        $jsContactXIcqEntry->setLabel("X-ICQ");
                        $jsContactXIcqEntry->setResource($vCardXIcqPropertyValue);

                        if (isset($vCardXIcqProperty['PREF']) && !empty($vCardXIcqProperty['PREF'])) {
                            $jsContactXIcqEntry->setPref($vCardXIcqProperty['PREF']);
                        }

                        if (isset($vCardXIcqProperty['TYPE']) && !empty($vCardXIcqProperty['TYPE'])) {
                            $jsContactXIcqContexts = [];

                            foreach ($vCardXIcqProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXIcqContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXIcqContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXIcqContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-ICQ: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXIcqEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXIcqContexts)
                                    ? $jsContactXIcqContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-ICQ property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXIcqPropertyValue)] = $jsContactXIcqEntry;
                    }
                }
            }
        }

        // X-MSN property mapping
        // Note: The vCard X-MSN property is Roundcube-specific
        if (in_array("X-MSN", $this->vCardChildren)) {
            $vCardXMsnProperties = $this->vCard->__get("X-MSN");

            foreach ($vCardXMsnProperties as $vCardXMsnProperty) {
                if (isset($vCardXMsnProperty)) {
                    $vCardXMsnPropertyValue = $vCardXMsnProperty->getValue();

                    if (isset($vCardXMsnPropertyValue) && !empty($vCardXMsnPropertyValue)) {
                        $jsContactXMsnEntry = new Resource();
                        $jsContactXMsnEntry->setAtType("Resource");
                        $jsContactXMsnEntry->setType("username");
                        $jsContactXMsnEntry->setLabel("X-MSN");
                        $jsContactXMsnEntry->setResource($vCardXMsnPropertyValue);

                        if (isset($vCardXMsnProperty['PREF']) && !empty($vCardXMsnProperty['PREF'])) {
                            $jsContactXMsnEntry->setPref($vCardXMsnProperty['PREF']);
                        }

                        if (isset($vCardXMsnProperty['TYPE']) && !empty($vCardXMsnProperty['TYPE'])) {
                            $jsContactXMsnContexts = [];

                            foreach ($vCardXMsnProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXMsnContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXMsnContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXMsnContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-MSN: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXMsnEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXMsnContexts)
                                    ? $jsContactXMsnContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-MSN property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXMsnPropertyValue)] = $jsContactXMsnEntry;
                    }
                }
            }
        }

        // X-YAHOO property mapping
        // Note: The vCard X-YAHOO property is Roundcube-specific
        if (in_array("X-YAHOO", $this->vCardChildren)) {
            $vCardXYahooProperties = $this->vCard->__get("X-YAHOO");

            foreach ($vCardXYahooProperties as $vCardXYahooProperty) {
                if (isset($vCardXYahooProperty)) {
                    $vCardXYahooPropertyValue = $vCardXYahooProperty->getValue();

                    if (isset($vCardXYahooPropertyValue) && !empty($vCardXYahooPropertyValue)) {
                        $jsContactXYahooEntry = new Resource();
                        $jsContactXYahooEntry->setAtType("Resource");
                        $jsContactXYahooEntry->setType("username");
                        $jsContactXYahooEntry->setLabel("X-YAHOO");
                        $jsContactXYahooEntry->setResource($vCardXYahooPropertyValue);

                        if (isset($vCardXYahooProperty['PREF']) && !empty($vCardXYahooProperty['PREF'])) {
                            $jsContactXYahooEntry->setPref($vCardXYahooProperty['PREF']);
                        }

                        if (isset($vCardXYahooProperty['TYPE']) && !empty($vCardXYahooProperty['TYPE'])) {
                            $jsContactXYahooContexts = [];

                            foreach ($vCardXYahooProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXYahooContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXYahooContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXYahooContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-YAHOO: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXYahooEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXYahooContexts)
                                    ? $jsContactXYahooContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-YAHOO property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXYahooPropertyValue)] = $jsContactXYahooEntry;
                    }
                }
            }
        }

        // X-JABBER property mapping
        // Note: The vCard X-JABBER property is Roundcube-specific
        if (in_array("X-JABBER", $this->vCardChildren)) {
            $vCardXJabberProperties = $this->vCard->__get("X-JABBER");

            foreach ($vCardXJabberProperties as $vCardXJabberProperty) {
                if (isset($vCardXJabberProperty)) {
                    $vCardXJabberPropertyValue = $vCardXJabberProperty->getValue();

                    if (isset($vCardXJabberPropertyValue) && !empty($vCardXJabberPropertyValue)) {
                        $jsContactXJabberEntry = new Resource();
                        $jsContactXJabberEntry->setAtType("Resource");
                        $jsContactXJabberEntry->setType("username");
                        $jsContactXJabberEntry->setLabel("X-JABBER");
                        $jsContactXJabberEntry->setResource($vCardXJabberPropertyValue);

                        if (isset($vCardXJabberProperty['PREF']) && !empty($vCardXJabberProperty['PREF'])) {
                            $jsContactXJabberEntry->setPref($vCardXJabberProperty['PREF']);
                        }

                        if (isset($vCardXJabberProperty['TYPE']) && !empty($vCardXJabberProperty['TYPE'])) {
                            $jsContactXJabberContexts = [];

                            foreach ($vCardXJabberProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXJabberContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXJabberContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXJabberContexts = null;
                                        break;

                                    default:
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-JABBER: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXJabberEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXJabberContexts)
                                    ? $jsContactXJabberContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-JABBER property's value to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXJabberPropertyValue)] = $jsContactXJabberEntry;
                    }
                }
            }
        }

        // X-SKYPE-USERNAME property mapping
        // Note: The vCard X-SKYPE-USERNAME property is Roundcube-specific
        if (in_array("X-SKYPE-USERNAME", $this->vCardChildren)) {
            $vCardXSkypeUsernameProperties = $this->vCard->__get("X-SKYPE-USERNAME");

            foreach ($vCardXSkypeUsernameProperties as $vCardXSkypeUsernameProperty) {
                if (isset($vCardXSkypeUsernameProperty)) {
                    $vCardXSkypeUsernamePropertyValue = $vCardXSkypeUsernameProperty->getValue();

                    if (isset($vCardXSkypeUsernamePropertyValue) && !empty($vCardXSkypeUsernamePropertyValue)) {
                        $jsContactXSkypeUsernameEntry = new Resource();
                        $jsContactXSkypeUsernameEntry->setAtType("Resource");
                        $jsContactXSkypeUsernameEntry->setType("username");
                        $jsContactXSkypeUsernameEntry->setLabel("X-SKYPE-USERNAME");
                        $jsContactXSkypeUsernameEntry->setResource($vCardXSkypeUsernamePropertyValue);

                        if (
                            isset($vCardXSkypeUsernameProperty['PREF'])
                            && !empty($vCardXSkypeUsernameProperty['PREF'])
                        ) {
                            $jsContactXSkypeUsernameEntry->setPref($vCardXSkypeUsernameProperty['PREF']);
                        }

                        if (
                            isset($vCardXSkypeUsernameProperty['TYPE'])
                            && !empty($vCardXSkypeUsernameProperty['TYPE'])
                        ) {
                            $jsContactXSkypeUsernameContexts = [];

                            foreach ($vCardXSkypeUsernameProperty['TYPE'] as $paramValue) {
                                switch ($paramValue) {
                                    case 'home':
                                        $jsContactXSkypeUsernameContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactXSkypeUsernameContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactXSkypeUsernameContexts = null;
                                        break;

                                    default:
                                        // Use the audriga-specific value "audriga.eu:other" to designate
                                        // other values for contexts
                                        $jsContactXSkypeUsernameContexts['audriga.eu:other'] = true;
                                        $this->logger = Logger::getInstance();
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property X-SKYPE-USERNAME: " . $paramValue
                                        );
                                        break;
                                }

                                $jsContactXSkypeUsernameEntry->setContexts(
                                    AdapterUtil::isSetNotNullAndNotEmpty($jsContactXSkypeUsernameContexts)
                                    ? $jsContactXSkypeUsernameContexts
                                    : null
                                );
                            }
                        }

                        // Since "online" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the X-SKYPE-USERNAME property's value
                        // to create the key of the entry in "online"
                        $jsContactOnlineProperty[md5($vCardXSkypeUsernamePropertyValue)]
                        = $jsContactXSkypeUsernameEntry;
                    }
                }
            }
        }

        return $jsContactOnlineProperty;
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-AIM property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXAim($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXAimParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-AIM") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXAimParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXAimParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-AIM vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXAimParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXAimParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-AIM", $resourceObjectResource, $vCardXAimParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-AIM property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-ICQ property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXIcq($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXIcqParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-ICQ") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXIcqParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXIcqParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-ICQ vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXIcqParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXIcqParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-ICQ", $resourceObjectResource, $vCardXIcqParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-ICQ property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-MSN property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXMsn($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXMsnParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-MSN") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXMsnParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXMsnParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-MSN vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXMsnParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXMsnParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-MSN", $resourceObjectResource, $vCardXMsnParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-MSN property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-YAHOO property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXYahoo($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXYahooParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-YAHOO") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXYahooParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXYahooParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-YAHOO vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXYahooParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXYahooParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-YAHOO", $resourceObjectResource, $vCardXYahooParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-YAHOO property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-JABBER property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXJabber($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXJabberParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-JABBER") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXJabberParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXJabberParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-JABBER vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXJabberParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXJabberParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-JABBER", $resourceObjectResource, $vCardXJabberParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-JABBER property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard X-SKYPE-USERNAME property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setXSkypeUsername($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectContexts = $resourceObject->contexts;
                $resourceObjectPref = $resourceObject->pref;
                $vCardXSkypeUsernameParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "X-SKYPE-USERNAME") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardXSkypeUsernameParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardXSkypeUsernameParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the X-SKYPE-USERNAME vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // If $resourceObjectContexts is null, then we set the vCard type to be 'other'
                            $vCardXSkypeUsernameParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardXSkypeUsernameParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("X-SKYPE-USERNAME", $resourceObjectResource, $vCardXSkypeUsernameParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard X-SKYPE-USERNAME property");
                }
            }
        }
    }

    /**
     * This function maps the vCard "X-GENDER" property to the JSContact "speakToAs" property
     *
     * @return SpeakToAs|null The "speakToAs" JSContact property as a SpeakToAs object
     */
    public function getSpeakToAs()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactSpeakToAsProperty = null;

        // X-GENDER property mapping
        // Note: The vCard X-GENDER property is Roundcube-specific
        if (in_array("X-GENDER", $this->vCardChildren)) {
            $vCardXGenderProperty = $this->vCard->__get("X-GENDER");

            if (isset($vCardXGenderProperty)) {
                $vCardXGenderPropertyValue = $vCardXGenderProperty->getValue();

                if (isset($vCardXGenderPropertyValue) && !empty($vCardXGenderPropertyValue)) {
                    $jsContactGrammaticalGenderValue = null;

                    // The Roundcube-specific X-GENDER can only take the values "male" and "female"
                    switch ($vCardXGenderPropertyValue) {
                        case 'male':
                            $jsContactGrammaticalGenderValue = 'male';
                            break;

                        case 'female':
                            $jsContactGrammaticalGenderValue = 'female';
                            break;

                        default:
                            $this->logger = Logger::getInstance();
                            $this->logger->error(
                                "Unknown vCard X-GENDER property value encountered: " . $vCardXGenderPropertyValue
                            );
                            $this->logger->warning("Setting JSContact grammaticalGender value to null");
                            $jsContactGrammaticalGenderValue = null;
                            break;
                    }

                    if (!is_null($jsContactGrammaticalGenderValue)) {
                        $jsContactSpeakToAsProperty = new SpeakToAs();
                        $jsContactSpeakToAsProperty->setAtType("SpeakToAs");
                        $jsContactSpeakToAsProperty->setGrammaticalGender($jsContactGrammaticalGenderValue);
                    }
                }
            }
        }

        return $jsContactSpeakToAsProperty;
    }

    /**
     * This function maps the JSContact "speakToAs" property to the vCard X-GENDER property
     *
     * @param SpeakToAs|null $jsContactSpeakToAs
     * The "speakToAs" JSContact property as a SpeakToAs object
     */
    public function setXGender($jsContactSpeakToAs)
    {
        if (!isset($jsContactSpeakToAs) || empty($jsContactSpeakToAs)) {
            return;
        }

        $jsContactSpeakToAsGrammaticalGender = $jsContactSpeakToAs->grammaticalGender;

        if (isset($jsContactSpeakToAsGrammaticalGender) && !empty($jsContactSpeakToAsGrammaticalGender)) {
            $vCardXGenderValue = null;

            switch ($jsContactSpeakToAsGrammaticalGender) {
                case 'male':
                    $vCardXGenderValue = 'male';
                    break;

                case 'female':
                    $vCardXGenderValue = 'female';
                    break;

                default:
                    throw new InvalidArgumentException(
                        "Unknown JSContact value for the property \"grammaticalGender\"
                        of the \"speakToAs\" property used during conversion to vCard X-GENDER property.
                        Encountered value is: " . $jsContactSpeakToAsGrammaticalGender
                    );
                    break;
            }

            if (!is_null($vCardXGenderValue)) {
                $this->vCard->add("X-GENDER", $vCardXGenderValue);
            }
        }
    }

    /**
     * This function maps the vCard "RELATED", "X-MANAGER", "X-ASSISTANT" and "X-SPOUSE"
     * properties to the JSContact "relatedTo" property
     *
     * @return array<string, Relation>|null The "relatedTo" JSContact property as a map of UIDs to Relation objects
     */
    public function getRelatedTo()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactRelatedToProperty = null;

        // RELATED property mapping
        if (in_array("RELATED", $this->vCardChildren)) {
            $vCardRelatedProperties = $this->vCard->RELATED;

            foreach ($vCardRelatedProperties as $vCardRelatedProperty) {
                if (isset($vCardRelatedProperty)) {
                    $vCardRelatedPropertyValue = $vCardRelatedProperty->getValue();

                    if (isset($vCardRelatedPropertyValue) && !empty($vCardRelatedPropertyValue)) {
                        $jsContactRelation = new Relation();
                        $jsContactRelation->setAtType("Relation");

                        if (isset($vCardRelatedProperty['TYPE']) && !empty($vCardRelatedProperty)) {
                            $jsContactRelationMap = [];

                            foreach ($vCardRelatedProperty['TYPE'] as $paramValue) {
                                $jsContactRelationMap[$paramValue] = true;
                            }

                            $jsContactRelation->setRelation(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactRelationMap)
                                ? $jsContactRelationMap
                                : null
                            );
                        } else {
                            // According to IETF draft:
                            // If no relation type given, "relation" property must contain an empty object
                            $jsContactRelation->setRelation(json_decode("{}"));
                        }

                        $jsContactRelatedToProperty[$vCardRelatedPropertyValue] = $jsContactRelation;
                    }
                }
            }
        }

        // X-MANAGER property mapping
        // Note: The vCard X-MANAGER property is Roundcube-specific
        if (in_array("X-MANAGER", $this->vCardChildren)) {
            $vCardXManagerProperties = $this->vCard->__get("X-MANAGER");

            foreach ($vCardXManagerProperties as $vCardXManagerProperty) {
                if (isset($vCardXManagerProperty)) {
                    $vCardXManagerPropertyValue = $vCardXManagerProperty->getValue();

                    if (isset($vCardXManagerPropertyValue) && !empty($vCardXManagerPropertyValue)) {
                        $jsContactRelation = new Relation();
                        $jsContactRelation->setAtType("Relation");

                        $jsContactRelation->setRelation(array("manager" => true));

                        $jsContactRelatedToProperty[$vCardXManagerPropertyValue] = $jsContactRelation;
                    }
                }
            }
        }

        // X-ASSISTANT property mapping
        // Note: The vCard X-ASSISTANT property is Roundcube-specific
        if (in_array("X-ASSISTANT", $this->vCardChildren)) {
            $vCardXAssistantProperties = $this->vCard->__get("X-ASSISTANT");

            foreach ($vCardXAssistantProperties as $vCardXAssistantProperty) {
                if (isset($vCardXAssistantProperty)) {
                    $vCardXAssistantPropertyValue = $vCardXAssistantProperty->getValue();

                    if (isset($vCardXAssistantPropertyValue) && !empty($vCardXAssistantPropertyValue)) {
                        $jsContactRelation = new Relation();
                        $jsContactRelation->setAtType("Relation");

                        $jsContactRelation->setRelation(array("assistant" => true));

                        $jsContactRelatedToProperty[$vCardXAssistantPropertyValue] = $jsContactRelation;
                    }
                }
            }
        }

        // X-SPOUSE property mapping
        // Note: The vCard X-SPOUSE property is Roundcube-specific
        if (in_array("X-SPOUSE", $this->vCardChildren)) {
            $vCardXSpouseProperties = $this->vCard->__get("X-SPOUSE");

            foreach ($vCardXSpouseProperties as $vCardXSpouseProperty) {
                if (isset($vCardXSpouseProperty)) {
                    $vCardXSpousePropertyValue = $vCardXSpouseProperty->getValue();

                    if (isset($vCardXSpousePropertyValue) && !empty($vCardXSpousePropertyValue)) {
                        $jsContactRelation = new Relation();
                        $jsContactRelation->setAtType("Relation");

                        $jsContactRelation->setRelation(array("spouse" => true));

                        $jsContactRelatedToProperty[$vCardXSpousePropertyValue] = $jsContactRelation;
                    }
                }
            }
        }

        return $jsContactRelatedToProperty;
    }

    /**
     * This function maps entries of the JSContact "relatedTo" property that correspond to
     * the vCard X-MANAGER property to it
     *
     * @param array<string, Relation>|null $jsContactRelatedTo
     * The "relatedTo" JSContact property as a map of strings to Relation objects
     */
    public function setXManager($jsContactRelatedTo)
    {
        if (!isset($jsContactRelatedTo) || empty($jsContactRelatedTo)) {
            return;
        }

        foreach ($jsContactRelatedTo as $relatedUid => $jsContactRelation) {
            if (isset($relatedUid) && !empty($relatedUid)) {
                if (isset($jsContactRelation) && !empty($jsContactRelation)) {
                    $jsContactRelationValues = $jsContactRelation->relation;
                    if (isset($jsContactRelationValues) && !empty($jsContactRelationValues)) {
                        foreach ($jsContactRelationValues as $jsContactRelationValue => $booleanValue) {
                            if (
                                isset($jsContactRelationValue)
                                && !empty($jsContactRelationValue)
                                && strcmp($jsContactRelationValue, "manager") === 0
                            ) {
                                $this->vCard->add("X-MANAGER", $relatedUid);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "relatedTo" property that correspond to
     * the vCard X-ASSISTANT property to it
     *
     * @param array<string, Relation>|null $jsContactRelatedTo
     * The "relatedTo" JSContact property as a map of strings to Relation objects
     */
    public function setXAssistant($jsContactRelatedTo)
    {
        if (!isset($jsContactRelatedTo) || empty($jsContactRelatedTo)) {
            return;
        }

        foreach ($jsContactRelatedTo as $relatedUid => $jsContactRelation) {
            if (isset($relatedUid) && !empty($relatedUid)) {
                if (isset($jsContactRelation) && !empty($jsContactRelation)) {
                    $jsContactRelationValues = $jsContactRelation->relation;
                    if (isset($jsContactRelationValues) && !empty($jsContactRelationValues)) {
                        foreach ($jsContactRelationValues as $jsContactRelationValue => $booleanValue) {
                            if (
                                isset($jsContactRelationValue)
                                && !empty($jsContactRelationValue)
                                && strcmp($jsContactRelationValue, "assistant") === 0
                            ) {
                                $this->vCard->add("X-ASSISTANT", $relatedUid);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "relatedTo" property that correspond to
     * the vCard X-SPOUSE property to it
     *
     * @param array<string, Relation>|null $jsContactRelatedTo
     * The "relatedTo" JSContact property as a map of strings to Relation objects
     */
    public function setXSpouse($jsContactRelatedTo)
    {
        if (!isset($jsContactRelatedTo) || empty($jsContactRelatedTo)) {
            return;
        }

        foreach ($jsContactRelatedTo as $relatedUid => $jsContactRelation) {
            if (isset($relatedUid) && !empty($relatedUid)) {
                if (isset($jsContactRelation) && !empty($jsContactRelation)) {
                    $jsContactRelationValues = $jsContactRelation->relation;
                    if (isset($jsContactRelationValues) && !empty($jsContactRelationValues)) {
                        foreach ($jsContactRelationValues as $jsContactRelationValue => $booleanValue) {
                            if (
                                isset($jsContactRelationValue)
                                && !empty($jsContactRelationValue)
                                && strcmp($jsContactRelationValue, "spouse") === 0
                            ) {
                                $this->vCard->add("X-SPOUSE", $relatedUid);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard X-MAIDENNAME (Roundcube-specific property)
     * to the JSContact "audriga.eu/roundcube:maidenName" property
     *
     * @return string|null The "audriga.eu/roundcube:maidenName" property as a string
     */
    public function getMaidenName()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactMaidenNameProperty = null;

        // X-MAIDENNAME property mapping
        // Note: The vCard X-MAIDENNAME property is Roundcube-specific
        if (in_array("X-MAIDENNAME", $this->vCardChildren)) {
            $vCardXMaidenNameProperty = $this->vCard->__get("X-MAIDENNAME");

            if (isset($vCardXMaidenNameProperty)) {
                $vCardXMaidenNamePropertyValue = $vCardXMaidenNameProperty->getValue();

                if (isset($vCardXMaidenNamePropertyValue) && !empty($vCardXMaidenNamePropertyValue)) {
                    $jsContactMaidenNameProperty = $vCardXMaidenNamePropertyValue;
                }
            }
        }

        return $jsContactMaidenNameProperty;
    }

    /**
     * This function maps the JSContact "audriga.eu/roundcube:maidenName" property to the vCard X-MAIDENNAME property
     *
     * @param string|null $jsContactMaidenName The "audriga.eu/roundcube:maidenName" JSContact property as a string
     */
    public function setXMaidenName($jsContactMaidenName)
    {
        if (!isset($jsContactMaidenName) || empty($jsContactMaidenName)) {
            return;
        }

        $this->vCard->add("X-MAIDENNAME", $jsContactMaidenName);
    }

    /**
     * This function maps the vCard "ORG" property to the JSContact "organizations" property
     *
     * @return array<string, Organization>|null
     * The "organizations" JSContact property as a map of IDs to Organization objects
     */
    public function getOrganizations()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactOrganizationsProperty = null;

        // ORG property mapping
        if (in_array("ORG", $this->vCardChildren)) {
            $vCardOrgProperties = $this->vCard->ORG;

            foreach ($vCardOrgProperties as $vCardOrgProperty) {
                if (isset($vCardOrgProperty)) {
                    $vCardOrgPropertyValue = $vCardOrgProperty->getValue();

                    if (isset($vCardOrgPropertyValue) && !empty($vCardOrgPropertyValue)) {
                        $jsContactOrganization = new Organization();
                        $jsContactOrganization->setAtType("Organization");

                        $jsContactOrganizationUnits = [];

                        if (strpos($vCardOrgPropertyValue, ';') !== false) {
                            $vCardOrgPropertyValue = explode(';', $vCardOrgPropertyValue);
                            $jsContactOrganization->setName($vCardOrgPropertyValue[0]);
                            array_merge($jsContactOrganizationUnits, array_splice($vCardOrgPropertyValue, 0, 1));
                        } else {
                            $jsContactOrganization->setName($vCardOrgPropertyValue);
                        }

                        // If the "X-DEPARTMENT" vCard Roundcube-specific property exists, then map its value
                        // to the "units" property of an entry of the "organizations" JSContact property
                        // Moreover, here we assume we can have more than one X-DEPARTMENT properties per single vCard
                        if (in_array("X-DEPARTMENT", $this->vCardChildren)) {
                            $vCardXDepartmentProperties = $this->vCard->__get("X-DEPARTMENT");
                            foreach ($vCardXDepartmentProperties as $vCardXDepartmentProperty) {
                                if (isset($vCardXDepartmentProperty) && !empty($vCardXDepartmentProperty)) {
                                    $vCardXDepartmentPropertyValue = $vCardXDepartmentProperty->getValue();
                                    if (
                                        isset($vCardXDepartmentPropertyValue)
                                        && !empty($vCardXDepartmentPropertyValue)
                                    ) {
                                        $jsContactOrganizationUnits[] = $vCardXDepartmentPropertyValue;
                                    }
                                }
                            }
                        }

                        if (isset($jsContactOrganizationUnits) && !empty($jsContactOrganizationUnits)) {
                            $jsContactOrganization->setUnits($jsContactOrganizationUnits);
                        }

                        $jsContactOrganizationsProperty[md5($vCardOrgPropertyValue[0])] = $jsContactOrganization;
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardOrgProperty['ALTID'])
                        && !empty($vCardOrgProperty['ALTID'])
                    ) {
                        $this->logger = Logger::getInstance();
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property ORG"
                        );
                    }

                    if (
                        isset($vCardOrgProperty['LANGUAGE'])
                        && !empty($vCardOrgProperty['LANGUAGE'])
                    ) {
                        $this->logger = Logger::getInstance();
                        $this->logger->error(
                            "Currently unsupported vCard Parameter LANGUAGE encountered
                            for vCard property ORG"
                        );
                    }
                }
            }
        }

        return $jsContactOrganizationsProperty;
    }

    /**
     * This function maps the JSContact "organizations" property to the vCard ORG property
     *
     * @param array<string, Organization>|null $jsContactOrganizations
     * The "organizations" JSContact property as a map of strings to Organization objects
     */
    public function setOrg($jsContactOrganizations)
    {
        if (!isset($jsContactOrganizations) || empty($jsContactOrganizations)) {
            return;
        }

        foreach ($jsContactOrganizations as $id => $jsContactOrganization) {
            if (isset($jsContactOrganization) && !empty($jsContactOrganization)) {
                $jsContactOrganizationName = $jsContactOrganization->name;
                $jsContactOrganizationUnits = $jsContactOrganization->units;

                if (isset($jsContactOrganizationName) && !empty($jsContactOrganizationName)) {
                    $this->vCard->add("ORG", $jsContactOrganizationName);

                    // If the "units" property of the "organizations" entry has values in it, then
                    // we write each value in it to a separate X-DEPARTMENT property in a given vCard
                    if (isset($jsContactOrganizationUnits) && !empty($jsContactOrganizationUnits)) {
                        $this->vCard->add("X-DEPARTMENT", $jsContactOrganizationUnits);
                    }
                }
            }
        }
    }
}
