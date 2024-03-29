<?php

namespace OpenXPort\Mapper;

use InvalidArgumentException;
use OpenXPort\Jmap\JSContact\Audriga\Card;
use OpenXPort\Util\Logger;
use Sabre\VObject\ParseException;

class RoundcubeJSContactVCardMapper extends JSContactVCardMapper
{
    public function mapFromJmap($jmapData, $adapter)
    {
        $map = [];

        foreach ($jmapData as $creationId => $jsContactCard) {
            try {
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
                $adapter->setXAim($jsContactCard->online);
                $adapter->setXIcq($jsContactCard->online);
                $adapter->setXMsn($jsContactCard->online);
                $adapter->setXYahoo($jsContactCard->online);
                $adapter->setXJabber($jsContactCard->online);
                $adapter->setXSkypeUsername($jsContactCard->online);

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
                $adapter->setXAnniversary($jsContactCard->anniversaries);

                $adapter->setXGender($jsContactCard->speakToAs);

                $adapter->setADR($jsContactCard->addresses);
                $adapter->setTZ($jsContactCard->addresses);

                $adapter->setTel($jsContactCard->phones);

                $adapter->setEmail($jsContactCard->emails);

                $adapter->setLang($jsContactCard->preferredContactLanguages);

                $adapter->setTitle($jsContactCard->titles);

                $adapter->setOrg($jsContactCard->organizations);

                $adapter->setRelated($jsContactCard->relatedTo);
                $adapter->setXManager($jsContactCard->relatedTo);
                $adapter->setXAssistant($jsContactCard->relatedTo);
                $adapter->setXSpouse($jsContactCard->relatedTo);

                $adapter->setExpertise($jsContactCard->personalInfo);
                $adapter->setHobby($jsContactCard->personalInfo);
                $adapter->setInterest($jsContactCard->personalInfo);

                $adapter->setCategories($jsContactCard->categories);

                $adapter->setNote($jsContactCard->notes);

                $adapter->setProdId($jsContactCard->prodId);

                $adapter->setRev($jsContactCard->updated);

                $adapter->setXMaidenName($jsContactCard->{"audriga.eu/roundcube:maidenName"});

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

    public function mapToJmap($data, $adapter)
    {
        $list = [];

        foreach ($data as $contactId => $vCard) {
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

            $jsContactCard = new Card();
            $jsContactCard->setAtType("Card");
            $jsContactCard->setId($contactId);
            $jsContactCard->setOnline($adapter->getOnline());
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

            // Map Roundcube-specific vCard properties to audriga-defined JSContact properties
            // Note: X-DEPARTMENT is currently mapped to "organizations"
            // See RoundcubeJSContactVCardAdapter's getOrganizations() method for more info
            $jsContactCard->setMaidenName($adapter->getMaidenName());

            array_push($list, $jsContactCard);
        }

        return $list;
    }
}
