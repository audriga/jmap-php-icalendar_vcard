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

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                if (!($jsContactCard instanceof ContactCard)) {
                    $card = new ContactCard();
                    foreach (get_object_vars($jsContactCard) as $key => $value) {
                        $setter = 'set' . ucfirst($key);
                            $card->$setter($value);
                    }
                    $jsContactCard = $card;
                }
                // start with a clean vCard each time
                $adapter->reset();

                // Set addressBookId from the card
                $addressBookIds = $jsContactCard->getAddressBookIds();
                if (is_array($addressBookIds) && !empty($addressBookIds)) {
                    $adapter->setAddressBookId($addressBookIds[0]);
                }

                // Map all properties from JSContact to vCard
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

                // Get the serialized vCard and build result
                $backendContact = $adapter->getVCard();
                $result = array("vCard" => $backendContact);

                // Add addressBookId to oxpProperties
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

        foreach ($data as $contactId => $cHash) {
            $adapter->reset();

            // Handle vCard payload - either in array or direct
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
                $addressBookId = (string)$cHash["oxpProperties"]["addressBookId"];
                $jsContactCard->setAddressBookIds([$addressBookId => true]);
            }

            $jsContactCard->setAtType("Card");
            $jsContactCard->setUid($contactId);

            // Map all properties from vCard to JSContact
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

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
