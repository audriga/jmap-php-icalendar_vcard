<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\OrgUnit;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Jmap\JSContact\Note;
use OpenXPort\Util\AdapterUtil;

/**
 * Converts Roundcube webmail contacts between vCard and JSContact formats.
 *
 * This adapter handles the special contact fields that Roundcube webmail uses but aren't
 * part of the standard vCard format. It makes sure these extra fields don't get lost
 * when converting contacts back and forth between vCard and JSContact.
 */
class RoundcubeJSContactVCardAdapter extends JSContactVCardAdapter
{
    // vCard -> JSContact
    /**
     * Runs the full vCard -> JSContact conversion, then handles maiden name
     * separately since it has no standard property match in the parent class.
     */

    /**
     * Reads X-MAIDENNAME from the vCard and stores it on the ContactCard.
     */
    public function getMaidenNameToJmap($card)
    {
        $xMaidenName = $this->vcard->__get("X-MAIDENNAME");
        if (AdapterUtil::isSetAndNotNull($xMaidenName)) {
            $value = trim((string)$xMaidenName);
            if ($value !== "") {
                $card->setProperty("audriga.eu/roundcube:maidenName", $value);
            }
        }
    }

    /**
     * Reads X-GENDER from the vCard and stores "male" or "female" on the ContactCard.
     * Falls back to the standard GRAMGENDER handling for any other values.
     */
    public function getGramGenderToJmap($card)
    {
        $xGender = $this->vcard->__get("X-GENDER");
        if (AdapterUtil::isSetAndNotNull($xGender)) {
            $value = trim((string)$xGender);

            if ($value !== "") {
                $normalizedValue = strtolower($value);

                if ($normalizedValue === "male" || $normalizedValue === "female") {
                    $speakToAs = $card->getSpeakToAs();
                    if (!$speakToAs) {
                        $speakToAs = new SpeakToAs();
                    }
                    $speakToAs->setGrammaticalGender($normalizedValue);
                    $card->setSpeakToAs($speakToAs);
                    return;
                }
            }
        }

        parent::getGramGenderToJmap($card);
    }

    /**
     * Reads TEL properties from the vCard, with extra handling for Roundcube-specific
     * types like home2, work2, homefax, and workfax.
     */
    public function getPhonesToJmap($card)
    {
        $telProperties = $this->vcard->TEL;
        if (!AdapterUtil::isSetAndNotNull($telProperties) || empty($telProperties)) {
            return;
        }

        $phones = [];
        $index = 1;

        foreach ($telProperties as $telProperty) {
            $number = trim((string)$telProperty);
            if ($number === '') {
                continue;
            }

            $types = $this->extractPropertyTypes($telProperty);
            $roundcubeTypes = array_intersect($types, ['home2', 'work2', 'homefax', 'workfax']);

            if (!empty($roundcubeTypes)) {
                foreach ($roundcubeTypes as $rcType) {
                    $phone = new Phone();
                    $phone->setNumber($number);

                    if ($rcType === 'homefax' || $rcType === 'workfax') {
                        $phone->setFeatures(['fax' => true]);
                    }

                    if ($rcType === 'home2' || $rcType === 'homefax') {
                        $phone->setContexts(['private' => true]);
                    } elseif ($rcType === 'work2' || $rcType === 'workfax') {
                        $phone->setContexts(['work' => true]);
                    }

                    $pref = $this->vcardPrefParamToInt($telProperty);
                    if ($pref !== null) {
                        $phone->setPref($pref);
                    }

                    $phone->setLabel($rcType);
                    $phones['p' . $index++] = $phone;
                }
                continue;
            }

            $phone = new Phone();
            $phone->setNumber($number);

            $contexts = $this->vcardTypeParamToContexts($telProperty);
            if (!empty($contexts)) {
                $phone->setContexts($contexts);
            }

            $features = [];
            $unknownTypes = [];

            foreach ($types as $type) {
                if ($type === '' || $type === 'home' || $type === 'work' || $type === 'private') {
                    continue;
                }

                if ($type === 'cell') {
                    $features['mobile'] = true;
                } elseif (in_array($type, ['mobile', 'voice', 'text', 'video', 'main-number', 'textphone', 'fax', 'pager'], true)) {
                    $features[$type] = true;
                } else {
                    $unknownTypes[] = $type;
                }
            }

            if (!empty($features)) {
                $phone->setFeatures($features);
            }

            if (!empty($unknownTypes)) {
                $phone->setLabel(implode(', ', $unknownTypes));
            }

            $pref = $this->vcardPrefParamToInt($telProperty);
            if ($pref !== null) {
                $phone->setPref($pref);
            }

            $phones['p' . $index++] = $phone;
        }

        if (!empty($phones)) {
            $card->setPhones($phones);
        }
    }

    /**
     * Reads standard online services, then also picks up Roundcube's proprietary
     * IM fields (X-AIM, X-ICQ, X-MSN, X-YAHOO, X-JABBER, X-SKYPE-USERNAME).
     */
    public function getOnlineToJmap($card)
    {
        parent::getOnlineToJmap($card);

        $services = $card->getOnlineServices() ?: [];
        $index = count($services) + 1;

        $this->readRoundcubeIM("X-AIM", "aim", $services, $index);
        $this->readRoundcubeIM("X-ICQ", "icq", $services, $index);
        $this->readRoundcubeIM("X-MSN", "msn", $services, $index);
        $this->readRoundcubeIM("X-YAHOO", "yahoo", $services, $index);
        $this->readRoundcubeIM("X-JABBER", "jabber", $services, $index);
        $this->readRoundcubeIM("X-SKYPE-USERNAME", "skype", $services, $index);

        if (!empty($services)) {
            $card->setOnlineServices($services);
        }
    }

    /**
     * Reads a single Roundcube X-property IM field and appends it to the services list.
     */
    private function readRoundcubeIM($propertyName, $serviceName, array &$services, &$index)
    {
        $properties = $this->vcard->__get($propertyName);
        if (!AdapterUtil::isSetAndNotNull($properties)) {
            return;
        }

        foreach ($properties as $property) {
            $value = trim((string)$property);
            if ($value === "") {
                continue;
            }

            $service = new OnlineService();
            $service->setService($serviceName);
            if (in_array($serviceName, ['skype', 'icq', 'msn', 'yahoo'], true)) {
                $service->setUser($value);
            } else {
                $service->setUri($value);
            }
            $service->setLabel($propertyName);

            $contexts = $this->vcardTypeParamToContexts($property);
            if (!empty($contexts)) {
                $service->setContexts($contexts);
            }

            $pref = $this->vcardPrefParamToInt($property);
            if ($pref !== null) {
                $service->setPref($pref);
            }

            $services['os' . $index++] = $service;
        }
    }

    /**
     * Reads standard RELATED properties, then also picks up Roundcube's X-MANAGER,
     * X-ASSISTANT, and X-SPOUSE fields.
     */
    public function getRelatedToToJmap($card)
    {
        parent::getRelatedToToJmap($card);

        $relations = $card->getRelatedTo() ?: [];

        $this->readRelatedX("X-MANAGER", "manager", $relations);
        $this->readRelatedX("X-ASSISTANT", "assistant", $relations);
        $this->readRelatedX("X-SPOUSE", "spouse", $relations);

        if (!empty($relations)) {
            $card->setRelatedTo($relations);
        }
    }

    /**
     * Reads a single Roundcube X-relation field and adds it to the relations map
     * under the given relation type.
     */
    private function readRelatedX($propertyName, $relationType, array &$relations)
    {
        $properties = $this->vcard->__get($propertyName);
        if (!AdapterUtil::isSetAndNotNull($properties)) {
            return;
        }

        foreach ($properties as $property) {
            $uid = trim((string)$property);
            if ($uid === "") {
                continue;
            }

            if (!isset($relations[$uid])) {
                $relations[$uid] = new Relation();
            }

            $relation = $relations[$uid];
            $types = $relation->getRelation() ?: [];
            $types[$relationType] = true;
            $relation->setRelation($types);
        }
    }

    /**
     * Reads standard anniversaries, then also picks up the Roundcube X-ANNIVERSARY field.
     */
    public function getAnniversariesToJmap($card)
    {
        parent::getAnniversariesToJmap($card);

        $xAnniversary = $this->vcard->__get("X-ANNIVERSARY");
        if (!AdapterUtil::isSetAndNotNull($xAnniversary)) {
            return;
        }

        $anniversaries = $card->getAnniversaries() ?: [];

        foreach ($xAnniversary as $prop) {
            $value = trim((string) $prop);
            if ($value === "") {
                continue;
            }

            $date = AdapterUtil::parseDateTime($value, 'Y-m-d', 'Y-m-d', 'Ymd');
            if ($date === null) {
                continue;
            }

            $alreadyExists = false;
            foreach ($anniversaries as $existing) {
                if (!($existing instanceof Anniversary)) {
                    continue;
                }

                if (
                    strtolower((string) $existing->getLabel()) === 'x-anniversary'
                    && $existing->getDate() === $date
                ) {
                    $alreadyExists = true;
                    break;
                }
            }

            if ($alreadyExists) {
                continue;
            }

            $anniversary = new Anniversary();
            $anniversary->setKind('other');
            $anniversary->setLabel('x-anniversary');
            $anniversary->setDate($date);

            $anniversaries[] = $anniversary;
        }

        if (!empty($anniversaries)) {
            $card->setAnniversaries($anniversaries);
        }
    }


    /**
     * Reads standard ORG properties, then merges in any X-DEPARTMENT values
     */
    public function getOrganizationToJmap($card)
    {
        parent::getOrganizationToJmap($card);

        $departments = $this->vcard->__get("X-DEPARTMENT");
        if (!AdapterUtil::isSetAndNotNull($departments)) {
            return;
        }

        $organizations = $card->getOrganizations() ?: [];

        // If there is no ORG yet, create one so X-DEPARTMENT still has somewhere to go.
        if (empty($organizations)) {
            $org = new Organization();
            $organizations['o1'] = $org;
        }

        $firstKey = array_key_first($organizations);
        $firstOrg = $organizations[$firstKey];

        if (!($firstOrg instanceof Organization)) {
            return;
        }

        $units = $firstOrg->getUnits() ?: [];

        foreach ($departments as $dept) {
            $value = trim((string)$dept);
            if ($value !== "") {
                $units[] = $value;
            }
        }

        if (!empty($units)) {
            $units = array_values(array_unique($units, SORT_STRING));
            $firstOrg->setUnits($units);
        }

        $organizations[$firstKey] = $firstOrg;
        $card->setOrganizations($organizations);
    }

    //JSContact -> vCard
    /**
     * Writes name components to the vCard, then also writes maiden name since
     * both belong to the contact's name identity.
     */
    public function setNameFromJmap($card)
    {
        parent::setNameFromJmap($card);
        $this->setMaidenNameFromJmap($card);
    }

    /**
     * Writes the maiden name from the ContactCard back to the X-MAIDENNAME vCard property.
     */
    public function setMaidenNameFromJmap($card)
    {
        $maidenName = $card->getProperty("audriga.eu/roundcube:maidenName");

        if ($maidenName !== null && $maidenName !== "") {
            $value = is_string($maidenName) ? $maidenName : (string)$maidenName;
            if (trim($value) !== "") {
                $this->addSingleProperty("X-MAIDENNAME", trim($value));
            }
        }
    }

    /**
     * Writes grammatical gender as X-GENDER for male/female values, falling back
     * to the standard GRAMGENDER property for anything else.
     */
    public function setGramGenderFromJmap($card)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!$speakToAs) {
            return;
        }

        $grammaticalGender = $speakToAs->getGrammaticalGender();
        if (!$grammaticalGender) {
            parent::setGramGenderFromJmap($card);
            return;
        }

        if ($grammaticalGender === "male" || $grammaticalGender === "female") {
            $this->vcard->add("X-GENDER", $grammaticalGender);
            return;
        }

        parent::setGramGenderFromJmap($card);
    }

    /**
     * Writes standard online services, then also writes any Roundcube IM services
     * back to their corresponding X-properties.
     */
    public function setOnlineFromJmap($card)
    {
        parent::setOnlineFromJmap($card);

        $services = $card->getOnlineServices();
        if (!is_array($services)) {
            return;
        }

        $serviceToXProperty = [
            'aim'              => 'X-AIM',
            'x-aim'            => 'X-AIM',
            'icq'              => 'X-ICQ',
            'x-icq'            => 'X-ICQ',
            'msn'              => 'X-MSN',
            'x-msn'            => 'X-MSN',
            'yahoo'            => 'X-YAHOO',
            'x-yahoo'          => 'X-YAHOO',
            'jabber'           => 'X-JABBER',
            'x-jabber'         => 'X-JABBER',
            'skype'            => 'X-SKYPE-USERNAME',
            'x-skype'          => 'X-SKYPE-USERNAME',
            'x-skype-username' => 'X-SKYPE-USERNAME',
        ];

        foreach ($services as $service) {
            if (!($service instanceof OnlineService)) {
                continue;
            }

            $xProperty = null;

            $label = strtoupper((string)$service->getLabel());
            $roundcubeProps = ['X-AIM', 'X-ICQ', 'X-MSN', 'X-YAHOO', 'X-JABBER', 'X-SKYPE-USERNAME'];
            if (in_array($label, $roundcubeProps, true)) {
                $xProperty = $label;
            } elseif ($service->getService()) {
                $serviceName = strtolower((string)$service->getService());
                if (isset($serviceToXProperty[$serviceName])) {
                    $xProperty = $serviceToXProperty[$serviceName];
                }
            }

            if ($xProperty) {
                $value = $service->getUri() ?: $service->getUser();
                if ($value) {
                    $this->vcard->add($xProperty, $value);
                }
            }
        }
    }

    /**
     * Writes standard RELATED properties, then also writes X-MANAGER, X-ASSISTANT,
     * and X-SPOUSE for any relations with those types.
     */
    public function setRelatedToFromJmap($card)
    {
        parent::setRelatedToFromJmap($card);

        $relations = $card->getRelatedTo();
        if (!is_array($relations)) {
            return;
        }

        foreach ($relations as $uid => $relation) {
            if (!($relation instanceof Relation)) {
                continue;
            }

            $types = $relation->getRelation();
            if (!is_array($types)) {
                continue;
            }

            if (!empty($types["manager"])) {
                $this->vcard->add("X-MANAGER", $uid);
            }
            if (!empty($types["assistant"])) {
                $this->vcard->add("X-ASSISTANT", $uid);
            }
            if (!empty($types["spouse"])) {
                $this->vcard->add("X-SPOUSE", $uid);
            }
        }
    }

    /**
     * Writes standard anniversaries, then also writes any anniversary labelled
     * "x-anniversary" back to the X-ANNIVERSARY vCard property.
     */
    public function setAnniversariesFromJmap($card)
    {
        parent::setAnniversariesFromJmap($card);

        $anniversaries = $card->getAnniversaries();
        if (!is_array($anniversaries) || empty($anniversaries)) {
            return;
        }

        $writtenDates = [];

        foreach ($anniversaries as $anniversary) {
            if (!($anniversary instanceof Anniversary)) {
                continue;
            }

            $label = strtolower(trim((string) $anniversary->getLabel()));
            $date  = trim((string) $anniversary->getDate());

            if ($label !== 'x-anniversary' || $date === '') {
                continue;
            }

            $normalizedDate = AdapterUtil::parseDateTime($date, 'Y-m-d', 'Y-m-d', 'Ymd');
            if ($normalizedDate === null) {
                continue;
            }

            if (isset($writtenDates[$normalizedDate])) {
                continue;
            }

            $writtenDates[$normalizedDate] = true;
            $this->vcard->add('X-ANNIVERSARY', $normalizedDate);
        }
    }

    /**
      * Writes Roundcube organizations as:
    *   ORG:<organization name only>
    *   X-DEPARTMENT:<unit>
    *   X-DEPARTMENT:<unit>
    */
    public function setOrganizationFromJmap($card)
    {
        $organizations = $card->getOrganizations();
        if (!is_array($organizations) || empty($organizations)) {
            return;
        }

        foreach ($organizations as $org) {
            if (!($org instanceof Organization)) {
                continue;
            }

            $name = trim((string)$org->getName());
            if ($name === '') {
                continue;
            }

            $params = [];
            $types = $this->contextsToVcardTypeParam($org);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            //ORG contains only the organization name.
            $this->vcard->add("ORG", [$name], $params);

            //departments/units go only into X-DEPARTMENT.
            $units = $org->getUnits();
            if (!is_array($units) || empty($units)) {
                continue;
            }

            $seen = [];
            foreach ($units as $unit) {
                if ($unit instanceof OrgUnit) {
                    $unitName = trim((string)$unit->getName());
                } elseif (is_string($unit)) {
                    $unitName = trim($unit);
                } else {
                    $unitName = '';
                }

                if ($unitName === '' || isset($seen[$unitName])) {
                    continue;
                }

                $seen[$unitName] = true;
                $this->vcard->add("X-DEPARTMENT", $unitName);
            }
        }
    }
    /**
     * Extracts the TYPE parameter values from a vCard property as a lowercase array.
     */
    private function extractPropertyTypes($property)
    {
        $types = [];

        if (isset($property['TYPE'])) {
            $parts = $property['TYPE']->getParts();
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $type = strtolower(trim((string)$part));
                    if ($type !== '') {
                        $types[] = $type;
                    }
                }
            }
        }

        return $types;
    }
}
