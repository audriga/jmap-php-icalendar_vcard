<?php

namespace OpenXPort\Mapper;

use InvalidArgumentException;
use OpenXPort\Jmap\JSContact\Card;
use OpenXPort\Util\Logger;

class JSContactVCardMapper extends AbstractMapper
{
    protected $logger;

    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
                $adapter->reset();

                $adapter->setAddressBookId($jsContactCard->addressBookId);

                // online deprecated
                // TODO only onlineServices conversion implemented for newest spec
                if (!empty($jsContactCard->online)) {
                    $adapter->setSource($jsContactCard->online);
                    $adapter->setImpp($jsContactCard->online);
                    $adapter->setLogo($jsContactCard->online);
                    $adapter->setContactUri($jsContactCard->online);
                    $adapter->setOrgDirectory($jsContactCard->online);
                    $adapter->setSound($jsContactCard->online);
                    $adapter->setUrl($jsContactCard->online);
                    $adapter->setKey($jsContactCard->online);
                    $adapter->setFbUrl($jsContactCard->online);
                    $adapter->setCalAdrUri($jsContactCard->online);
                    $adapter->setCalUri($jsContactCard->online);
                }

                // onlineServices
                if (!empty($jsContactCard->onlineServices)) {
                    $adapter->setImppFromServices($jsContactCard->onlineServices);
                    $adapter->setSocialFromServices($jsContactCard->onlineServices);
                }

                $adapter->setKind($jsContactCard->kind);

                $adapter->setFN($jsContactCard->fullName);
                $adapter->setN($jsContactCard->name);
                $adapter->setNickname($jsContactCard->nickNames);

                $adapter->setPhoto($jsContactCard->photos);

                $adapter->setBDay($jsContactCard->anniversaries);
                $adapter->setBirthPlace($jsContactCard->anniversaries);
                $adapter->setDeathDate($jsContactCard->anniversaries);
                $adapter->setDeathPlace($jsContactCard->anniversaries);
                $adapter->setAnniversary($jsContactCard->anniversaries);

                $adapter->setGender($jsContactCard->speakToAs);

                $adapter->setADR($jsContactCard->addresses);
                $adapter->setTZ($jsContactCard->addresses);

                $adapter->setTel($jsContactCard->phones);

                $adapter->setEmail($jsContactCard->emails);

                $adapter->setLang($jsContactCard->preferredContactLanguages);

                $adapter->setTitle($jsContactCard->titles);

                $adapter->setOrg($jsContactCard->organizations);

                $adapter->setRelated($jsContactCard->relatedTo);

                $adapter->setExpertise($jsContactCard->personalInfo);
                $adapter->setHobby($jsContactCard->personalInfo);
                $adapter->setInterest($jsContactCard->personalInfo);

                $adapter->setCategories($jsContactCard->categories);

                $adapter->setNote($jsContactCard->notes);

                $adapter->setProdId($jsContactCard->prodId);

                $adapter->setRev($jsContactCard->updated);

                $adapter->deriveFN($jsContactCard->name);

                array_push($map, array($creationId => $adapter->getAsHash()));
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

    public function mapToJmap($data, $adapter)
    {
        $list = [];

        foreach ($data as $contactId => $cHash) {
            $adapter->reset();
            $adapter->setFromHash($cHash);

            $jsContactCard = new Card();

            if (
                array_key_exists("oxpProperties", $cHash) &&
                array_key_exists("addressBookId", $cHash["oxpProperties"])
            ) {
                $jsContactCard->setAddressBookId($cHash["oxpProperties"]["addressBookId"]);
            }

            $jsContactCard->setAtType("Card");
            $jsContactCard->setId($contactId);
            $jsContactCard->setOnline($adapter->getOnline());
            $jsContactCard->setOnlineServices($adapter->getOnlineServices());
            $jsContactCard->setKind($adapter->getKind());
            $jsContactCard->setFullName($adapter->getFullName());
            $jsContactCard->setName($adapter->getName());
            $jsContactCard->setNickNames($adapter->getNickNames());
            $jsContactCard->setPhotos($adapter->getPhotos());
            $jsContactCard->setAnniversaries($adapter->getAnniversaries());
            $jsContactCard->setSpeakToAs($adapter->getSpeakToAs());
            $jsContactCard->setAddresses($adapter->getAddresses());
            $jsContactCard->setPhones($adapter->getPhones());
            $jsContactCard->setEmails($adapter->getEmails());
            $jsContactCard->setPreferredContactLanguages($adapter->getPreferredContactLanguages());
            $jsContactCard->setTitles($adapter->getTitles());
            $jsContactCard->setOrganizations($adapter->getOrganizations());
            $jsContactCard->setRelatedTo($adapter->getRelatedTo());
            $jsContactCard->setPersonalInfo($adapter->getPersonalInfo());
            $jsContactCard->setCategories($adapter->getCategories());
            $jsContactCard->setNotes($adapter->getNotes());
            $jsContactCard->setProdId($adapter->getProdId());
            $jsContactCard->setUpdated($adapter->getUpdated());

            // Currently assume uid = id in OXP Core
            // WARNING: This will disregard UID from vCards
            // replace with the following to support UIDs:
            // $jsContactCard->setUid($adapter->getUid());
            $jsContactCard->setUid($contactId);

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
