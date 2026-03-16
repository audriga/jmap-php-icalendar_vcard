<?php

namespace OpenXPort\Mapper;

use OpenXPort\Adapter\JSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;

class JSContactVCardMapper extends AbstractMapper
{
    /**
     * Map from JMAP ContactCard objects (RFC 9553)
     * to vCard data.
     *
     * @param array<string,ContactCard> $jmapData  creationId => ContactCard
     * @param JSContactVCardAdapter     $adapter
     *
     * @return array<int,array<string,mixed>>      [ [ creationId => vcardString ], ... ]
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
            $adapter->setAddressBookId($contactCard->getAddressBookIds()); // addressBookId(s)
            $adapter->setUidFromJmap($contactCard);           // UID
            $adapter->setUpdatedFromJmap($contactCard);       // REV
            $adapter->setKindFromJmap($contactCard);          // KIND (incl. group)
            $adapter->setGramGenderFromJmap($contactCard);    // GENDER from speakToAs
            $adapter->setCreatedFromJmap($contactCard);       // CREATED
            $adapter->setProdIdFromJmap($contactCard);        // PRODID
            $adapter->setLanguageFromJmap($contactCard);      // LANGUAGE
            $adapter->setNameFromJmap($contactCard);          // N
            $adapter->setFnFromJmap($contactCard);            // FN (displayname)
            $adapter->setNicknameFromJmap($contactCard);      // NICKNAME
            $adapter->setPronounsFromJmap($contactCard);      // PRONOUNS
            $adapter->setOrganizationFromJmap($contactCard);  // ORG
            $adapter->setTitlesFromJmap($contactCard);        // TITLE / ROLE
            $adapter->setNotesFromJmap($contactCard);         // NOTE
            $adapter->setEmailsFromJmap($contactCard);        // EMAIL
            $adapter->setPhonesFromJmap($contactCard);        // TEL
            $adapter->setOnlineFromJmap($contactCard);        // URL/IMPP/KEY/FBURL/CAL*
            $adapter->setPreferredLanguagesFromJmap($contactCard); // LANGUAGE
            $adapter->setMediaFromJmap($contactCard);         // PHOTO/LOGO/SOUND
            $adapter->setDirectoriesFromJmap($contactCard);   // SOURCE/ORG-DIRECTORY
            $adapter->setLinksFromJmap($contactCard);         // URL/CONTACT-URI
            $adapter->setCryptoKeysFromJmap($contactCard);    // KEY
            $adapter->setSchedulingAddressesFromJmap($contactCard); // CALADRURI
            $adapter->setAddressesFromJmap($contactCard);     // ADR (+ TZ)
            $adapter->setAnniversariesFromJmap($contactCard); // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
            $adapter->setRelatedToFromJmap($contactCard);     // RELATED
            $adapter->setMembersFromJmap($contactCard);       // MEMBER/KIND=group
            $adapter->setKeywordsFromJmap($contactCard);      // CATEGORIES
            $adapter->setPersonalInfoFromJmap($contactCard);  // personal fields

            $backendContact = $adapter->getContact();         // serialized vCard

            $result = array("vCard" => $backendContact);

            if ($contactCard->getAddressBookIds() !== null) {
                $result["oxpProperties"]["addressBookId"] = $contactCard->getAddressBookIds();
            }

            $map[] = array($creationId => $result);
        }

        return $map;
    }

    /**
     * Map from vCard data to JMAP ContactCard objects (RFC 9553).
     *
     * @param array<string,mixed>       $data      contactId => vcardString
     * @param JSContactVCardAdapter     $adapter
     *
     * @return ContactCard[]
     */
    public function mapToJmap($data, $adapter)
    {
        $list = array();

        foreach ($data as $contactId => $contactVCard) {
            $adapter->reset();

            // Support both plain vCard string and wrapped ["vCard" => ..., "oxpProperties" => ...] array
            $vCardPayload = is_array($contactVCard) && array_key_exists("vCard", $contactVCard)
                ? $contactVCard["vCard"]
                : $contactVCard;

            $adapter->setContact($vCardPayload);        // load vCard

            $contactCard = new ContactCard();
            $contactCard->setUid($contactId);
            $adapter->getUidToJmap($contactCard);             // UID
            $adapter->getUpdatedToJmap($contactCard);         // REV
            $adapter->getKindToJmap($contactCard);            // KIND
            $adapter->getGramGenderToJmap($contactCard);      // GENDER -> speakToAs
            $adapter->getLanguageToJmap($contactCard);        // LANGUAGE
            $adapter->getCreatedToJmap($contactCard);         // CREATED
            $adapter->getProdIdToJmap($contactCard);          // PRODID
            $adapter->getNameToJmap($contactCard);            // N
            $adapter->getNicknameToJmap($contactCard);        // NICKNAME
            $adapter->getPronounsToJmap($contactCard);        // PRONOUNS -> speakToAs.pronouns
            $adapter->getOrganizationToJmap($contactCard);    // ORG
            $adapter->getTitlesToJmap($contactCard);          // TITLE/ROLE
            $adapter->getNotesToJmap($contactCard);           // NOTE
            $adapter->getEmailsToJmap($contactCard);          // EMAIL
            $adapter->getPhonesToJmap($contactCard);          // TEL
            $adapter->getOnlineToJmap($contactCard);          // URL/IMPP/KEY/FBURL/CAL* -> onlineServices
            $adapter->getMediaToJmap($contactCard);           // PHOTO/LOGO/SOUND -> media
            $adapter->getDirectoriesToJmap($contactCard);     // SOURCE/ORG-DIRECTORY -> directories
            $adapter->getLinksToJmap($contactCard);           // URL/CONTACT-URI -> links
            $adapter->getCryptoKeysToJmap($contactCard);      // KEY -> cryptoKeys
            $adapter->getSchedulingAddressesToJmap($contactCard); // CALADRURI
            $adapter->getAddressesToJmap($contactCard);       // ADR/TZ -> addresses
            $adapter->getAnniversariesToJmap($contactCard);   // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
            $adapter->getRelatedToToJmap($contactCard);       // RELATED
            $adapter->getMembersToJmap($contactCard);         // MEMBER
            $adapter->getPreferredLanguagesToJmap($contactCard); // LANGUAGE with PREF/TYPE
            $adapter->getKeywordsToJmap($contactCard);        // CATEGORIES -> keywords
            $adapter->getPersonalInfoToJmap($contactCard);    // personal info bundle

            // Restore JMAP-specific fields that cannot be represented in vCard
            if (
                is_array($contactVCard) &&
                array_key_exists("oxpProperties", $contactVCard) &&
                array_key_exists("addressBookId", $contactVCard["oxpProperties"])
            ) {
                $contactCard->setAddressBookIds($contactVCard["oxpProperties"]["addressBookId"]);
            }

            $list[] = $contactCard;
        }

        return $list;
    }
}
