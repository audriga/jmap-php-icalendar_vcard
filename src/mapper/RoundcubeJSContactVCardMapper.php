<?php

namespace OpenXPort\Mapper;

use InvalidArgumentException;
use OpenXPort\Adapter\RoundcubeJSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Util\Logger;
use Sabre\VObject\ParseException;

/**
 * Roundcube-specific mapper for RFC 9553 ContactCard <-> vCard conversion.
 *
 * Extends JSContactVCardMapper to handle Roundcube's custom X-properties:
 * - X-MAIDENNAME (maiden name)
 * - X-ANNIVERSARY (anniversary date)
 * - X-GENDER (grammatical gender)
 * - X-DEPARTMENT (organization units)
 * - X-AIM, X-ICQ, X-MSN, X-YAHOO, X-JABBER, X-SKYPE-USERNAME (instant messaging)
 * - X-MANAGER, X-ASSISTANT, X-SPOUSE (relations)
 */
class RoundcubeJSContactVCardMapper extends JSContactVCardMapper
{
    protected $logger;

    /**
     * Map from JMAP ContactCard objects to vCard with Roundcube extensions.
     *
     * Handles both standard RFC 9553 properties and Roundcube-specific X-properties.
     *
     * @param array<string,ContactCard> $jmapData  creationId => ContactCard
     * @param RoundcubeJSContactVCardAdapter $adapter
     *
     * @return array<int,array<string,mixed>>  [ [ creationId => vcardString ], ... ]
     */
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                $adapter->reset();

                $adapter->setAddressBookId($jsContactCard->getAddressBookIds()); // addressBookId(s)

                $adapter->setUidFromJmap($jsContactCard);           // UID
                $adapter->setUpdatedFromJmap($jsContactCard);       // REV
                $adapter->setKindFromJmap($jsContactCard);          // KIND
                $adapter->setGramGenderFromJmap($jsContactCard);    // X-GENDER + X-MAIDENNAME
                $adapter->setCreatedFromJmap($jsContactCard);       // CREATED
                $adapter->setProdIdFromJmap($jsContactCard);        // PRODID
                $adapter->setLanguageFromJmap($jsContactCard);      // LANGUAGE
                $adapter->setNameFromJmap($jsContactCard);          // N
                $adapter->setFnFromJmap($jsContactCard);            // FN
                $adapter->setNicknameFromJmap($jsContactCard);      // NICKNAME
                $adapter->setMaidenNameFromJmap($jsContactCard);    // X-MAIDENNAME
                $adapter->setPronounsFromJmap($jsContactCard);      // PRONOUNS
                $adapter->setOrganizationFromJmap($jsContactCard);  // ORG + X-DEPARTMENT
                $adapter->setTitlesFromJmap($jsContactCard);        // TITLE / ROLE
                $adapter->setNotesFromJmap($jsContactCard);         // NOTE
                $adapter->setEmailsFromJmap($jsContactCard);        // EMAIL
                $adapter->setPhonesFromJmap($jsContactCard);        // TEL
                $adapter->setOnlineServicesFromJmap($jsContactCard);  // URL/IMPP + X-AIM/ICQ/MSN/YAHOO/JABBER/SKYPE
                $adapter->setPreferredLanguagesFromJmap($jsContactCard); // LANGUAGE
                $adapter->setMediaFromJmap($jsContactCard);         // PHOTO/LOGO/SOUND
                $adapter->setDirectoriesFromJmap($jsContactCard);   // SOURCE/ORG-DIRECTORY
                $adapter->setLinksFromJmap($jsContactCard);         // URL/CONTACT-URI
                $adapter->setCryptoKeysFromJmap($jsContactCard);    // KEY
                $adapter->setSchedulingAddressesFromJmap($jsContactCard); // CALADRURI
                $adapter->setCalendarsFromJmap($jsContactCard);
                $adapter->setAddressesFromJmap($jsContactCard);     // ADR
                $adapter->setAnniversariesFromJmap($jsContactCard); // BDAY/ANNIVERSARY + X-ANNIVERSARY
                $adapter->setRelatedToFromJmap($jsContactCard);     // RELATED + X-MANAGER/ASSISTANT/SPOUSE
                $adapter->setMembersFromJmap($jsContactCard);       // MEMBER
                $adapter->setKeywordsFromJmap($jsContactCard);      // CATEGORIES
                $adapter->setPersonalInfoFromJmap($jsContactCard);  // personal fields

                $backendContact = $adapter->getVCard();

                array_push($map, array($creationId => $backendContact));
            } catch (InvalidArgumentException $e) {
                $this->logger = Logger::getInstance();
                $this->logger->error($e->getMessage());

                // Add a null value to the key of $creationId. This null serves as an indicator in the data access class
                // to not perform any writing
                array_push($map, array($creationId => null));
            }
        }

        return $map;
    }

    /**
     * Map from vCard to JMAP ContactCard objects with Roundcube extensions.
     *
     * @param array<string,string> $data  contactId => vcardString
     * @param RoundcubeJSContactVCardAdapter $adapter
     *
     * @return ContactCard[]
     */
    public function mapToJmap($data, $adapter)
    {
        $list = [];

        foreach ($data as $contactId => $vCard) {
            $adapter->reset();

            // Support both plain vCard string and wrapped ["vCard" => ..., "oxpProperties" => ...] array
            $vCardPayload = is_array($vCard) && array_key_exists('vCard', $vCard)
                ? $vCard['vCard']
                : $vCard;

            // Try setting the vCard from the received String. If it cannot be parsed, add
            // more info to the thrown ParseException.
            try {
                $adapter->setVCard($vCardPayload);
            } catch (ParseException $e) {
                throw new ParseException(
                    $e->getMessage() . "\nNon-parseable vCard: $contactId",
                    $e->getCode(),
                    $e
                );
            }

            // If the vCard Object is set to null, skip the vCard in question. This should only
            // happen if the 'vCardParsing' config option is set to 'ignoreInvalidVCards'.
            if (is_null($adapter->getVCard())) {
                continue;
            }

            $jsContactCard = new ContactCard();

            if (
                is_array($vCard) &&
                array_key_exists("oxpProperties", $vCard) &&
                array_key_exists("addressBookId", $vCard["oxpProperties"])
            ) {
                $jsContactCard->setAddressBookIds($vCard["oxpProperties"]["addressBookId"]);
            }

            $jsContactCard->setAtType("Card");

            $jsContactCard->setUid($contactId);

            $adapter->getUidToJmap($jsContactCard);
            $adapter->getUpdatedToJmap($jsContactCard);
            $adapter->getKindToJmap($jsContactCard);
            $adapter->getGramGenderToJmap($jsContactCard);
            $adapter->getLanguageToJmap($jsContactCard);
            $adapter->getCreatedToJmap($jsContactCard);
            $adapter->getProdIdToJmap($jsContactCard);
            $adapter->getNameToJmap($jsContactCard);
            $adapter->getMaidenNameToJmap($jsContactCard);
            $adapter->getNicknameToJmap($jsContactCard);
            $adapter->getPronounsToJmap($jsContactCard);
            $adapter->getOrganizationToJmap($jsContactCard);
            $adapter->getTitlesToJmap($jsContactCard);
            $adapter->getNotesToJmap($jsContactCard);
            $adapter->getEmailsToJmap($jsContactCard);
            $adapter->getPhonesToJmap($jsContactCard);
            $adapter->getOnlineServicesToJmap($jsContactCard);
            $adapter->getMediaToJmap($jsContactCard);
            $adapter->getDirectoriesToJmap($jsContactCard);
            $adapter->getLinksToJmap($jsContactCard);
            $adapter->getCryptoKeysToJmap($jsContactCard);
            $adapter->getSchedulingAddressesToJmap($jsContactCard);
            $adapter->getCalendarsToJmap($jsContactCard);
            $adapter->getAddressesToJmap($jsContactCard);
            $adapter->getAnniversariesToJmap($jsContactCard);
            $adapter->getRelatedToToJmap($jsContactCard);
            $adapter->getMembersToJmap($jsContactCard);
            $adapter->getPreferredLanguagesToJmap($jsContactCard);
            $adapter->getKeywordsToJmap($jsContactCard);
            $adapter->getPersonalInfoToJmap($jsContactCard);

            // Map Roundcube-specific vCard properties to audriga-defined JSContact properties
            // Note: X-DEPARTMENT is currently mapped to "organizations"
            // See RoundcubeJSContactVCardAdapter's getOrganizations() method for more info

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
