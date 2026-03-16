<?php

namespace OpenXPort\Mapper;

use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Adapter\RoundcubeJSContactVCardAdapter;

/**
 * Roundcube-specific mapper for RFC 9553 ContactCard <-> vCard conversion.
 *
 * Extends VCardToContactCardMapper to handle Roundcube's custom X-properties:
 * - X-MAIDENNAME (maiden name)
 * - X-ANNIVERSARY (anniversary date)
 * - X-GENDER (grammatical gender)
 * - X-DEPARTMENT (organization units)
 * - X-AIM, X-ICQ, X-MSN, X-YAHOO, X-JABBER, X-SKYPE-USERNAME (instant messaging)
 * - X-MANAGER, X-ASSISTANT, X-SPOUSE (relations)
 *
 * These properties are preserved during round-trip conversion via custom property
 * storage in the JSContact Card using the "audriga.eu/roundcube:" namespace.
 */
class RoundcubeJSContactVCardMapper extends JSContactVCardMapper
{
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
        $map = array();

        foreach ($jmapData as $creationId => $contactCard) {
            if (!($contactCard instanceof ContactCard)) {
                continue;
            }

            // start with a clean vCard each time
            $adapter->reset();

            // metadata / core
            $adapter->setUidFromJmap($contactCard);           // UID
            $adapter->setUpdatedFromJmap($contactCard);       // REV
            $adapter->setKindFromJmap($contactCard);          // KIND
            $adapter->setGramGenderFromJmap($contactCard);    // X-GENDER + X-MAIDENNAME
            $adapter->setCreatedFromJmap($contactCard);       // CREATED
            $adapter->setProdIdFromJmap($contactCard);        // PRODID
            $adapter->setLanguageFromJmap($contactCard);      // LANGUAGE

            // names
            $adapter->setNameFromJmap($contactCard);          // N
            $adapter->setFnFromJmap($contactCard);            // FN
            $adapter->setNicknameFromJmap($contactCard);      // NICKNAME
            $adapter->setMaidenNameFromJmap($contactCard);    // X-MAIDENNAME

            // Speaking properties (RFC 9554 extensions)
            $adapter->setPronounsFromJmap($contactCard);      // PRONOUNS

            // org / titles / notes
            $adapter->setOrganizationFromJmap($contactCard);  // ORG + X-DEPARTMENT
            $adapter->setTitlesFromJmap($contactCard);        // TITLE / ROLE
            $adapter->setNotesFromJmap($contactCard);         // NOTE

            // comms
            $adapter->setEmailsFromJmap($contactCard);        // EMAIL
            $adapter->setPhonesFromJmap($contactCard);        // TEL
            $adapter->setOnlineFromJmap($contactCard);        // URL/IMPP + X-AIM/ICQ/MSN/YAHOO/JABBER/SKYPE
            $adapter->setPreferredLanguagesFromJmap($contactCard); // LANGUAGE

            // Media, directories, links, security, calendar
            $adapter->setMediaFromJmap($contactCard);         // PHOTO/LOGO/SOUND
            $adapter->setDirectoriesFromJmap($contactCard);   // SOURCE/ORG-DIRECTORY
            $adapter->setLinksFromJmap($contactCard);         // URL/CONTACT-URI
            $adapter->setCryptoKeysFromJmap($contactCard);    // KEY
            $adapter->setSchedulingAddressesFromJmap($contactCard); // CALADRURI

            // addresses / anniversaries
            $adapter->setAddressesFromJmap($contactCard);     // ADR
            $adapter->setAnniversariesFromJmap($contactCard); // BDAY/ANNIVERSARY + X-ANNIVERSARY

            // relations, groups
            $adapter->setRelatedToFromJmap($contactCard);     // RELATED + X-MANAGER/ASSISTANT/SPOUSE
            $adapter->setMembersFromJmap($contactCard);       // MEMBER

            // language, keywords, personal info
            $adapter->setKeywordsFromJmap($contactCard);      // CATEGORIES
            $adapter->setPersonalInfoFromJmap($contactCard);  // personal fields

            $backendContact = $adapter->getContact();
            $map[] = array($creationId => $backendContact);
        }

        return $map;
    }

    /**
     * Map from vCard to JMAP ContactCard objects with Roundcube extensions.
     *
     * Extracts both standard RFC 9553 properties and Roundcube-specific X-properties,
     * storing them in the ContactCard using the "audriga.eu/roundcube:" custom property namespace.
     *
     * @param array<string,string> $data  contactId => vcardString
     * @param RoundcubeJSContactVCardAdapter $adapter
     *
     * @return ContactCard[]
     */
    /**
 * @param array<string,string|array<string,mixed>> $data
 * @param RoundcubeJSContactVCardAdapter $adapter
 *
 * @return ContactCard[]
 */
    public function mapToJmap($data, $adapter)
    {
        $list = array();

        foreach ($data as $contactId => $contactVCard) {
            $adapter->reset();

            $vCardPayload = is_array($contactVCard) && array_key_exists('vCard', $contactVCard)
            ? $contactVCard['vCard']
            : $contactVCard;

            try {
                $adapter->setContact($vCardPayload);
            } catch (\Sabre\VObject\ParseException $e) {
                throw new \Sabre\VObject\ParseException(
                    $e->getMessage() . "\nNon-parseable vCard: $contactId",
                    $e->getCode(),
                    $e
                );
            }

            if (is_null($adapter->getVCard())) {
                continue;
            }

            $contactCard = new ContactCard();
            $contactCard->setUid($contactId);

            $adapter->getUidToJmap($contactCard);
            $adapter->getUpdatedToJmap($contactCard);
            $adapter->getKindToJmap($contactCard);
            $adapter->getGramGenderToJmap($contactCard);
            $adapter->getLanguageToJmap($contactCard);
            $adapter->getCreatedToJmap($contactCard);
            $adapter->getProdIdToJmap($contactCard);
            $adapter->getNameToJmap($contactCard);
            $adapter->getMaidenNameToJmap($contactCard);
            $adapter->getNicknameToJmap($contactCard);
            $adapter->getPronounsToJmap($contactCard);
            $adapter->getOrganizationToJmap($contactCard);
            $adapter->getTitlesToJmap($contactCard);
            $adapter->getNotesToJmap($contactCard);
            $adapter->getEmailsToJmap($contactCard);
            $adapter->getPhonesToJmap($contactCard);
            $adapter->getOnlineToJmap($contactCard);
            $adapter->getMediaToJmap($contactCard);
            $adapter->getDirectoriesToJmap($contactCard);
            $adapter->getLinksToJmap($contactCard);
            $adapter->getCryptoKeysToJmap($contactCard);
            $adapter->getSchedulingAddressesToJmap($contactCard);
            $adapter->getAddressesToJmap($contactCard);
            $adapter->getAnniversariesToJmap($contactCard);
            $adapter->getRelatedToToJmap($contactCard);
            $adapter->getMembersToJmap($contactCard);
            $adapter->getPreferredLanguagesToJmap($contactCard);
            $adapter->getKeywordsToJmap($contactCard);
            $adapter->getPersonalInfoToJmap($contactCard);

            $list[] = $contactCard;
        }

        return $list;
    }
}
