<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\OrgUnit;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Util\AdapterUtil;

/**
 * Roundcube-specific adapter to convert between vCard <-> JSContact.
 * Overrides methods of the generic adapter if Roundcube deviates.
 */
class RoundcubeJSContactVCardAdapter extends JSContactVCardAdapter
{
    protected $logger;

    /**
     * This function maps the vCard "BDAY", "BIRTHPLACE", "DEATHDATE", "DEATHPLACE", "ANNIVERSARY"
     * and "X-ANNIVERSARY" properties to the JSContact "anniversaries" property
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getAnniversariesToJmap($card)
    {
        parent::getAnniversariesToJmap($card);

        $xAnniversary = $this->vCard->__get("X-ANNIVERSARY");
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
     * This function maps the vCard "IMPP", "SOCIALPROFILE", "URL" and Roundcube-specific
     * instant messaging properties to the JSContact "onlineServices" property
     *
     * The Roundcube-specific vCard properties are:
     *  * X-AIM
     *  * X-ICQ
     *  * X-MSN
     *  * X-YAHOO
     *  * X-JABBER
     *  * X-SKYPE-USERNAME
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getOnlineServicesToJmap($card)
    {
        parent::getOnlineServicesToJmap($card);

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
     *
     * @param string $propertyName The vCard property name (e.g., "X-AIM")
     * @param string $serviceName The service name for JSContact (e.g., "aim")
     * @param array $services Reference to the services array being built
     * @param int $index Reference to the current index counter
     */
    private function readRoundcubeIM($propertyName, $serviceName, array &$services, &$index)
    {
        $properties = $this->vCard->__get($propertyName);
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

            $contexts = $this->vCardTypeParamToContexts($property);
            if (!empty($contexts)) {
                $service->setContexts($contexts);
            }

            $pref = $this->vCardPrefParamToInt($property);
            if ($pref !== null) {
                $service->setPref($pref);
            }

            $services['os' . $index++] = $service;
        }
    }

    /**
     * This function maps the vCard "X-GENDER" property to the JSContact "speakToAs" property
     * Falls back to standard GRAMGENDER handling if X-GENDER is not "male" or "female"
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getGramGenderToJmap($card)
    {
        $xGender = $this->vCard->__get("X-GENDER");
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
     * This function maps the vCard "RELATED", "X-MANAGER", "X-ASSISTANT" and "X-SPOUSE"
     * properties to the JSContact "relatedTo" property
     *
     * @param ContactCard $card The ContactCard to populate
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
     *
     * @param string $propertyName The vCard property name (e.g., "X-MANAGER")
     * @param string $relationType The relation type for JSContact (e.g., "manager")
     * @param array $relations Reference to the relations array being built
     */
    private function readRelatedX($propertyName, $relationType, array &$relations)
    {
        $properties = $this->vCard->__get($propertyName);
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
     * This function maps the vCard "ORG" and "X-DEPARTMENT" properties to the JSContact "organizations" property
     * Note: X-DEPARTMENT values are mapped to the "units" property of Organization objects
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getOrganizationToJmap($card)
    {
        parent::getOrganizationToJmap($card);

        $departments = $this->vCard->__get("X-DEPARTMENT");
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

    /**
     * This function maps the vCard X-MAIDENNAME (Roundcube-specific property)
     * to the JSContact "audriga.eu/roundcube:maidenName" property
     *
     * @param ContactCard $card The ContactCard to populate
     */
    public function getMaidenNameToJmap($card)
    {
        $xMaidenName = $this->vCard->__get("X-MAIDENNAME");
        if (AdapterUtil::isSetAndNotNull($xMaidenName)) {
            $value = trim((string)$xMaidenName);
            if ($value !== "") {
                $card->setProperty("audriga.eu/roundcube:maidenName", $value);
            }
        }
    }

    /**
     * This function maps entries of the JSContact "anniversaries" property corresponding to
     * the vCard X-ANNIVERSARY property to it
     * Note: The vCard X-ANNIVERSARY property is Roundcube-specific
     *
     * @param ContactCard $card The ContactCard containing anniversaries
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
            $this->vCard->add('X-ANNIVERSARY', $normalizedDate);
        }
    }

    /**
     * This function maps the JSContact "onlineServices" property to vCard properties including
     * Roundcube-specific X- properties for instant messaging services
     *
     * @param ContactCard $card The ContactCard containing online services
     */
    public function setOnlineServicesFromJmap($card)
    {
        parent::setOnlineServicesFromJmap($card);

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
                    $this->vCard->add($xProperty, $value);
                }
            }
        }
    }

    /**
     * This function maps the JSContact "speakToAs" property to the vCard X-GENDER property
     * Falls back to standard GRAMGENDER for values other than "male" or "female"
     *
     * @param ContactCard $card The ContactCard containing speakToAs information
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
            $this->vCard->add("X-GENDER", $grammaticalGender);
            return;
        }

        parent::setGramGenderFromJmap($card);
    }

    /**
     * This function maps the JSContact "relatedTo" property to vCard RELATED and
     * Roundcube-specific X-MANAGER, X-ASSISTANT, and X-SPOUSE properties
     *
     * @param ContactCard $card The ContactCard containing relations
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
                $this->vCard->add("X-MANAGER", $uid);
            }
            if (!empty($types["assistant"])) {
                $this->vCard->add("X-ASSISTANT", $uid);
            }
            if (!empty($types["spouse"])) {
                $this->vCard->add("X-SPOUSE", $uid);
            }
        }
    }

    /**
     * This function maps the JSContact "organizations" property to the vCard ORG and X-DEPARTMENT properties
     * Note: In Roundcube, organization units are stored in separate X-DEPARTMENT properties
     *
     * @param ContactCard $card The ContactCard containing organizations
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

            // ORG contains only the organization name.
            $this->vCard->add("ORG", [$name], $params);

            // Departments/units go only into X-DEPARTMENT.
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
                $this->vCard->add("X-DEPARTMENT", $unitName);
            }
        }
    }

    /**
     * This function maps the JSContact "audriga.eu/roundcube:maidenName" property to the vCard X-MAIDENNAME property
     *
     * @param ContactCard $card The ContactCard containing maiden name
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
     * Writes name components to the vCard, then also writes maiden name since
     * both belong to the contact's name identity.
     *
     * @param ContactCard $card The ContactCard containing name information
     */
    public function setNameFromJmap($card)
    {
        parent::setNameFromJmap($card);
        $this->setMaidenNameFromJmap($card);
    }
}
