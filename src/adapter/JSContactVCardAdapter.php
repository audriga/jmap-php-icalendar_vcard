<?php

namespace OpenXPort\Adapter;

use InvalidArgumentException;
use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\ContactLanguage;
use OpenXPort\Jmap\JSContact\EmailAddress;
use OpenXPort\Jmap\JSContact\File;
use OpenXPort\Jmap\JSContact\Name;
use OpenXPort\Jmap\JSContact\NameComponent;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\PersonalInformation;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\Resource;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Jmap\JSContact\StreetComponent;
use OpenXPort\Jmap\JSContact\Title;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\Logger;
use Sabre\VObject;

class JSContactVCardAdapter extends AbstractAdapter
{
    protected $logger;

    /** @var VObject\Component\VCard */
    protected $vCard;

    protected $vCardChildren = [];

    /**
     * Constructor of this class
     *
     * Initializes the $vCard property of this class to a new VCard() object
     */
    public function __construct()
    {
        $this->vCard = new VObject\Component\VCard();
        $this->logger = Logger::getInstance();
    }

    /**
     * Getter for this class' $vCard property
     *
     * Obtain the vCard object represented in this adapter
     *
     * @return string The vCard of the adapter, serialized as string
     */
    public function getVCard()
    {
        return $this->vCard->serialize();
    }

    /**
     * Setter for this class' $vCard property
     *
     * Set the vCard object represented in this adapter
     *
     * @param string $vCardString The vCard string used to initialize the vCard object of this adapter
     */
    public function setVCard($vCardString)
    {
        $this->vCard = VObject\Reader::read($vCardString);

        foreach ($this->vCard->children() as $vCardChild) {
            $this->vCardChildren[] = $vCardChild->name;
        }
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
                                        $jsContactImppContexts = null;
                                        break;

                                    default:
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

                        // If the INDEX parameter is set for ORG-DIRECTORY, then use it as the key for
                        // the "online" entry representing the corresponding org-directory
                        // If it's not set, then use a MD5 hash of the org-directory's value
                        if (isset($vCardOrgDirectoryProperty['INDEX']) && !empty($vCardOrgDirectoryProperty['INDEX'])) {
                            $jsContactOnlineProperty["ORG-DIRECTORY-" . $vCardOrgDirectoryProperty['INDEX']]
                            = $jsContactOrgDirectoryEntry;
                        } else {
                            $jsContactOnlineProperty[md5($vCardOrgDirectoryPropertyValue)]
                            = $jsContactOrgDirectoryEntry;
                        }
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

        return $jsContactOnlineProperty;
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard SOURCE property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setSource($jsContactOnlineMap)
    {
        if (!isset($jsContactOnlineMap) || empty($jsContactOnlineMap)) {
            return;
        }

        foreach ($jsContactOnlineMap as $id => $resourceObject) {
            if (isset($resourceObject) && !empty($resourceObject)) {
                $resourceObjectLabel = $resourceObject->label;
                $resourceObjectResource = $resourceObject->resource;
                $resourceObjectMediaType = $resourceObject->mediaType;
                $resourceObjectPref = $resourceObject->pref;
                $vCardSourceParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "source") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectMediaType) && !empty($resourceObjectMediaType)) {
                            $vCardSourceParams['mediatype'] = $resourceObjectMediaType;
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardSourceParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("SOURCE", $resourceObjectResource, $vCardSourceParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard SOURCE property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard IMPP property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setImpp($jsContactOnlineMap)
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
                $vCardImppParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "XMPP") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardImppParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardImppParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the IMPP vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardImppParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardImppParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("IMPP", $resourceObjectResource, $vCardImppParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard IMPP property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard LOGO property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setLogo($jsContactOnlineMap)
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
                $vCardLogoParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        isset($resourceObjectLabel) && strcmp($resourceObjectLabel, "logo") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardLogoParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardLogoParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the LOGO vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardLogoParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardLogoParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("LOGO", $resourceObjectResource, $vCardLogoParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard LOGO property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard CONTACT-URI property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setContactUri($jsContactOnlineMap)
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
                $vCardContactUriParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "contact-uri") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardContactUriParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardContactUriParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the CONTACT-URI vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardContactUriParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardContactUriParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("CONTACT-URI", $resourceObjectResource, $vCardContactUriParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard CONTACT-URI property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard ORG-DIRECTORY property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setOrgDirectory($jsContactOnlineMap)
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
                $vCardOrgDirectoryParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "org-directory") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardOrgDirectoryParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardOrgDirectoryParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the ORG-DIRECTORY vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardOrgDirectoryParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardOrgDirectoryParams['pref'] = $resourceObjectPref;
                        }

                        if (isset($id) && !empty($id) && strcmp("ORG-DIRECTORY", substr($id, 0, 13)) === 0) {
                            $vCardOrgDirectoryParams['index'] = substr($id, 12, 1);
                        }

                        $this->vCard->add("ORG-DIRECTORY", $resourceObjectResource, $vCardOrgDirectoryParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard ORG-DIRECTORY property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard SOUND property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setSound($jsContactOnlineMap)
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
                $vCardSoundParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "sound") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardSoundParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardSoundParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the SOUND vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardSoundParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardSoundParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("SOUND", $resourceObjectResource, $vCardSoundParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard SOUND property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard URL property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setUrl($jsContactOnlineMap)
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
                $vCardUrlParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "url") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardUrlParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardUrlParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the URL vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardUrlParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardUrlParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("URL", $resourceObjectResource, $vCardUrlParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard URL property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard KEY property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setKey($jsContactOnlineMap)
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
                $vCardKeyParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "key") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardKeyParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardKeyParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the KEY vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardKeyParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardKeyParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("KEY", $resourceObjectResource, $vCardKeyParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard KEY property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard FBURL property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setFbUrl($jsContactOnlineMap)
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
                $vCardFbUrlParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "fburl") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardFbUrlParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardFbUrlParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the FBURL vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardFbUrlParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardFbUrlParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("FBURL", $resourceObjectResource, $vCardFbUrlParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard FBURL property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard CALADRURI property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setCalAdrUri($jsContactOnlineMap)
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
                $vCardCalAdrUriParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "caladruri") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardCalAdrUriParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardCalAdrUriParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the CALADRURI vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardCalAdrUriParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardCalAdrUriParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("CALADRURI", $resourceObjectResource, $vCardCalAdrUriParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard CALADRURI property");
                }
            }
        }
    }

    /**
     * This function maps all JSContact "online" entries that correspond to the vCard CALURI property to it
     *
     * @param array<string, Resource>|null $jsContactOnlineMap
     * The "online" JSContact property as a map of IDs to Resource objects
     */
    public function setCalUri($jsContactOnlineMap)
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
                $vCardCalUriParams = [];

                if (isset($resourceObjectLabel) && !empty($resourceObjectLabel)) {
                    if (
                        strcmp($resourceObjectLabel, "caluri") === 0
                        && isset($resourceObjectResource) && !empty($resourceObjectResource)
                    ) {
                        if (isset($resourceObjectContexts) && !empty($resourceObjectContexts)) {
                            foreach ($resourceObjectContexts as $resourceObjectContext => $booleanValue) {
                                switch ($resourceObjectContext) {
                                    case 'private':
                                        $vCardCalUriParams['type'] = 'home';
                                        break;

                                    case 'work':
                                        $vCardCalUriParams['type'] = 'work';
                                        break;

                                    default:
                                        $this->logger->error("Unknown value for the \"contexts\" property of a
                                        Resource object in the JSContact \"online\" property encountered during
                                        conversion to the CALURI vCard property.
                                        Encountered value is: " . $resourceObjectContext);
                                        break;
                                }
                            }
                        } else { // In case that $resourceObjectContexts is null, we set the vCard type to be 'other'
                            $vCardCalUriParams['type'] = 'other';
                        }

                        if (isset($resourceObjectPref)) {
                            $vCardCalUriParams['pref'] = $resourceObjectPref;
                        }

                        $this->vCard->add("CALURI", $resourceObjectResource, $vCardCalUriParams);
                    }
                } else {
                    throw new InvalidArgumentException("\"label\" property of \"online\" property entry
                    not set during conversion to vCard CALURI property");
                }
            }
        }
    }

    /**
     * This function maps the vCard KIND property to the JSContact "kind" property
     *
     * @return string|null The "kind" JSContact property as a string
     */
    public function getKind()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactKindProperty = null;

        // KIND property mapping
        if (in_array("KIND", $this->vCardChildren)) {
            $vCardKindProperty = $this->vCard->KIND;

            if (isset($vCardKindProperty)) {
                $vCardKindPropertyValue = $vCardKindProperty->getValue();

                // Only if the vCard KIND property indeed has a value, we map it to "kind" in JSContact.
                // Moreover, skip the KIND value if it's equal to "group", since "group" is only relevant for CardGroup
                if (isset($vCardKindPropertyValue) && !empty($vCardKindPropertyValue)) {
                    if (isset($this->vCard->MEMBER) || strcmp($vCardKindPropertyValue, "group") === 0) {
                        $this->logger->error(
                            "vCard MEMBER property is set and/or KIND has value of \"group\" which is not allowed for
                            vCards not representing a group"
                        );
                    } else {
                        $jsContactKindProperty = $vCardKindPropertyValue;
                    }
                }
            }
        }

        return $jsContactKindProperty;
    }

    /**
     * This function maps the JSContact "kind" property to the vCard KIND property
     *
     * @param string|null $jsContactKind
     * The "kind" JSContact property as a string
     */
    public function setKind($jsContactKind)
    {
        if (!isset($jsContactKind) || empty($jsContactKind)) {
            return;
        }

        $this->vCard->add("KIND", $jsContactKind);
    }

    /**
     * This function maps the vCard FN property to the JSContact "fullName" property
     *
     * @return string|null The "fullName" JSContact property as a string
     */
    public function getFullName()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactFullNameProperty = null;

        // FN property mapping
        if (in_array("FN", $this->vCardChildren)) {
            $vCardFNProperty = $this->vCard->FN;

            if (isset($vCardFNProperty)) {
                $vCardFNPropertyValue = $vCardFNProperty->getValue();

                // Only if the vCard FN property indeed has a value, we map it to "fullName" in JSContact
                if (isset($vCardFNPropertyValue) && !empty($vCardFNPropertyValue)) {
                    $jsContactFullNameProperty = $vCardFNPropertyValue;
                }

                // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                // If yes, then provide an error log with some information that they're not supported
                if (isset($vCardFNProperty['ALTID']) && !empty($vCardFNProperty['ALTID'])) {
                    $this->logger->error(
                        "Currently unsupported vCard Parameter ALTID encountered for vCard property FN"
                    );
                }

                if (isset($vCardFNProperty['LANGUAGE']) && !empty($vCardFNProperty['LANGUAGE'])) {
                    $this->logger->error(
                        "Currently unsupported vCard Parameter LANGUAGE encountered for vCard property FN"
                    );
                }
            }
        }

        return $jsContactFullNameProperty;
    }

    /**
     * This function maps the JSContact "fullName" property to the vCard FN property
     *
     * @param string|null $jsContactFullName
     * The "fullName" JSContact property as a string
     */
    public function setFN($jsContactFullName)
    {
        if (!isset($jsContactFullName) || empty($jsContactFullName)) {
            return;
        }

        $this->vCard->add("FN", $jsContactFullName);
    }

    /**
     * This function maps the vCard "N" property to the JSContact "name" property
     *
     * @return Name|null The "name" JSContact property as a Name object comprising NameComponents
     */
    public function getName()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactNameProperty = null;

        // N property mapping
        if (in_array("N", $this->vCardChildren)) {
            $vCardNProperty = $this->vCard->N;

            if (isset($vCardNProperty)) {
                $vCardNPropertyValues = $vCardNProperty->getParts();

                // Only if the vCard N property indeed has any values, create
                // corresponding entries in the JSContact "name" property
                if (isset($vCardNPropertyValues) && !empty($vCardNPropertyValues)) {
                    $nameComponents = array();

                    $prefixNameComponent = null;
                    $givenNameComponent = null;
                    $surnameNameComponent = null;
                    $additionalNameComponent = null;
                    $suffixNameComponent = null;

                    // Note: it is important to order the name components in a way that they can
                    // logically form an entire name if concatenated. That's why they're appended
                    // to the $nameComponents array below in this order.

                    // Create a NameComponent for prefix if vCard prefix exists
                    if (isset($vCardNPropertyValues[3]) && !empty($vCardNPropertyValues[3])) {
                        $prefixNameComponent = new NameComponent();
                        $prefixNameComponent->setAtType("NameComponent");
                        $prefixNameComponent->setValue($vCardNPropertyValues[3]);
                        $prefixNameComponent->setType("prefix");

                        $nameComponents[] = $prefixNameComponent;
                    }

                    // Create a NameComponent for given name if vCard given name exists
                    if (isset($vCardNPropertyValues[1]) && !empty($vCardNPropertyValues[1])) {
                        $givenNameComponent = new NameComponent();
                        $givenNameComponent->setAtType("NameComponent");
                        $givenNameComponent->setValue($vCardNPropertyValues[1]);
                        $givenNameComponent->setType("given");

                        $nameComponents[] = $givenNameComponent;
                    }

                    // Create a NameComponent for surname if vCard surname exists
                    if (isset($vCardNPropertyValues[0]) && !empty($vCardNPropertyValues[0])) {
                        $surnameNameComponent = new NameComponent();
                        $surnameNameComponent->setAtType("NameComponent");
                        $surnameNameComponent->setValue($vCardNPropertyValues[0]);
                        $surnameNameComponent->setType("surname");

                        $nameComponents[] = $surnameNameComponent;
                    }

                    // Create a NameComponent for additional name if vCard additional name exists
                    if (isset($vCardNPropertyValues[2]) && !empty($vCardNPropertyValues[2])) {
                        $additionalNameComponent = new NameComponent();
                        $additionalNameComponent->setAtType("NameComponent");
                        $additionalNameComponent->setValue($vCardNPropertyValues[2]);
                        $additionalNameComponent->setType("additional");

                        $nameComponents[] = $additionalNameComponent;
                    }

                    // Create a NameComponent for suffix if vCard suffix exists
                    if (isset($vCardNPropertyValues[4]) && !empty($vCardNPropertyValues[4])) {
                        $suffixNameComponent = new NameComponent();
                        $suffixNameComponent->setAtType("NameComponent");
                        $suffixNameComponent->setValue($vCardNPropertyValues[4]);
                        $suffixNameComponent->setType("suffix");

                        $nameComponents[] = $suffixNameComponent;
                    }


                    $jsContactNameProperty = new Name();
                    $jsContactNameProperty->setAtType("Name");
                    $jsContactNameProperty->setComponents($nameComponents);
                }
            }
        }

        return $jsContactNameProperty;
    }

    /**
     * This function maps the JSContact "name" property to the vCard N property
     *
     * @param Name|null $jsContactName
     * The "name" JSContact property as a Name object
     */
    public function setN($jsContactName)
    {
        if (!isset($jsContactName) || empty($jsContactName)) {
            return;
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

                                case 'additional':
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

        $vCardNProperty = $this->vCard->createProperty(
            "N",
            array($vCardFamilyName, $vCardGivenName, $vCardAdditionalName, $vCardPrefix, $vCardSuffix)
        );
        $this->vCard->add($vCardNProperty);
    }

    /**
     * This function maps the vCard "NICKNAME" property to the JSContact "nickNames" property
     *
     * @return array<string>|null The "nickNames" JSContact property as an array of strings (containing nicknames)
     */
    public function getNickNames()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactNickNamesProperty = null;

        // NICKNAME property mapping
        if (in_array("NICKNAME", $this->vCardChildren)) {
            $vCardNicknameProperties = $this->vCard->NICKNAME;

            foreach ($vCardNicknameProperties as $vCardNicknameProperty) {
                if (isset($vCardNicknameProperty)) {
                    $vCardNicknamePropertyValue = $vCardNicknameProperty->getValue();

                    // Only if the vCard NICKNAME property indeed has a value, we add it as an element of
                    // "nickNames" in JSContact
                    if (isset($vCardNicknamePropertyValue) && !empty($vCardNicknamePropertyValue)) {
                        $jsContactNickNamesProperty[] = $vCardNicknamePropertyValue;
                    }
                }
            }
        }

        return $jsContactNickNamesProperty;
    }

    /**
     * This function maps the JSContact "nickNames" property to the vCard NICKNAME property
     *
     * @param array<string>|null $jsContactNickNames
     * The "nickNames" JSContact property as an array of strings
     */
    public function setNickname($jsContactNickNames)
    {
        if (!isset($jsContactNickNames) || empty($jsContactNickNames)) {
            return;
        }

        foreach ($jsContactNickNames as $jsContactNickName) {
            if (isset($jsContactNickName) && !empty($jsContactNickName)) {
                $this->vCard->add("NICKNAME", $jsContactNickName);
            }
        }
    }

    /**
     * This function maps the vCard "PHOTO" property to the JSContact "photos" property
     *
     * @return array<string, File>|null The "photos" JSContact property as a map of IDs to File objects
     */
    public function getPhotos()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactPhotosProperty = null;

        // PHOTO property mapping
        if (in_array("PHOTO", $this->vCardChildren)) {
            $vCardPhotoProperties = $this->vCard->PHOTO;

            foreach ($vCardPhotoProperties as $vCardPhotoProperty) {
                if (isset($vCardPhotoProperty)) {
                    $vCardPhotoPropertyValue = $vCardPhotoProperty->getRawMimeDirValue();

                    // Only if the vCard PHOTO property indeed has a value, we add it as "href" into a File object
                    // which in turn is an element of "photos" in JSContact
                    if (isset($vCardPhotoPropertyValue) && !empty($vCardPhotoPropertyValue)) {
                        $jsContactPhoto = new File();
                        $jsContactPhoto->setAtType("File");
                        $jsContactPhoto->setHref($vCardPhotoPropertyValue);

                        if (isset($vCardPhotoProperty['PREF']) && !empty($vCardPhotoProperty['PREF'])) {
                            $jsContactPhoto->setPref($vCardPhotoProperty['PREF']);
                        }

                        if (isset($vCardPhotoProperty['MEDIATYPE']) && !empty($vCardPhotoProperty['MEDIATYPE'])) {
                            $jsContactPhoto->setMediaType($vCardPhotoProperty['MEDIATYPE']);
                        }

                        // Since "photos" is a map and key creation for the map keys is not specified, we use
                        // the MD5 hash of the PHOTO property's value to create the key of the entry in "photos"
                        $jsContactPhotosProperty[md5($vCardPhotoPropertyValue)] = $jsContactPhoto;
                    }
                }
            }
        }

        return $jsContactPhotosProperty;
    }

    /**
     * This function maps the JSContact "photos" property to the vCard PHOTO property
     *
     * @param array<string, File>|null $jsContactPhotos
     * The "photos" JSContact property as a map of strings to File objects
     */
    public function setPhoto($jsContactPhotos)
    {
        if (!isset($jsContactPhotos) || empty($jsContactPhotos)) {
            return;
        }

        foreach ($jsContactPhotos as $id => $jsContactPhoto) {
            if (isset($jsContactPhoto) && !empty($jsContactPhoto)) {
                $jsContactPhotoValue = $jsContactPhoto->href;
                $jsContactPhotoPref = $jsContactPhoto->pref;
                $jsContactPhotoMediaType = $jsContactPhoto->mediaType;

                $vCardPhotoParams = [];

                if (isset($jsContactPhotoPref)) {
                    $vCardPhotoParams['pref'] = $jsContactPhotoPref;
                }

                if (isset($jsContactPhotoMediaType) && !empty($jsContactPhotoMediaType)) {
                    $vCardPhotoParams['mediatype'] = $jsContactPhotoMediaType;
                }

                if (isset($jsContactPhotoValue) && !empty($jsContactPhotoValue)) {
                    $this->vCard->add("PHOTO", $jsContactPhotoValue, $vCardPhotoParams);
                }
            }
        }
    }

    /**
     * This function maps the vCard "BDAY", "BIRTHPLACE", "DEATHDATE", "DEATHPLACE" and "ANNIVERSARY" properties
     * to the JSContact "anniversaries" property
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
                        'Ymd\THis\Z',
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
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter ALTID encountered
                                    for vCard property BIRTHPLACE"
                                );
                            }

                            if (
                                isset($vCardBirthdayPlaceProperty['LANGUAGE'])
                                && !empty($vCardBirthdayPlaceProperty['LANGUAGE'])
                            ) {
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
                        'Ymd\THis\Z',
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
                                $this->logger->error(
                                    "Currently unsupported vCard Parameter ALTID encountered
                                    for vCard property DEATHPLACE"
                                );
                            }

                            if (
                                isset($vCardDeathdatePlaceProperty['LANGUAGE'])
                                && !empty($vCardDeathdatePlaceProperty['LANGUAGE'])
                            ) {
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
                        'Ymd\THis\Z',
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
                    // any further specifics about what to include in the value.
                    // Note: "type" of the JSContact Anniversary object is not set here.
                    $jsContactAnniversary->setLabel("anniversary");
                    $jsContactAnniversary->setDate($jsContactAnniversaryPropertyValue);

                    // Since "anniversaries" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of the ANNIVERSARY property value to create the key of the entry in "anniversaries"
                    $jsContactAnniversariesProperty[md5($vCardAnniversaryPropertyValue)] = $jsContactAnniversary;
                }
            }
        }

        return $jsContactAnniversariesProperty;
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard BDAY property to it
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setBDay($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryType = $jsContactAnniversary->type;
                $jsContactAnniversaryValue = $jsContactAnniversary->date;

                if (
                    isset($jsContactAnniversaryType)
                    && !empty($jsContactAnniversaryType)
                    && strcmp($jsContactAnniversaryType, "birth") === 0
                ) {
                    if (isset($jsContactAnniversaryValue) && !empty($jsContactAnniversaryValue)) {
                        $vCardBDayValue = AdapterUtil::parseDateTime(
                            $jsContactAnniversaryValue,
                            'Y-m-d\TH:i:s\Z',
                            'Ymd\THis\Z',
                            'Y-m-d'
                        );

                        if (is_null($vCardBDayValue)) {
                            throw new InvalidArgumentException("Couldn't parse JSContact birth date to vCard BDAY.
                            JSContact date encountered is: " . $jsContactAnniversaryValue);
                            return;
                        }

                        $this->vCard->add("BDAY", $vCardBDayValue);
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard BIRTHPLACE property to it
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setBirthPlace($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryType = $jsContactAnniversary->type;
                $jsContactAnniversaryPlace = $jsContactAnniversary->place;

                if (
                    isset($jsContactAnniversaryType)
                    && !empty($jsContactAnniversaryType)
                    && strcmp($jsContactAnniversaryType, "birth") === 0
                ) {
                    if (isset($jsContactAnniversaryPlace) && !empty($jsContactAnniversaryPlace)) {
                        $jsContactAnniversaryPlaceCoordinates = $jsContactAnniversaryPlace->coordinates;
                        $jsContactAnniversaryPlaceFullAddress = $jsContactAnniversaryPlace->fullAddress;

                        if (
                            isset($jsContactAnniversaryPlaceCoordinates)
                            && !empty($jsContactAnniversaryPlaceCoordinates)
                        ) {
                            $this->vCard->add("BIRTHPLACE", $jsContactAnniversaryPlaceCoordinates);
                        } elseif (
                            isset($jsContactAnniversaryPlaceFullAddress)
                            && !empty($jsContactAnniversaryPlaceFullAddress)
                        ) {
                            $this->vCard->add("BIRTHPLACE", $jsContactAnniversaryPlaceFullAddress);
                        } else {
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard DEATHDATE property to it
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setDeathDate($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryType = $jsContactAnniversary->type;
                $jsContactAnniversaryValue = $jsContactAnniversary->date;

                if (
                    isset($jsContactAnniversaryType)
                    && !empty($jsContactAnniversaryType)
                    && strcmp($jsContactAnniversaryType, "death") === 0
                ) {
                    if (isset($jsContactAnniversaryValue) && !empty($jsContactAnniversaryValue)) {
                        $vCardDeathDateValue = AdapterUtil::parseDateTime(
                            $jsContactAnniversaryValue,
                            'Y-m-d\TH:i:s\Z',
                            'Ymd\THis\Z',
                            'Y-m-d'
                        );

                        if (is_null($vCardDeathDateValue)) {
                            throw new InvalidArgumentException("Couldn't parse JSContact death date to vCard DEATHDATE.
                            JSContact date encountered is: " . $jsContactAnniversaryValue);
                            return;
                        }

                        $this->vCard->add("DEATHDATE", $vCardDeathDateValue);
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard DEATHPLACE property to it
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setDeathPlace($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryType = $jsContactAnniversary->type;
                $jsContactAnniversaryPlace = $jsContactAnniversary->place;

                if (
                    isset($jsContactAnniversaryType)
                    && !empty($jsContactAnniversaryType)
                    && strcmp($jsContactAnniversaryType, "death") === 0
                ) {
                    if (isset($jsContactAnniversaryPlace) && !empty($jsContactAnniversaryPlace)) {
                        $jsContactAnniversaryPlaceCoordinates = $jsContactAnniversaryPlace->coordinates;
                        $jsContactAnniversaryPlaceFullAddress = $jsContactAnniversaryPlace->fullAddress;

                        if (
                            isset($jsContactAnniversaryPlaceCoordinates)
                            && !empty($jsContactAnniversaryPlaceCoordinates)
                        ) {
                            $this->vCard->add("DEATHPLACE", $jsContactAnniversaryPlaceCoordinates);
                        } elseif (
                            isset($jsContactAnniversaryPlaceFullAddress)
                            && !empty($jsContactAnniversaryPlaceFullAddress)
                        ) {
                            $this->vCard->add("DEATHPLACE", $jsContactAnniversaryPlaceFullAddress);
                        } else {
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard ANNIVERSARY property to it
     *
     * @param array<string, Anniversary>|null $jsContactAnniversaries The "anniversaries" JSContact property as a map
     * of IDs to Anniversary objects
     */
    public function setAnniversary($jsContactAnniversaries)
    {
        if (!isset($jsContactAnniversaries) || empty($jsContactAnniversaries)) {
            return;
        }

        foreach ($jsContactAnniversaries as $id => $jsContactAnniversary) {
            if (isset($jsContactAnniversary) && !empty($jsContactAnniversary)) {
                $jsContactAnniversaryLabel = $jsContactAnniversary->label;
                $jsContactAnniversaryValue = $jsContactAnniversary->date;

                if (
                    isset($jsContactAnniversaryLabel)
                    && !empty($jsContactAnniversaryLabel)
                    && strcmp($jsContactAnniversaryLabel, "anniversary") === 0
                ) {
                    if (isset($jsContactAnniversaryValue) && !empty($jsContactAnniversaryValue)) {
                        $vCardAnniversaryValue = AdapterUtil::parseDateTime(
                            $jsContactAnniversaryValue,
                            'Y-m-d\TH:i:s\Z',
                            'Ymd\THis\Z',
                            'Y-m-d'
                        );

                        if (is_null($vCardAnniversaryValue)) {
                            throw new InvalidArgumentException(
                                "Couldn't parse JSContact anniversary date to vCard ANNIVERSARY.
                                JSContact date encountered is: " . $jsContactAnniversaryValue
                            );
                            return;
                        }

                        $this->vCard->add("ANNIVERSARY", $vCardAnniversaryValue);
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard "GENDER" property to the JSContact "speakToAs" property
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

        // GENDER property mapping
        if (in_array("GENDER", $this->vCardChildren)) {
            $vCardGenderProperty = $this->vCard->GENDER;

            if (isset($vCardGenderProperty)) {
                $vCardGenderPropertyValue = $vCardGenderProperty->getValue();

                if (isset($vCardGenderPropertyValue) && !empty($vCardGenderPropertyValue)) {
                    $jsContactGrammaticalGenderValue = null;

                    switch ($vCardGenderPropertyValue) {
                        case 'M':
                            $jsContactGrammaticalGenderValue = 'male';
                            break;

                        case 'F':
                            $jsContactGrammaticalGenderValue = 'female';
                            break;

                        case 'N':
                            $jsContactGrammaticalGenderValue = 'neuter';
                            break;

                        case 'O':
                            $jsContactGrammaticalGenderValue = 'animate';
                            break;

                        case 'U':
                            $jsContactGrammaticalGenderValue = null;
                            break;

                        default:
                            $this->logger->warning(
                                "Unknown vCard GENDER property value encountered: " . $vCardGenderPropertyValue
                            );
                            $this->logger->warning("Setting JSContact grammaticalGender value to null.");
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
     * This function maps the JSContact "speakToAs" property to the vCard GENDER property
     *
     * @param SpeakToAs|null $jsContactSpeakToAs
     * The "speakToAs" JSContact property as a SpeakToAs object
     */
    public function setGender($jsContactSpeakToAs)
    {
        if (!isset($jsContactSpeakToAs) || empty($jsContactSpeakToAs)) {
            return;
        }

        $jsContactSpeakToAsGrammaticalGender = $jsContactSpeakToAs->grammaticalGender;

        if (isset($jsContactSpeakToAsGrammaticalGender) && !empty($jsContactSpeakToAsGrammaticalGender)) {
            $vCardGenderValue = null;

            switch ($jsContactSpeakToAsGrammaticalGender) {
                case 'male':
                    $vCardGenderValue = 'M';
                    break;

                case 'female':
                    $vCardGenderValue = 'F';
                    break;

                case 'neuter':
                    $vCardGenderValue = 'N';
                    break;

                case 'animate':
                    $vCardGenderValue = 'O';
                    break;

                case 'inanimate':
                    $vCardGenderValue = 'N;inanimate';
                    break;

                default:
                    throw new InvalidArgumentException("Unknown JSContact value for the property \"grammaticalGender\"
                    of the \"speakToAs\" property used during conversion to vCard GENDER property.
                    Encountered value is: " . $jsContactSpeakToAsGrammaticalGender);
                    break;
            }

            if (!is_null($vCardGenderValue)) {
                $this->vCard->add("GENDER", $vCardGenderValue);
            }
        }
    }

    /**
     * This function maps the vCard "ADR" property to the JSContact "addresses" property
     *
     * @return array<string, Address>|null The "addresses" JSContact property as a map of IDs to Address objects
     */
    public function getAddresses()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactAddressesProperty = null;

        // ADR property mapping
        if (in_array("ADR", $this->vCardChildren)) {
            $vCardAddressProperties = $this->vCard->ADR;

            foreach ($vCardAddressProperties as $vCardAddressProperty) {
                if (isset($vCardAddressProperty)) {
                    $vCardAddressPropertyValue = $vCardAddressProperty->getParts();

                    // Obtain the vCard ADR values
                    $vCardPostOfficeBox = $vCardAddressPropertyValue[0];
                    $vCardExtendedAddress = $vCardAddressPropertyValue[1];
                    $vCardStreetAddress = $vCardAddressPropertyValue[2];
                    $vCardLocality = $vCardAddressPropertyValue[3];
                    $vCardRegion = $vCardAddressPropertyValue[4];
                    $vCardPostalCode = $vCardAddressPropertyValue[5];
                    $vCardCountryName = $vCardAddressPropertyValue[6];

                    // Create the JSContact Address object and populate it with data below
                    $jsContactAddress = new Address();
                    $jsContactAddress->setAtType("Address");

                    if (isset($vCardLocality) && !empty($vCardLocality)) {
                        $jsContactAddress->setLocality($vCardLocality);
                    }

                    if (isset($vCardRegion) && !empty($vCardRegion)) {
                        $jsContactAddress->setRegion($vCardRegion);
                    }

                    if (isset($vCardPostalCode) && !empty($vCardPostalCode)) {
                        $jsContactAddress->setPostcode($vCardPostalCode);
                    }

                    if (isset($vCardCountryName) && !empty($vCardCountryName)) {
                        $jsContactAddress->setCountry($vCardCountryName);
                    }

                    $jsContactStreet = [];

                    if (isset($vCardPostOfficeBox) && !empty($vCardPostOfficeBox)) {
                        $jsContactPostOfficeBoxComponent = new StreetComponent();
                        $jsContactPostOfficeBoxComponent->setAtType("StreetComponent");
                        $jsContactPostOfficeBoxComponent->setType("postOfficeBox");
                        $jsContactPostOfficeBoxComponent->setValue($vCardPostOfficeBox);
                        $jsContactStreet[] = $jsContactPostOfficeBoxComponent;
                    }

                    if (isset($vCardExtendedAddress) && !empty($vCardExtendedAddress)) {
                        $jsContactExtendedAddressComponent = new StreetComponent();
                        $jsContactExtendedAddressComponent->setAtType("StreetComponent");
                        $jsContactExtendedAddressComponent->setType("extension");
                        $jsContactExtendedAddressComponent->setValue($vCardExtendedAddress);
                        $jsContactStreet[] = $jsContactExtendedAddressComponent;
                    }

                    if (isset($vCardStreetAddress) && !empty($vCardStreetAddress)) {
                        $jsContactStreetAddressComponent = new StreetComponent();
                        $jsContactStreetAddressComponent->setAtType("StreetComponent");
                        $jsContactStreetAddressComponent->setType("name");
                        $jsContactStreetAddressComponent->setValue($vCardStreetAddress);
                        $jsContactStreet[] = $jsContactStreetAddressComponent;
                    }

                    $jsContactAddress->setStreet($jsContactStreet);


                    // Map the LABEL parameter to "fullAddress"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['LABEL'])) {
                        $jsContactAddress->setFullAddress($vCardAddressProperty['LABEL']);
                    }

                    // Map the GEO parameter to "coordinates"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['GEO'])) {
                        $jsContactAddress->setCoordinates($vCardAddressProperty['GEO']);
                    }

                    // Map the TZ parameter to "timeZone"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['TZ'])) {
                        $jsContactAddress->setTimeZone($vCardAddressProperty['TZ']);
                    }

                    // Map the CC parameter to "countryCode"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['CC'])) {
                        $jsContactAddress->setCountryCode($vCardAddressProperty['CC']);
                    }

                    // Map the PREF parameter to "pref"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['PREF'])) {
                        $jsContactAddress->setPref($vCardAddressProperty['PREF']);
                    }

                    // Map the TYPE parameter to "contexts"
                    if (AdapterUtil::isSetNotNullAndNotEmpty($vCardAddressProperty['TYPE'])) {
                        $jsContactAddressContexts = [];

                        foreach ($vCardAddressProperty['TYPE'] as $paramValue) {
                            switch ($paramValue) {
                                case 'home':
                                    $jsContactAddressContexts['private'] = true;
                                    break;

                                case 'work':
                                    $jsContactAddressContexts['work'] = true;
                                    break;

                                default:
                                    $this->logger->warning(
                                        "Unknown vCard TYPE parameter value encountered
                                        for vCard property ADR: " . $paramValue
                                    );
                                    break;
                            }

                            $jsContactAddress->setContexts(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactAddressContexts)
                                ? $jsContactAddressContexts
                                : null
                            );
                        }
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardAddressProperty['ALTID'])
                        && !empty($vCardAddressProperty['ALTID'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property ADR"
                        );
                    }

                    if (
                        isset($vCardAddressProperty['LANGUAGE'])
                        && !empty($vCardAddressProperty['LANGUAGE'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter LANGUAGE encountered
                            for vCard property ADR"
                        );
                    }

                    // Since "addresses" is a map and key creation for the map keys is not specified, we use
                    // the MD5 hash of a JSContact address object's serialized string as the key for this same
                    // object that is the corresponding value to this key in "addresses"
                    $jsContactAddressesProperty[md5(serialize($jsContactAddress))] = $jsContactAddress;
                }
            }
        }

        // After we've converted all ADR properties to "addresses", we check if the TZ property in vCard is set
        // If it's set, then we create an extra entry in "addresses" that contains the inforamtion from TZ
        if (in_array("TZ", $this->vCardChildren)) {
            $vCardTimeZoneProperty = $this->vCard->TZ;

            if (isset($vCardTimeZoneProperty) && !empty($vCardTimeZoneProperty)) {
                $vCardTimeZonePropertyValue = $vCardTimeZoneProperty->getValue();

                if (isset($vCardTimeZonePropertyValue) && !empty($vCardTimeZonePropertyValue)) {
                    // If the TZ value is a known time zone identifier, then we set it in the "addresses" entry
                    // Otherwise, we just log an error
                    if (in_array($vCardTimeZonePropertyValue, timezone_identifiers_list())) {
                        $jsContactTimeZoneAddressEntry = new Address();
                        $jsContactTimeZoneAddressEntry->setAtType("Address");
                        $jsContactTimeZoneAddressEntry->setTimeZone($vCardTimeZonePropertyValue);

                        // In order to be able to differentiate between JSContact addresses corresponding
                        // to ADR and TZ, for those that correspond to TZ we set the Address object's label
                        // to "timezone", so that we can clearly know we have to convert it to TZ when
                        // converting from JSContact to vCard
                        $jsContactTimeZoneAddressEntry->setLabel("timezone");

                        $jsContactAddressesProperty[md5($vCardTimeZonePropertyValue)]
                        = $jsContactTimeZoneAddressEntry;
                    } else {
                        $this->logger->error(
                            "Unknown time zone identifier provided as value for
                            the vCard TZ property: " . $vCardTimeZonePropertyValue
                        );
                    }
                }
            }
        }

        return $jsContactAddressesProperty;
    }

    /**
     * This function maps the JSContact "addresses" property to the vCard ADR property
     *
     * @param array<string, Address>|null $jsContactAddresses
     * The "addresses" JSContact property as a map of string to Address objects
     */
    public function setADR($jsContactAddresses)
    {
        if (!isset($jsContactAddresses) || empty($jsContactAddresses)) {
            return;
        }

        foreach ($jsContactAddresses as $id => $jsContactAddress) {
            if (isset($jsContactAddress) && !empty($jsContactAddress)) {
                $jsContactAddressLocality = $jsContactAddress->locality;
                $jsContactAddressRegion = $jsContactAddress->region;
                $jsContactAddressPostcode = $jsContactAddress->postcode;
                $jsContactAddressCountry = $jsContactAddress->country;
                $jsContactAddressStreet = $jsContactAddress->street;

                $vCardPostOfficeBox = null;
                $vCardExtendedAddress = null;
                $vCardStreetAddress = null;
                $vCardLocality = null;
                $vCardRegion = null;
                $vCardPostalCode = null;
                $vCardCountryName = null;

                if (isset($jsContactAddressLocality) && !empty($jsContactAddressLocality)) {
                    $vCardLocality = $jsContactAddressLocality;
                }

                if (isset($jsContactAddressRegion) && !empty($jsContactAddressRegion)) {
                    $vCardRegion = $jsContactAddressRegion;
                }

                if (isset($jsContactAddressPostcode) && !empty($jsContactAddressPostcode)) {
                    $vCardPostalCode = $jsContactAddressPostcode;
                }

                if (isset($jsContactAddressCountry) && !empty($jsContactAddressCountry)) {
                    $vCardCountryName = $jsContactAddressCountry;
                }

                if (isset($jsContactAddressStreet) && !empty($jsContactAddressStreet)) {
                    foreach ($jsContactAddressStreet as $jsContactAddressStreetComponent) {
                        if (isset($jsContactAddressStreetComponent) && !empty($jsContactAddressStreetComponent)) {
                            $jsContactAddressStreetComponentValue = $jsContactAddressStreetComponent->value;
                            if (
                                isset($jsContactAddressStreetComponentValue)
                                && !empty($jsContactAddressStreetComponentValue)
                            ) {
                                $jsContactAddressStreetComponentType = $jsContactAddressStreetComponent->type;
                                if (
                                    isset($jsContactAddressStreetComponentType)
                                    && !empty($jsContactAddressStreetComponentType)
                                ) {
                                    switch ($jsContactAddressStreetComponentType) {
                                        case 'postOfficeBox':
                                            $vCardPostOfficeBox = $jsContactAddressStreetComponentValue;
                                            break;

                                        case 'extension':
                                            $vCardExtendedAddress = $jsContactAddressStreetComponentValue;
                                            break;

                                        case 'name':
                                            $vCardStreetAddress = $jsContactAddressStreetComponentValue;
                                            break;

                                        default:
                                            throw new InvalidArgumentException(
                                                "Encountered value for StreetComponent's \"type\"
                                                property which is neither \"postOfficeBox\", nor \"extension\", nor
                                                \"name\" during conversion from JSContact's \"addresses\" property to
                                                vCard's ADR property. Encountered value is: "
                                                . $jsContactAddressStreetComponentType
                                            );
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }

                // ADR parameter writing
                $jsContactAddressFullAddress = $jsContactAddress->fullAddress;
                $jsContactAddressCoordinates = $jsContactAddress->coordinates;
                $jsContactAddressTimeZone = $jsContactAddress->timeZone;
                $jsContactAddressCountryCode = $jsContactAddress->countryCode;
                $jsContactAddressPref = $jsContactAddress->pref;
                $jsContactAddressContexts = $jsContactAddress->contexts;

                $vCardAdrParams = [];

                if (isset($jsContactAddressFullAddress) && !empty($jsContactAddressFullAddress)) {
                    $vCardAdrParams['LABEL'] = $jsContactAddressFullAddress;
                }

                if (isset($jsContactAddressCoordinates) && !empty($jsContactAddressCoordinates)) {
                    $vCardAdrParams['GEO'] = $jsContactAddressCoordinates;
                }

                if (isset($jsContactAddressTimeZone) && !empty($jsContactAddressTimeZone)) {
                    $vCardAdrParams['TZ'] = $jsContactAddressTimeZone;
                }

                if (isset($jsContactAddressCountryCode) && !empty($jsContactAddressCountryCode)) {
                    $vCardAdrParams['CC'] = $jsContactAddressCountryCode;
                }

                if (isset($jsContactAddressPref)) {
                    $vCardAdrParams['PREF'] = $jsContactAddressPref;
                }

                if (isset($jsContactAddressContexts) && !empty($jsContactAddressContexts)) {
                    $vCardAdrTypes = [];

                    foreach ($jsContactAddressContexts as $jsContactAddressContext => $booleanValue) {
                        if (isset($jsContactAddressContext) && !empty($jsContactAddressContext)) {
                            switch ($jsContactAddressContext) {
                                case 'private':
                                    $vCardAdrTypes[] = 'home';
                                    break;

                                case 'work':
                                    $vCardAdrTypes[] = 'work';
                                    break;

                                default:
                                    throw new InvalidArgumentException(
                                        "Unknown value for the JSContact property \"contexts\"
                                        encountered during conversion from JSContact's \"addresses\" property to
                                        vCard's ADR property. The encountered value is: " . $jsContactAddressContext
                                    );
                                    break;
                            }
                        }
                    }

                    $vCardAdrParams['TYPE'] = $vCardAdrTypes;
                }

                $this->vCard->add(
                    "ADR",
                    [
                        $vCardPostOfficeBox,
                        $vCardExtendedAddress,
                        $vCardStreetAddress,
                        $vCardLocality,
                        $vCardRegion,
                        $vCardPostalCode,
                        $vCardCountryName
                    ],
                    $vCardAdrParams
                );
            }
        }
    }

    /**
     * This function maps the corresponding entry in the JSContact "addresses" property to the vCard TZ property
     *
     * @param array<string, Address>|null $jsContactAddresses
     * The "addresses" JSContact property as a map of string to Address objects
     */
    public function setTZ($jsContactAddresses)
    {
        if (!isset($jsContactAddresses) || empty($jsContactAddresses)) {
            return;
        }

        foreach ($jsContactAddresses as $id => $jsContactAddress) {
            if (isset($jsContactAddress) && !empty($jsContactAddress)) {
                $jsContactAddressTimeZone = $jsContactAddress->timeZone;
                $jsContactAddressLabel = $jsContactAddress->label;

                // We use the Address object's label property from JSContact to
                // find out here if we're dealing with an entry in the "addresses"
                // JSContact property that corresponds to the vCard TZ property
                if (
                    isset($jsContactAddressLabel)
                    && !empty($jsContactAddressLabel)
                    && strcmp($jsContactAddressLabel, "timezone")
                ) {
                    if (isset($jsContactAddressTimeZone) && !empty($jsContactAddressTimeZone)) {
                        $this->vCard->add("TZ", $jsContactAddressTimeZone);
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
                                        $jsContactPhoneContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactPhoneContexts['work'] = true;
                                        break;

                                    // If we encounter 'other' as a value from vCard, we need to set the JSContact
                                    // "contexts" property to null
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
                                        $jsContactPhoneFeatures['fax'] = true;
                                        break;

                                    case 'cell':
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
     * This function maps the JSContact "phones" property to the vCard TEL property
     *
     * @param array<string, Phone>|null $jsContactPhones
     * The "phones" JSContact property as a map of strings to Phone objects
     */
    public function setTel($jsContactPhones)
    {
        if (!isset($jsContactPhones) || empty($jsContactPhones)) {
            return;
        }

        foreach ($jsContactPhones as $id => $jsContactPhone) {
            if (isset($jsContactPhone) && !empty($jsContactPhone)) {
                $jsContactPhoneValue = $jsContactPhone->phone;
                $jsContactPhoneContexts = $jsContactPhone->contexts;
                $jsContactPhoneFeatures = $jsContactPhone->features;
                $jsContactPhoneLabels = $jsContactPhone->label;
                $jsContactPhonePref = $jsContactPhone->pref;

                $vCardTelParams = [];

                if (isset($jsContactPhoneValue) && !empty($jsContactPhoneValue)) {
                    if (isset($jsContactPhoneContexts) && !empty($jsContactPhoneContexts)) {
                        foreach ($jsContactPhoneContexts as $jsContactPhoneContext => $booleanValue) {
                            switch ($jsContactPhoneContext) {
                                case 'private':
                                    $vCardTelParams['type'][] = 'home';
                                    break;

                                case 'work':
                                    $vCardTelParams['type'][] = 'work';
                                    break;

                                default:
                                    throw new InvalidArgumentException(
                                        "Unknown value encountered for the \"contexts\"
                                        JSContact property of a JSContact Phone object during conversion from
                                        JSContact's \"phones\" property to vCard's TEL property.
                                        Encountered value is: " . $jsContactPhoneContext
                                    );
                                    break;
                            }
                        }
                    } else { // In case that $jsContactPhoneContexts is null, we set the vCard type to be 'other'
                        $vCardTelParams['type'] = 'other';
                    }

                    if (isset($jsContactPhoneFeatures) && !empty($jsContactPhoneFeatures)) {
                        foreach ($jsContactPhoneFeatures as $jsContactPhoneFeature => $booleanValue) {
                            $vCardTelParams['type'][] = $jsContactPhoneFeature;
                        }
                    }

                    if (isset($jsContactPhoneLabels) && !empty($jsContactPhoneLabels)) {
                        // Since $jsContactPhoneLabels is a string that contains one or more values separated
                        // by a comma, we need to turn it into an array by calling explode() with comma as delimiter
                        $jsContactPhoneLabels = explode(',', $jsContactPhoneLabels);

                        foreach ($jsContactPhoneLabels as $jsContactPhoneLabel) {
                            $vCardTelParams['type'][] = $jsContactPhoneLabel;
                        }
                    }

                    if (isset($jsContactPhonePref)) {
                        $vCardTelParams['pref'] = $jsContactPhonePref;
                    }

                    $this->vCard->add("TEL", $jsContactPhoneValue, $vCardTelParams);
                }
            }
        }
    }

    /**
     * This function maps the vCard "EMAIL" property to the JSContact "emails" property
     *
     * @return array<string, EmailAddress>|null The "emails" JSContact property as a map of IDs to EmailAddress objects
     */
    public function getEmails()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactEmailsProperty = null;

        // EMAIL property mapping
        if (in_array("EMAIL", $this->vCardChildren)) {
            $vCardEmailProperties = $this->vCard->EMAIL;

            foreach ($vCardEmailProperties as $vCardEmailProperty) {
                if (isset($vCardEmailProperty)) {
                    $vCardEmailPropertyValue = $vCardEmailProperty->getValue();

                    if (isset($vCardEmailPropertyValue) && !empty($vCardEmailPropertyValue)) {
                        $jsContactEmail = new EmailAddress();
                        $jsContactEmail->setAtType("EmailAddress");
                        $jsContactEmail->setEmail($vCardEmailPropertyValue);

                        // Map the TYPE parameter to the "context" property
                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardEmailProperty['TYPE'])) {
                            $jsContactEmailContexts = [];

                            foreach ($vCardEmailProperty['TYPE'] as $paramValue) {
                                switch (strtolower($paramValue)) {
                                    case 'home':
                                        $jsContactEmailContexts['private'] = true;
                                        break;

                                    case 'work':
                                        $jsContactEmailContexts['work'] = true;
                                        break;

                                    case 'other':
                                        $jsContactEmailContexts = null;
                                        break;

                                    default:
                                        $this->logger->warning(
                                            "Unknown vCard TYPE parameter value encountered
                                            for vCard property EMAIL: " . $paramValue
                                        );
                                        break;
                                }
                            }

                            $jsContactEmail->setContexts(
                                AdapterUtil::isSetNotNullAndNotEmpty($jsContactEmailContexts)
                                ? $jsContactEmailContexts
                                : null
                            );
                        }

                        // Map the PREF parameter to the "pref" property
                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardEmailProperty['PREF'])) {
                            $jsContactEmail->setPref($vCardEmailProperty['PREF']);
                        }

                        $jsContactEmailsProperty[md5($vCardEmailPropertyValue)] = $jsContactEmail;
                    }
                }
            }
        }

        return $jsContactEmailsProperty;
    }

    /**
     * This function maps the JSContact "emails" property to the vCard EMAIL property
     *
     * @param array<string, EmailAddress>|null $jsContactEmails
     * The "emails" JSContact property as a map of strings to EmailAddress objects
     */
    public function setEmail($jsContactEmails)
    {
        if (!isset($jsContactEmails) || empty($jsContactEmails)) {
            return;
        }

        foreach ($jsContactEmails as $id => $jsContactEmail) {
            if (isset($jsContactEmail) && !empty($jsContactEmail)) {
                $jsContactEmailValue = $jsContactEmail->email;
                $jsContactEmailContexts = $jsContactEmail->contexts;
                $jsContactEmailPref = $jsContactEmail->pref;

                $vCardEmailParams = [];

                if (isset($jsContactEmailValue) && !empty($jsContactEmailValue)) {
                    if (isset($jsContactEmailContexts) && !empty($jsContactEmailContexts)) {
                        foreach ($jsContactEmailContexts as $jsContactEmailContext => $booleanValue) {
                            switch ($jsContactEmailContext) {
                                case 'private':
                                    $vCardEmailParams['type'][] = 'home';
                                    break;

                                case 'work':
                                    $vCardEmailParams['type'][] = 'work';
                                    break;

                                default:
                                    throw new InvalidArgumentException(
                                        "Unknown value encountered for the \"contexts\"
                                        JSContact property of a JSContact EmailAddress object during conversion from
                                        JSContact's \"emails\" property to vCard's EMAIL property.
                                        Encountered value is: " . $jsContactEmailContext
                                    );
                                    break;
                            }
                        }
                    } else { // In case that $jsContactEmailContexts is null, we set the vCard type to be 'other'
                        $vCardEmailParams['type'] = 'other';
                    }

                    if (isset($jsContactEmailPref)) {
                        $vCardEmailParams['pref'] = $jsContactEmailPref;
                    }

                    $this->vCard->add("EMAIL", $jsContactEmailValue, $vCardEmailParams);
                }
            }
        }
    }

    /**
     * This function maps the vCard "LANG" property to the JSContact "preferredContactLanguages" property
     *
     * @return array<string, array<ContactLanguage>>|null
     * The "preferredContactLanguages" JSContact property as a map of IDs to arrays fo ContactLanguage objects
     */
    public function getPreferredContactLanguages()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactPreferredContactLanguagesProperty = null;

        // LANG property mapping
        if (in_array("LANG", $this->vCardChildren)) {
            $vCardLangProperties = $this->vCard->LANG;

            foreach ($vCardLangProperties as $vCardLangProperty) {
                if (isset($vCardLangProperty)) {
                    $vCardLangPropertyValue = $vCardLangProperty->getValue();

                    if (isset($vCardLangPropertyValue) && !empty($vCardLangPropertyValue)) {
                        // According to the IETF draft:
                        // "If both PREF and TYPE parameters are missing, the array of
                        // "ContactLanguage" objects MUST be empty.
                        if (
                            !AdapterUtil::isSetNotNullAndNotEmpty($vCardLangProperty['PREF'])
                            && !AdapterUtil::isSetNotNullAndNotEmpty($vCardLangProperty['TYPE'])
                        ) {
                            $jsContactPreferredContactLanguagesProperty[$vCardLangPropertyValue] = [];
                            continue;
                        }

                        $jsContactLangEntry = new ContactLanguage();
                        $jsContactLangEntry->setAtType("ContactLanguage");

                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardLangProperty['TYPE'])) {
                            switch ($vCardLangProperty['TYPE']) {
                                case 'home':
                                    $jsContactLangEntry->setContext('private');
                                    break;

                                case 'work':
                                    $jsContactLangEntry->setContext('work');
                                    break;

                                default:
                                    $this->logger->warning(
                                        "Unknown vCard TYPE parameter value encountered
                                        for vCard property LANG: " . $vCardLangProperty['TYPE']
                                    );
                                    break;
                            }
                        }

                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardLangProperty['PREF'])) {
                            $jsContactLangEntry->setPref($vCardLangProperty['PREF']);
                        }

                        // The "preferredContactLanguages" property is a map and the key is the value of
                        // vCard LANG property while the key is the ContactLanguage object we just created above
                        $jsContactPreferredContactLanguagesProperty[$vCardLangPropertyValue] = $jsContactLangEntry;
                    }
                }
            }
        }

        return $jsContactPreferredContactLanguagesProperty;
    }

    /**
     * This function maps the JSContact "preferredContactLanguages" property to the vCard LANG property
     *
     * @param array<string, array<ContactLanguage>>|null $jsContactPreferredContactLanguages
     * The "preferredContactLanguages" JSContact property as a map of strings to arrays of ContactLanguage objects
     */
    public function setLang($jsContactPreferredContactLanguages)
    {
        if (!isset($jsContactPreferredContactLanguages) || empty($jsContactPreferredContactLanguages)) {
            return;
        }

        foreach ($jsContactPreferredContactLanguages as $languageTag => $jsContactPreferredContactLanguageArray) {
            if (isset($languageTag) && !empty($languageTag)) {
                if (isset($jsContactPreferredContactLanguageArray) && !empty($jsContactPreferredContactLanguageArray)) {
                    $vCardLangParams = [];

                    foreach ($jsContactPreferredContactLanguageArray as $jsContactPreferredContactLanguage) {
                        $jsContactPreferredContactLanguageContext = $jsContactPreferredContactLanguage->context;
                        $jsContactPreferredContactLanguagePref = $jsContactPreferredContactLanguage->pref;

                        if (
                            isset($jsContactPreferredContactLanguageContext)
                            && !empty($jsContactPreferredContactLanguageContext)
                        ) {
                            $vCardLangParams['type'][] = $jsContactPreferredContactLanguageContext;
                        }

                        if (
                            isset($jsContactPreferredContactLanguagePref)
                            && !empty($jsContactPreferredContactLanguagePref)
                        ) {
                            $vCardLangParams['pref'][] = $jsContactPreferredContactLanguagePref;
                        }
                    }

                    $this->vCard->add("LANG", $languageTag, $vCardLangParams);
                }
            }
        }
    }

    /**
     * This function maps the vCard "TITLE" and "ROLE" properties to the JSContact "titles" property
     *
     * @return array<string, Title>|null The "titles" JSContact property as a map of IDs to Title objects
     */
    public function getTitles()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactTitlesProperty = null;

        // TITLE property mapping
        if (in_array("TITLE", $this->vCardChildren)) {
            $vCardTitleProperties = $this->vCard->TITLE;

            foreach ($vCardTitleProperties as $vCardTitleProperty) {
                if (isset($vCardTitleProperty)) {
                    $vCardTitlePropertyValue = $vCardTitleProperty->getValue();

                    if (isset($vCardTitlePropertyValue) && !empty($vCardTitlePropertyValue)) {
                        $jsContactTitleEntry = new Title();
                        $jsContactTitleEntry->setAtType("Title");
                        $jsContactTitleEntry->setTitle($vCardTitlePropertyValue);

                        $jsContactTitlesProperty[md5($vCardTitlePropertyValue)] = $jsContactTitleEntry;
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardTitleProperty['ALTID'])
                        && !empty($vCardTitleProperty['ALTID'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property TITLE"
                        );
                    }

                    if (
                        isset($vCardTitleProperty['LANGUAGE'])
                        && !empty($vCardTitleProperty['LANGUAGE'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter LANGUAGE encountered
                            for vCard property TITLE"
                        );
                    }
                }
            }
        }

        // ROLE property mapping
        if (in_array("ROLE", $this->vCardChildren)) {
            $vCardRoleProperties = $this->vCard->ROLE;

            foreach ($vCardRoleProperties as $vCardRoleProperty) {
                if (isset($vCardRoleProperty)) {
                    $vCardRolePropertyValue = $vCardRoleProperty->getValue();

                    if (isset($vCardRolePropertyValue) && !empty($vCardRolePropertyValue)) {
                        $jsContactRoleEntry = new Title();
                        $jsContactRoleEntry->setAtType("Title");
                        $jsContactRoleEntry->setTitle($vCardRolePropertyValue);

                        $jsContactTitlesProperty[md5($vCardRolePropertyValue)] = $jsContactRoleEntry;
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardRoleProperty['ALTID'])
                        && !empty($vCardRoleProperty['ALTID'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property ROLE"
                        );
                    }

                    if (
                        isset($vCardRoleProperty['LANGUAGE'])
                        && !empty($vCardRoleProperty['LANGUAGE'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter LANGUAGE encountered
                            for vCard property ROLE"
                        );
                    }
                }
            }
        }

        return $jsContactTitlesProperty;
    }

    /**
     * This function maps the JSContact "titles" property to the vCard TITLE property
     *
     * @param array<string, Title>|null $jsContactTitles
     * The "titles" JSContact property as a map of strings to Title objects
     */
    public function setTitle($jsContactTitles)
    {
        if (!isset($jsContactTitles) || empty($jsContactTitles)) {
            return;
        }

        foreach ($jsContactTitles as $id => $jsContactTitle) {
            if (isset($jsContactTitle) && !empty($jsContactTitle)) {
                $jsContactTitleValue = $jsContactTitle->title;
                if (isset($jsContactTitleValue) && !empty($jsContactTitleValue)) {
                    $this->vCard->add("TITLE", $jsContactTitleValue);
                }
            }
        }
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

                        if (strpos($vCardOrgPropertyValue, ';') !== false) {
                            $vCardOrgPropertyValue = explode(';', $vCardOrgPropertyValue);
                            $jsContactOrganization->setName($vCardOrgPropertyValue[0]);
                            $jsContactOrganization->setUnits(array_splice($vCardOrgPropertyValue, 0, 1));
                        } else {
                            $jsContactOrganization->setName($vCardOrgPropertyValue);
                        }

                        $jsContactOrganizationsProperty[md5($vCardOrgPropertyValue[0])] = $jsContactOrganization;
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardOrgProperty['ALTID'])
                        && !empty($vCardOrgProperty['ALTID'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property ORG"
                        );
                    }

                    if (
                        isset($vCardOrgProperty['LANGUAGE'])
                        && !empty($vCardOrgProperty['LANGUAGE'])
                    ) {
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
                    if (isset($jsContactOrganizationUnits) && !empty($jsContactOrganizationUnits)) {
                        $this->vCard->add(
                            "ORG",
                            $jsContactOrganizationName . ';' . implode(';', $jsContactOrganizationUnits)
                        );
                    } else {
                        $this->vCard->add("ORG", $jsContactOrganizationName);
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard "RELATED" property to the JSContact "relatedTo" property
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

        return $jsContactRelatedToProperty;
    }

    /**
     * This function maps the JSContact "relatedTo" property to the vCard RELATED property
     *
     * @param array<string, Relation>|null $jsContactRelatedTo
     * The "relatedTo" JSContact property as a map of strings to Relation objects
     */
    public function setRelated($jsContactRelatedTo)
    {
        if (!isset($jsContactRelatedTo) || empty($jsContactRelatedTo)) {
            return;
        }

        foreach ($jsContactRelatedTo as $relatedUid => $jsContactRelation) {
            if (isset($relatedUid) && !empty($relatedUid)) {
                if (isset($jsContactRelation) && !empty($jsContactRelation)) {
                    $jsContactRelationValues = $jsContactRelation->relation;
                    if (isset($jsContactRelationValues) && !empty($jsContactRelationValues)) {
                        $vCardRelatedParams = [];
                        foreach ($jsContactRelationValues as $jsContactRelationValue => $booleanValue) {
                            if (isset($jsContactRelationValue) && !empty($jsContactRelationValue)) {
                                $vCardRelatedParams['type'][] = $jsContactRelationValue;
                            }
                        }

                        $this->vCard->add("RELATED", $relatedUid, $vCardRelatedParams);
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard "EXPERTISE", "HOBBY" and "INTEREST" properties to
     * the JSContact "personalInfo" property
     *
     * @return array<string, PersonalInformation>|null
     * The "personalInfo" JSContact property as a map of IDs to PersonalInformation objects
     */
    public function getPersonalInfo()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactPersonalInfoProperty = null;

        // EXPERTISE property mapping
        if (in_array("EXPERTISE", $this->vCardChildren)) {
            $vCardExpertiseProperties = $this->vCard->EXPERTISE;

            foreach ($vCardExpertiseProperties as $vCardExpertiseProperty) {
                if (isset($vCardExpertiseProperty)) {
                    $vCardExpertisePropertyValue = $vCardExpertiseProperty->getValue();

                    if (isset($vCardExpertisePropertyValue) && !empty($vCardExpertisePropertyValue)) {
                        $jsContactExpertiseEntry = new PersonalInformation();
                        $jsContactExpertiseEntry->setAtType("PersonalInformation");
                        $jsContactExpertiseEntry->setType('expertise');
                        $jsContactExpertiseEntry->setValue($vCardExpertisePropertyValue);

                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardExpertiseProperty['LEVEL'])) {
                            switch ($vCardExpertiseProperty['LEVEL']) {
                                case 'beginner':
                                    $jsContactExpertiseEntry->setLevel('low');
                                    break;

                                case 'average':
                                    $jsContactExpertiseEntry->setLevel('medium');
                                    break;

                                case 'expert':
                                    $jsContactExpertiseEntry->setLevel('high');
                                    break;

                                default:
                                    $this->logger->warning(
                                        "Unknown vCard LEVEL parameter value encountered
                                        for vCard property EXPERTISE: " . $vCardExpertiseProperty['LEVEL']
                                    );
                                    break;
                            }
                        }

                        // If the INDEX parameter is set for EXPERTISE, then use it as the key for
                        // the "personalInfo" entry representing the corresponding expertise
                        // If it's not set, then use a MD5 hash of the expertise's value
                        if (isset($vCardExpertiseProperty['INDEX']) && !empty($vCardExpertiseProperty['INDEX'])) {
                            $jsContactPersonalInfoProperty["EXPERTISE-" . $vCardExpertiseProperty['INDEX']]
                            = $jsContactExpertiseEntry;
                        } else {
                            $jsContactPersonalInfoProperty[md5($vCardExpertisePropertyValue)]
                            = $jsContactExpertiseEntry;
                        }
                    }
                }
            }
        }

        // HOBBY property mapping
        if (in_array("HOBBY", $this->vCardChildren)) {
            $vCardHobbyProperties = $this->vCard->HOBBY;

            foreach ($vCardHobbyProperties as $vCardHobbyProperty) {
                if (isset($vCardHobbyProperty)) {
                    $vCardHobbyPropertyValue = $vCardHobbyProperty->getValue();

                    if (isset($vCardHobbyPropertyValue) && !empty($vCardHobbyPropertyValue)) {
                        $jsContactHobbyEntry = new PersonalInformation();
                        $jsContactHobbyEntry->setAtType("PersonalInformation");
                        $jsContactHobbyEntry->setType('hobby');
                        $jsContactHobbyEntry->setValue($vCardHobbyPropertyValue);

                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardHobbyProperty['LEVEL'])) {
                            $jsContactHobbyEntry->setLevel($vCardHobbyProperty['LEVEL']);
                        }

                        // If the INDEX parameter is set for HOBBY, then use it as the key for
                        // the "personalInfo" entry representing the corresponding hobby
                        // If it's not set, then use a MD5 hash of the hobby's value
                        if (isset($vCardHobbyProperty['INDEX']) && !empty($vCardHobbyProperty['INDEX'])) {
                            $jsContactPersonalInfoProperty["HOBBY-" . $vCardHobbyProperty['INDEX']]
                            = $jsContactHobbyEntry;
                        } else {
                            $jsContactPersonalInfoProperty[md5($vCardHobbyPropertyValue)]
                            = $jsContactHobbyEntry;
                        }
                    }
                }
            }
        }

        // INTEREST property mapping
        if (in_array("INTEREST", $this->vCardChildren)) {
            $vCardInterestProperties = $this->vCard->INTEREST;

            foreach ($vCardInterestProperties as $vCardInterestProperty) {
                if (isset($vCardInterestProperty)) {
                    $vCardInterestPropertyValue = $vCardInterestProperty->getValue();

                    if (isset($vCardInterestPropertyValue) && !empty($vCardInterestPropertyValue)) {
                        $jsContactInterestEntry = new PersonalInformation();
                        $jsContactInterestEntry->setAtType("PersonalInformation");
                        $jsContactInterestEntry->setType('interest');
                        $jsContactInterestEntry->setValue($vCardInterestPropertyValue);

                        if (AdapterUtil::isSetNotNullAndNotEmpty($vCardInterestProperty['LEVEL'])) {
                            $jsContactInterestEntry->setLevel($vCardInterestProperty['LEVEL']);
                        }

                        // If the INDEX parameter is set for INTEREST, then use it as the key for
                        // the "personalInfo" entry representing the corresponding interest
                        // If it's not set, then use a MD5 hash of the interest's value
                        if (isset($vCardInterestProperty['INDEX']) && !empty($vCardInterestProperty['INDEX'])) {
                            $jsContactPersonalInfoProperty["INTEREST-" . $vCardInterestProperty['INDEX']]
                            = $jsContactInterestEntry;
                        } else {
                            $jsContactPersonalInfoProperty[md5($vCardInterestPropertyValue)]
                            = $jsContactInterestEntry;
                        }
                    }
                }
            }
        }

        return $jsContactPersonalInfoProperty;
    }

    /**
     * This function maps the entries of the JSContact "relatedTo" property corresponding to
     * the vCard EXPERTISE property to it
     *
     * @param array<string, PersonalInformation>|null $jsContactPersonalInfo
     * The "personalInfo" JSContact property as a map of strings to PersonalInformation objects
     */
    public function setExpertise($jsContactPersonalInfo)
    {
        if (!isset($jsContactPersonalInfo) || empty($jsContactPersonalInfo)) {
            return;
        }

        foreach ($jsContactPersonalInfo as $id => $jsContactExpertise) {
            if (isset($jsContactExpertise) && !empty($jsContactExpertise)) {
                $jsContactExpertiseType = $jsContactExpertise->type;
                if (
                    isset($jsContactExpertiseType)
                    && !empty($jsContactExpertiseType)
                    && strcmp($jsContactExpertiseType, "expertise")
                ) {
                    $vCardExpertiseParams = [];

                    $jsContactExpertiseValue = $jsContactExpertise->value;
                    if (isset($jsContactExpertiseValue) && !empty($jsContactExpertiseValue)) {
                        $jsContactExpertiseLevel = $jsContactExpertise->level;
                        if (isset($jsContactExpertiseLevel) && !empty($jsContactExpertiseLevel)) {
                            switch ($jsContactExpertiseLevel) {
                                case 'low':
                                    $vCardExpertiseParams['level'][] = 'beginner';
                                    break;

                                case 'medium':
                                    $vCardExpertiseParams['level'][] = 'average';
                                    break;

                                case 'high':
                                    $vCardExpertiseParams['level'][] = 'expert';
                                    break;

                                default:
                                    throw new InvalidArgumentException(
                                        "Unknown value encountered for the JSContact
                                        \"level\" property of the JSContact PersonalInformation object
                                        during conversion from the \"personalInfo\" JSContact property to
                                        the EXPERTISE vCard property. Encountered value is:" . $jsContactExpertiseLevel
                                    );
                                    break;
                            }
                        }

                        if (isset($id) && !empty($id) && strpos($id, "-") !== false) {
                            $vCardExpertiseParams['index'] = explode('-', $id)[1];
                        }

                        $this->vCard->add("EXPERTISE", $jsContactExpertiseValue, $vCardExpertiseParams);
                    }
                }
            }
        }
    }

    /**
     * This function maps the entries of the JSContact "relatedTo" property corresponding to
     * the vCard HOBBY property to it
     *
     * @param array<string, PersonalInformation>|null $jsContactPersonalInfo
     * The "personalInfo" JSContact property as a map of strings to PersonalInformation objects
     */
    public function setHobby($jsContactPersonalInfo)
    {
        if (!isset($jsContactPersonalInfo) || empty($jsContactPersonalInfo)) {
            return;
        }

        foreach ($jsContactPersonalInfo as $id => $jsContactHobby) {
            if (isset($jsContactHobby) && !empty($jsContactHobby)) {
                $jsContactHobbyType = $jsContactHobby->type;
                if (
                    isset($jsContactHobbyType)
                    && !empty($jsContactHobbyType)
                    && strcmp($jsContactHobbyType, "hobby")
                ) {
                    $vCardHobbyParams = [];

                    $jsContactHobbyValue = $jsContactHobby->value;
                    if (isset($jsContactHobbyValue) && !empty($jsContactHobbyValue)) {
                        $jsContactHobbyLevel = $jsContactHobby->level;
                        if (isset($jsContactHobbyLevel) && !empty($jsContactHobbyLevel)) {
                            $vCardHobbyParams['level'][] = $jsContactHobbyLevel;
                        }

                        if (isset($id) && !empty($id) && strpos($id, "-") !== false) {
                            $vCardHobbyParams['index'] = explode('-', $id)[1];
                        }

                        $this->vCard->add("HOBBY", $jsContactHobbyValue, $vCardHobbyParams);
                    }
                }
            }
        }
    }

    /**
     * This function maps the entries of the JSContact "relatedTo" property corresponding to
     * the vCard INTEREST property to it
     *
     * @param array<string, PersonalInformation>|null $jsContactPersonalInfo
     * The "personalInfo" JSContact property as a map of strings to PersonalInformation objects
     */
    public function setInterest($jsContactPersonalInfo)
    {
        if (!isset($jsContactPersonalInfo) || empty($jsContactPersonalInfo)) {
            return;
        }

        foreach ($jsContactPersonalInfo as $id => $jsContactInterest) {
            if (isset($jsContactInterest) && !empty($jsContactInterest)) {
                $jsContactInterestType = $jsContactInterest->type;
                if (
                    isset($jsContactInterestType)
                    && !empty($jsContactInterestType)
                    && strcmp($jsContactInterestType, "interest")
                ) {
                    $vCardInterestParams = [];

                    $jsContactInterestValue = $jsContactInterest->value;
                    if (isset($jsContactInterestValue) && !empty($jsContactInterestValue)) {
                        $jsContactInterestLevel = $jsContactInterest->level;
                        if (isset($jsContactInterestLevel) && !empty($jsContactInterestLevel)) {
                            $vCardInterestParams['level'][] = $jsContactInterestLevel;
                        }

                        if (isset($id) && !empty($id) && strpos($id, "-") !== false) {
                            $vCardInterestParams['index'] = explode('-', $id)[1];
                        }

                        $this->vCard->add("INTEREST", $jsContactInterestValue, $vCardInterestParams);
                    }
                }
            }
        }
    }

    /**
     * This function maps the vCard "CATEGORIES" property to the JSContact "categories" property
     *
     * @return array<string, boolean>|null
     * The "categories" JSContact property as a map of categories to the boolean value "true"
     */
    public function getCategories()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactCategoriesProperty = null;

        // CATEGORIES property mapping
        if (in_array("CATEGORIES", $this->vCardChildren)) {
            $vCardCategoriesProperty = $this->vCard->CATEGORIES;

            if (isset($vCardCategoriesProperty)) {
                $vCardCategoriesPropertyValue = $vCardCategoriesProperty->getParts();

                if (isset($vCardCategoriesPropertyValue) && !empty($vCardCategoriesPropertyValue)) {
                    foreach ($vCardCategoriesPropertyValue as $vCardCategoryValue) {
                        $jsContactCategoriesProperty[$vCardCategoryValue] = true;
                    }
                }
            }
        }

        return $jsContactCategoriesProperty;
    }

    /**
     * This function maps the "categories" JSContact property to the CATEGORIES vCard property
     *
     * @param array<string, boolean>|null $jsContactCategories
     * The "categories" JSContact property as a map of strings to booleans
     */
    public function setCategories($jsContactCategories)
    {
        if (!isset($jsContactCategories) || empty($jsContactCategories)) {
            return;
        }

        $vCardCategoriesValues = [];

        foreach ($jsContactCategories as $jsContactCategory => $booleanValue) {
            if (isset($jsContactCategory) && !empty($jsContactCategory)) {
                $vCardCategoriesValues[] = $jsContactCategory;
            }
        }

        $this->vCard->add("CATEGORIES", implode(',', $vCardCategoriesValues));
    }

    /**
     * This function maps the vCard "NOTE" property to the JSContact "notes" property
     *
     * @return string|null The "notes" JSContact property as a string value
     */
    public function getNotes()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactNotesProperty = null;

        // NOTE property mapping
        if (in_array("NOTE", $this->vCardChildren)) {
            $vCardNoteProperties = $this->vCard->NOTE;

            // If there are multiple NOTE instances, they're condensed into a single note and separated by newline
            foreach ($vCardNoteProperties as $i => $vCardNoteProperty) {
                if (isset($vCardNoteProperty)) {
                    $vCardNotePropertyValue = $vCardNoteProperty->getValue();

                    if (isset($vCardNotePropertyValue) && !empty($vCardNotePropertyValue)) {
                        // Check if we're dealing with the last of multiple NOTE property instances
                        // If yes, then don't append a newline character after it
                        if (isset($i) && is_int($i)) {
                            if ($i < count($vCardNoteProperties) - 1) {
                                $jsContactNotesProperty .= $vCardNotePropertyValue . "\n";
                            } else {
                                $jsContactNotesProperty .= $vCardNotePropertyValue;
                            }
                        }
                    }

                    // Check if the currently unsupported vCard parameters ALTID and LANGUAGE are present
                    // If yes, then provide an error log with some information that they're not supported
                    if (
                        isset($vCardNoteProperty['ALTID'])
                        && !empty($vCardNoteProperty['ALTID'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter ALTID encountered
                            for vCard property NOTE"
                        );
                    }

                    if (
                        isset($vCardNoteProperty['LANGUAGE'])
                        && !empty($vCardNoteProperty['LANGUAGE'])
                    ) {
                        $this->logger->error(
                            "Currently unsupported vCard Parameter LANGUAGE encountered
                            for vCard property NOTE"
                        );
                    }
                }
            }
        }

        return $jsContactNotesProperty;
    }

    /**
     * This function maps the "notes" JSContact property to the NOTE vCard property
     *
     * @param string|null $jsContactNotes The "notes" JSContact property as a string
     */
    public function setNote($jsContactNotes)
    {
        if (!isset($jsContactNotes) || empty($jsContactNotes)) {
            return;
        }

        // Since multiple vCard NOTE instances are condensed into a single JSContact "notes"
        // property, separated by "\n", we explode "notes" by the "\n" character
        $jsContactNotes = explode("\n", $jsContactNotes);

        foreach ($jsContactNotes as $jsContactNote) {
            if (isset($jsContactNote) && !empty($jsContactNote)) {
                $this->vCard->add("NOTE", $jsContactNote);
            }
        }
    }

    /**
     * This function maps the vCard "PRODID" property to the JSContact "prodId" property
     *
     * @return string|null The "prodId" JSContact property as a string value
     */
    public function getProdId()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactProdIdProperty = null;

        // PRODID property mapping
        if (in_array("PRODID", $this->vCardChildren)) {
            $vCardProdIdProperty = $this->vCard->PRODID;

            if (isset($vCardProdIdProperty)) {
                $vCardProdIdPropertyValue = $vCardProdIdProperty->getValue();

                if (isset($vCardProdIdPropertyValue) && !empty($vCardProdIdPropertyValue)) {
                    $jsContactProdIdProperty = $vCardProdIdPropertyValue;
                }
            }
        }

        return $jsContactProdIdProperty;
    }

    /**
     * This function maps the "prodId" JSContact property to the PRODID vCard property
     *
     * @param string|null $jsContactProdId The "prodId" JSContact property as a string
     */
    public function setProdId($jsContactProdId)
    {
        if (!isset($jsContactProdId) || empty($jsContactProdId)) {
            return;
        }

        $this->vCard->add("PRODID", $jsContactProdId);
    }

    /**
     * This function maps the vCard "REV" property to the JSContact "updated" property
     *
     * @return string|null The "updated" JSContact property as a string value representing a date and time
     */
    public function getUpdated()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactUpdatedProperty = null;

        // REV property mapping
        if (in_array("REV", $this->vCardChildren)) {
            $vCardRevProperty = $this->vCard->REV;

            if (isset($vCardRevProperty)) {
                $vCardRevPropertyValue = $vCardRevProperty->getValue();

                if (isset($vCardRevPropertyValue) && !empty($vCardRevPropertyValue)) {
                    // Restructure the date string value to follow JSContact's format
                    $jsContactUpdatedProperty = AdapterUtil::parseDateTime(
                        $vCardRevPropertyValue,
                        'Ymd\THis\Z',
                        'Y-m-d\TH:i:s\Z'
                    );

                    if (is_null($jsContactUpdatedProperty)) {
                        $this->logger->error("Couldn't parse vCard REV property date to JSContact's
                        \"updated\" property. vCard date encountered is: " . $vCardRevPropertyValue);
                        return;
                    }
                }
            }
        }

        return $jsContactUpdatedProperty;
    }

    /**
     * This function maps the "updated" JSContact property to the REV vCard property
     *
     * @param string|null $jsContactUpdated The "updated" JSContact property as a datetime string
     */
    public function setRev($jsContactUpdated)
    {
        if (!isset($jsContactUpdated) || empty($jsContactUpdated)) {
            return;
        }

        $vCardRevValue = AdapterUtil::parseDateTime(
            $jsContactUpdated,
            'Y-m-d\TH:i:s\Z',
            'Ymd\THis\Z'
        );

        if (is_null($vCardRevValue)) {
            throw new InvalidArgumentException(
                "Date parsing failed during conversion from JSContact's
                \"updated\" property to vCard's REV property. Encountered date value that
                was tried for parsing is: " . $jsContactUpdated
            );
            return;
        }

        $this->vCard->add("REV", $vCardRevValue);
    }

    /**
     * This function maps the vCard "UID" property to the JSContact "uid" property
     *
     * @return string|null The "uid" JSContact property as a string value
     */
    public function getUid()
    {
        // Before trying to map any vCard properties to any JSContact properties,
        // check if the vCard has any properties at all and directly return if it doesn't have any
        if (!AdapterUtil::checkVCardChildren($this->vCard)) {
            return;
        }

        $jsContactUidProperty = null;

        // UID property mapping
        if (in_array("UID", $this->vCardChildren)) {
            $vCardUidProperty = $this->vCard->UID;

            if (isset($vCardUidProperty)) {
                $vCardUidPropertyValue = $vCardUidProperty->getValue();

                if (isset($vCardUidPropertyValue) && !empty($vCardUidPropertyValue)) {
                    $jsContactUidProperty = $vCardUidPropertyValue;
                }
            }
        }

        return $jsContactUidProperty;
    }

    /**
     * This function maps the "uid" JSContact property to the UID vCard property
     *
     * @param string|null $jsContactUid The "uid" JSContact property as a string
     */
    public function setUid($jsContactUid)
    {
        if (!isset($jsContactUid) || empty($jsContactUid)) {
            return;
        }

        $this->vCard->add("UID", $jsContactUid);
    }
}
