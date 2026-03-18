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
     * @param array<string,ContactCard> $jmapData
     * @param RoundcubeJSContactVCardAdapter $adapter
     *
     * @return array<int,array<string,mixed>>
     */
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                $adapter->reset();

                $adapter->setAddressBookId($jsContactCard->getAddressBookIds()); // addressBookId(s)

                $adapter->setUid($jsContactCard);           // UID
                $adapter->setUpdated($jsContactCard);       // REV
                $adapter->setKind($jsContactCard);          // KIND
                $adapter->setGramGender($jsContactCard);    // X-GENDER + X-MAIDENNAME
                $adapter->setCreated($jsContactCard);       // CREATED
                $adapter->setProdId($jsContactCard);        // PRODID
                $adapter->setLanguage($jsContactCard);      // LANGUAGE
                $adapter->setName($jsContactCard);          // N
                $adapter->setFn($jsContactCard);            // FN
                $adapter->setNickname($jsContactCard);      // NICKNAME
                $adapter->setMaidenName($jsContactCard);    // X-MAIDENNAME
                $adapter->setPronouns($jsContactCard);      // PRONOUNS
                $adapter->setOrganizations($jsContactCard);  // ORG + X-DEPARTMENT
                $adapter->setTitles($jsContactCard);        // TITLE / ROLE
                $adapter->setNotes($jsContactCard);         // NOTE
                $adapter->setEmails($jsContactCard);        // EMAIL
                $adapter->setPhones($jsContactCard);        // TEL
                $adapter->setOnlineServices($jsContactCard);  // URL/IMPP + X-AIM/ICQ/MSN/YAHOO/JABBER/SKYPE
                $adapter->setPreferredLanguages($jsContactCard); // LANGUAGE
                $adapter->setMedia($jsContactCard);         // PHOTO/LOGO/SOUND
                $adapter->setDirectories($jsContactCard);   // SOURCE/ORG-DIRECTORY
                $adapter->setLinks($jsContactCard);         // URL/CONTACT-URI
                $adapter->setCryptoKeys($jsContactCard);    // KEY
                $adapter->setSchedulingAddresses($jsContactCard); // CALADRURI
                $adapter->setCalendars($jsContactCard);     // CALURI
                $adapter->setAddresses($jsContactCard);     // ADR
                $adapter->setAnniversaries($jsContactCard); // BDAY/ANNIVERSARY + X-ANNIVERSARY
                $adapter->setRelatedTo($jsContactCard);     // RELATED + X-MANAGER/ASSISTANT/SPOUSE
                $adapter->setMembers($jsContactCard);       // MEMBER
                $adapter->setKeywords($jsContactCard);      // CATEGORIES
                $adapter->setPersonalInfo($jsContactCard);  // personal fields

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
     * @param array<string,string> $data
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

            $adapter->getUid($jsContactCard);
            $adapter->getUpdated($jsContactCard);
            $adapter->getKind($jsContactCard);
            $adapter->getGramGender($jsContactCard);
            $adapter->getLanguage($jsContactCard);
            $adapter->getCreated($jsContactCard);
            $adapter->getProdId($jsContactCard);
            $adapter->getName($jsContactCard);
            $adapter->getMaidenName($jsContactCard);
            $adapter->getNickname($jsContactCard);
            $adapter->getPronouns($jsContactCard);
            $adapter->getOrganizations($jsContactCard);
            $adapter->getTitles($jsContactCard);
            $adapter->getNotes($jsContactCard);
            $adapter->getEmails($jsContactCard);
            $adapter->getPhones($jsContactCard);
            $adapter->getOnlineServices($jsContactCard);
            $adapter->getMedia($jsContactCard);
            $adapter->getDirectories($jsContactCard);
            $adapter->getLinks($jsContactCard);
            $adapter->getCryptoKeys($jsContactCard);
            $adapter->getSchedulingAddresses($jsContactCard);
            $adapter->getCalendars($jsContactCard);
            $adapter->getAddresses($jsContactCard);
            $adapter->getAnniversaries($jsContactCard);
            $adapter->getRelatedTo($jsContactCard);
            $adapter->getMembers($jsContactCard);
            $adapter->getPreferredLanguages($jsContactCard);
            $adapter->getKeywords($jsContactCard);
            $adapter->getPersonalInfo($jsContactCard);

            // Map Roundcube-specific vCard properties to audriga-defined JSContact properties
            // Note: X-DEPARTMENT is currently mapped to "organizations"
            // See RoundcubeJSContactVCardAdapter's getOrganizations() method for more info

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
