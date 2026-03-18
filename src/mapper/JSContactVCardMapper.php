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
     * https://datatracker.ietf.org/doc/rfc9555/
     *
     * @param array<string,ContactCard> $jmapData
     * @param JSContactVCardAdapter     $adapter
     *
     * @return array<int,array<string,mixed>>
     */
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];
        ;

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                // start with a clean vCard each time
                $adapter->reset();

                $addressBookIds = $jsContactCard->getAddressBookIds();
                if (is_array($addressBookIds) && !empty($addressBookIds)) {
                    $adapter->setAddressBookId($addressBookIds[0]);
                }

                $adapter->setUid($jsContactCard);           // UID
                $adapter->setUpdated($jsContactCard);       // REV
                $adapter->setKind($jsContactCard);          // KIND (incl. group)
                $adapter->setGramGender($jsContactCard);    // GENDER from speakToAs
                $adapter->setCreated($jsContactCard);       // CREATED
                $adapter->setProdId($jsContactCard);        // PRODID
                $adapter->setLanguage($jsContactCard);      // LANGUAGE
                $adapter->setName($jsContactCard);          // N
                $adapter->setFn($jsContactCard);            // FN (displayname)
                $adapter->setNickname($jsContactCard);      // NICKNAME
                $adapter->setPronouns($jsContactCard);      // PRONOUNS
                $adapter->setOrganizations($jsContactCard);  // ORG
                $adapter->setTitles($jsContactCard);        // TITLE / ROLE
                $adapter->setNotes($jsContactCard);         // NOTE
                $adapter->setEmails($jsContactCard);        // EMAIL
                $adapter->setPhones($jsContactCard);        // TEL
                $adapter->setOnlineServices($jsContactCard);        // URL/IMPP/KEY/FBURL/CAL*
                $adapter->setPreferredLanguages($jsContactCard); // LANGUAGE
                $adapter->setMedia($jsContactCard);         // PHOTO/LOGO/SOUND
                $adapter->setDirectories($jsContactCard);   // SOURCE/ORG-DIRECTORY
                $adapter->setLinks($jsContactCard);         // URL/CONTACT-URI
                $adapter->setCryptoKeys($jsContactCard);    // KEY
                $adapter->setSchedulingAddresses($jsContactCard); // CALADRURI
                $adapter->setCalendars($jsContactCard);      // CALENDAR
                $adapter->setAddresses($jsContactCard);     // ADR (+ TZ)
                $adapter->setAnniversaries($jsContactCard); // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
                $adapter->setRelatedTo($jsContactCard);     // RELATED
                $adapter->setMembers($jsContactCard);       // MEMBER/KIND=group
                $adapter->setKeywords($jsContactCard);      // CATEGORIES
                $adapter->setPersonalInfo($jsContactCard);  // personal fields

                $backendContact = $adapter->getVCard();           // serialized vCard

                $result = array("vCard" => $backendContact);

                $addressBookIds = $jsContactCard->getAddressBookIds();
                if (is_array($addressBookIds) && !empty($addressBookIds)) {
                    $result["oxpProperties"]["addressBookId"] = $addressBookIds[0];
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
     * @param array<string,mixed>       $data
     * @param JSContactVCardAdapter     $adapter
     *
     * @return ContactCard[]
     */
    public function mapToJmap($data, $adapter)
    {
        $list = [];
        ;

        foreach ($data as $contactId => $cHash) {
            $adapter->reset();

            $vCardPayload = is_array($cHash) && array_key_exists("vCard", $cHash)
                ? $cHash["vCard"]
                : $cHash;
            $adapter->setVCard($vCardPayload);

            $jsContactCard = new ContactCard();


            // Handle oxpProperties if present
            if (
                is_array($cHash) &&
                array_key_exists("oxpProperties", $cHash) &&
                array_key_exists("addressBookId", $cHash["oxpProperties"])
            ) {
                $jsContactCard->setAddressBookIds([$cHash["oxpProperties"]["addressBookId"]]);
            }

            $jsContactCard->setAtType("Card");

            $jsContactCard->setUid($contactId);

            $adapter->getUid($jsContactCard);             // UID
            $adapter->getUpdated($jsContactCard);         // REV
            $adapter->getKind($jsContactCard);            // KIND
            $adapter->getGramGender($jsContactCard);      // GENDER -> speakToAs
            $adapter->getLanguage($jsContactCard);        // LANGUAGE
            $adapter->getCreated($jsContactCard);         // CREATED
            $adapter->getProdId($jsContactCard);          // PRODID
            $adapter->getName($jsContactCard);            // N
            $adapter->getNickname($jsContactCard);        // NICKNAME
            $adapter->getPronouns($jsContactCard);        // PRONOUNS -> speakToAs.pronouns
            $adapter->getOrganizations($jsContactCard);    // ORG
            $adapter->getTitles($jsContactCard);          // TITLE/ROLE
            $adapter->getNotes($jsContactCard);           // NOTE
            $adapter->getEmails($jsContactCard);          // EMAIL
            $adapter->getPhones($jsContactCard);          // TEL
            $adapter->getOnlineServices($jsContactCard);  // URL/IMPP/KEY/FBURL/CAL* -> onlineServices
            $adapter->getMedia($jsContactCard);           // PHOTO/LOGO/SOUND -> media
            $adapter->getDirectories($jsContactCard);     // SOURCE/ORG-DIRECTORY -> directories
            $adapter->getLinks($jsContactCard);           // URL/CONTACT-URI -> links
            $adapter->getCryptoKeys($jsContactCard);      // KEY -> cryptoKeys
            $adapter->getSchedulingAddresses($jsContactCard); // CALADRURI
            $adapter->getCalendars($jsContactCard);       // CALENDAR -> calendars
            $adapter->getAddresses($jsContactCard);       // ADR/TZ -> addresses
            $adapter->getAnniversaries($jsContactCard);   // BDAY/BIRTHPLACE/DEATH*/ANNIVERSARY
            $adapter->getRelatedTo($jsContactCard);       // RELATED
            $adapter->getMembers($jsContactCard);         // MEMBER
            $adapter->getPreferredLanguages($jsContactCard); // LANGUAGE with PREF/TYPE
            $adapter->getKeywords($jsContactCard);        // CATEGORIES -> keywords
            $adapter->getPersonalInfo($jsContactCard);    // personal info bundle

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
