<?php

namespace OpenXPort\Mapper;

use InvalidArgumentException;
use OpenXPort\Adapter\JSContactVCardAdapter;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Util\Logger;

class JSContactVCardMapper extends AbstractMapper
{
    protected $logger;

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

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                // start with a clean vCard each time
                $adapter->reset();

                $adapter->setAddressBookId($jsContactCard->getAddressBookIds()); // addressBookId(s)

                $adapter->setUidFromJmap($jsContactCard);           // UID
                $adapter->setUpdatedFromJmap($jsContactCard);       // REV
                $adapter->setKindFromJmap($jsContactCard);          // KIND (incl. group)
                $adapter->setGramGenderFromJmap($jsContactCard);    // GENDER from speakToAs
                $adapter->setCreatedFromJmap($jsContactCard);       // CREATED
                $adapter->setProdIdFromJmap($jsContactCard);        // PRODID
                $adapter->setLanguageFromJmap($jsContactCard);      // LANGUAGE
                $adapter->setNameFromJmap($jsContactCard);          // N
                $adapter->setFnFromJmap($jsContactCard);            // FN (displayname)
                $adapter->setNicknameFromJmap($jsContactCard);      // NICKNAME
                $adapter->setPronounsFromJmap($jsContactCard);      // PRONOUNS
                $adapter->setOrganizationFromJmap($jsContactCard);  // ORG
                $adapter->setTitlesFromJmap($jsContactCard);        // TITLE / ROLE
                $adapter->setNotesFromJmap($jsContactCard);         // NOTE
                $adapter->setEmailsFromJmap($jsContactCard);        // EMAIL
                $adapter->setPhonesFromJmap($jsContactCard);        // TEL
                $adapter->setOnlineServicesFromJmap($jsContactCard);        // URL/IMPP/KEY/FBURL/CAL*
                $adapter->setPreferredLanguagesFromJmap($jsContactCard); // LANGUAGE
                $adapter->setMediaFromJmap($jsContactCard);         // PHOTO/LOGO/SOUND
                $adapter->setDirectoriesFromJmap($jsContactCard);   // SOURCE/ORG-DIRECTORY
                $adapter->setLinksFromJmap($jsContactCard);         // URL/CONTACT-URI
                $adapter->setCryptoKeysFromJmap($jsContactCard);    // KEY
                $adapter->setSchedulingAddressesFromJmap($jsContactCard); // CALADRURI
                $adapter->setCalendarsFromJmap($jsContactCard);      // CALENDAR
                $adapter->setAddressesFromJmap($jsContactCard);     // ADR (+ TZ)
                $adapter->setAnniversariesFromJmap($jsContactCard); // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
                $adapter->setRelatedToFromJmap($jsContactCard);     // RELATED
                $adapter->setMembersFromJmap($jsContactCard);       // MEMBER/KIND=group
                $adapter->setKeywordsFromJmap($jsContactCard);      // CATEGORIES
                $adapter->setPersonalInfoFromJmap($jsContactCard);  // personal fields

                $backendContact = $adapter->getVCard();           // serialized vCard

                $result = array("vCard" => $backendContact);

                if ($jsContactCard->getAddressBookIds() !== null) {
                    $result["oxpProperties"]["addressBookId"] = $jsContactCard->getAddressBookIds();
                }

                array_push($map, array($creationId => $result));
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

        foreach ($data as $contactId => $cHash) {
            $adapter->reset();

            // Support both plain vCard string and wrapped ["vCard" => ..., "oxpProperties" => ...] array
            $vCardPayload = is_array($cHash) && array_key_exists("vCard", $cHash)
                ? $cHash["vCard"]
                : $cHash;

            $adapter->setVCard($vCardPayload);        // load vCard

            $jsContactCard = new ContactCard();

            if (
                is_array($cHash) &&
                array_key_exists("oxpProperties", $cHash) &&
                array_key_exists("addressBookId", $cHash["oxpProperties"])
            ) {
                $jsContactCard->setAddressBookIds($cHash["oxpProperties"]["addressBookId"]);
            }

            $jsContactCard->setAtType("Card");

            $jsContactCard->setUid($contactId);

            $adapter->getUidToJmap($jsContactCard);             // UID
            $adapter->getUpdatedToJmap($jsContactCard);         // REV
            $adapter->getKindToJmap($jsContactCard);            // KIND
            $adapter->getGramGenderToJmap($jsContactCard);      // GENDER -> speakToAs
            $adapter->getLanguageToJmap($jsContactCard);        // LANGUAGE
            $adapter->getCreatedToJmap($jsContactCard);         // CREATED
            $adapter->getProdIdToJmap($jsContactCard);          // PRODID
            $adapter->getNameToJmap($jsContactCard);            // N
            $adapter->getNicknameToJmap($jsContactCard);        // NICKNAME
            $adapter->getPronounsToJmap($jsContactCard);        // PRONOUNS -> speakToAs.pronouns
            $adapter->getOrganizationToJmap($jsContactCard);    // ORG
            $adapter->getTitlesToJmap($jsContactCard);          // TITLE/ROLE
            $adapter->getNotesToJmap($jsContactCard);           // NOTE
            $adapter->getEmailsToJmap($jsContactCard);          // EMAIL
            $adapter->getPhonesToJmap($jsContactCard);          // TEL
            $adapter->getOnlineServicesToJmap($jsContactCard);  // URL/IMPP/KEY/FBURL/CAL* -> onlineServices
            $adapter->getMediaToJmap($jsContactCard);           // PHOTO/LOGO/SOUND -> media
            $adapter->getDirectoriesToJmap($jsContactCard);     // SOURCE/ORG-DIRECTORY -> directories
            $adapter->getLinksToJmap($jsContactCard);           // URL/CONTACT-URI -> links
            $adapter->getCryptoKeysToJmap($jsContactCard);      // KEY -> cryptoKeys
            $adapter->getSchedulingAddressesToJmap($jsContactCard); // CALADRURI
            $adapter->getCalendarsToJmap($jsContactCard);       // CALENDAR -> calendars
            $adapter->getAddressesToJmap($jsContactCard);       // ADR/TZ -> addresses
            $adapter->getAnniversariesToJmap($jsContactCard);   // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
            $adapter->getRelatedToToJmap($jsContactCard);       // RELATED
            $adapter->getMembersToJmap($jsContactCard);         // MEMBER
            $adapter->getPreferredLanguagesToJmap($jsContactCard); // LANGUAGE with PREF/TYPE
            $adapter->getKeywordsToJmap($jsContactCard);        // CATEGORIES -> keywords
            $adapter->getPersonalInfoToJmap($jsContactCard);    // personal info bundle

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
