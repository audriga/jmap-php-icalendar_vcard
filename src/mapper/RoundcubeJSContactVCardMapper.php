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

                $adapter->setAddressBookId($jsContactCard->getAddressBookIds());
                $adapter->setUid($jsContactCard);
                $adapter->setKind($jsContactCard);
                $adapter->setFn($jsContactCard);
                $adapter->setName($jsContactCard);
                $adapter->setNickname($jsContactCard);
                $adapter->setMedia($jsContactCard);
                $adapter->setAnniversaries($jsContactCard);
                $adapter->setGramGender($jsContactCard);
                $adapter->setPronouns($jsContactCard);
                $adapter->setAddresses($jsContactCard);
                $adapter->setPhones($jsContactCard);
                $adapter->setEmails($jsContactCard);
                $adapter->setPreferredLanguages($jsContactCard);
                $adapter->setTitles($jsContactCard);
                $adapter->setOrganizations($jsContactCard);
                $adapter->setRelatedTo($jsContactCard);
                $adapter->setPersonalInfo($jsContactCard);
                $adapter->setKeywords($jsContactCard);
                $adapter->setNotes($jsContactCard);
                $adapter->setProdId($jsContactCard);
                $adapter->setUpdated($jsContactCard);
                $adapter->setCreated($jsContactCard);
                $adapter->setLanguage($jsContactCard);
                $adapter->setOnlineServices($jsContactCard);
                $adapter->setDirectories($jsContactCard);
                $adapter->setLinks($jsContactCard);
                $adapter->setCryptoKeys($jsContactCard);
                $adapter->setSchedulingAddresses($jsContactCard);
                $adapter->setCalendars($jsContactCard);
                $adapter->setMembers($jsContactCard);
                $adapter->setMaidenName($jsContactCard);

                array_push($map, array($creationId => $adapter->getVCard()));
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

            // Try setting the vCard from the received String. If it cannot be parsed, add
            // more info to the thrown ParseException.
            try {
                $adapter->setVCard($vCard);
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

            $jsContactCard->setAtType("Card");
            $jsContactCard->setUid($contactId);

            $adapter->getUid($jsContactCard);
            $adapter->getKind($jsContactCard);
            $adapter->getName($jsContactCard);
            $adapter->getNickname($jsContactCard);
            $adapter->getMedia($jsContactCard);
            $adapter->getAnniversaries($jsContactCard);
            $adapter->getGramGender($jsContactCard);
            $adapter->getPronouns($jsContactCard);
            $adapter->getAddresses($jsContactCard);
            $adapter->getPhones($jsContactCard);
            $adapter->getEmails($jsContactCard);
            $adapter->getPreferredLanguages($jsContactCard);
            $adapter->getTitles($jsContactCard);
            $adapter->getOrganizations($jsContactCard);
            $adapter->getRelatedTo($jsContactCard);
            $adapter->getPersonalInfo($jsContactCard);
            $adapter->getKeywords($jsContactCard);
            $adapter->getNotes($jsContactCard);
            $adapter->getProdId($jsContactCard);
            $adapter->getUpdated($jsContactCard);
            $adapter->getCreated($jsContactCard);
            $adapter->getLanguage($jsContactCard);
            $adapter->getOnlineServices($jsContactCard);
            $adapter->getDirectories($jsContactCard);
            $adapter->getLinks($jsContactCard);
            $adapter->getCryptoKeys($jsContactCard);
            $adapter->getSchedulingAddresses($jsContactCard);
            $adapter->getCalendars($jsContactCard);
            $adapter->getMembers($jsContactCard);
            $adapter->getMaidenName($jsContactCard);

            // Map Roundcube-specific vCard properties to audriga-defined JSContact properties
            // Note: X-DEPARTMENT is currently mapped to "organizations"
            // See RoundcubeJSContactVCardAdapter's getOrganizations() method for more info

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
