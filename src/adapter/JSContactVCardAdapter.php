<?php

namespace OpenXPort\Adapter;

use OpenXPort\Jmap\JSContact\ContactCard;
use OpenXPort\Jmap\JSContact\Name;
use OpenXPort\Jmap\JSContact\NameComponent;
use OpenXPort\Jmap\JSContact\Nickname;
use OpenXPort\Jmap\JSContact\Organization;
use OpenXPort\Jmap\JSContact\Title;
use OpenXPort\Jmap\JSContact\Note;
use OpenXPort\Jmap\JSContact\EmailAddress;
use OpenXPort\Jmap\JSContact\Phone;
use OpenXPort\Jmap\JSContact\OnlineService;
use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\LanguagePref;
use OpenXPort\Jmap\JSContact\PersonalInformation;
use OpenXPort\Jmap\JSContact\SpeakToAs;
use OpenXPort\Jmap\JSContact\Directory;
use OpenXPort\Jmap\JSContact\Link;
use OpenXPort\Jmap\JSContact\SchedulingAddress;
use OpenXPort\Jmap\JSContact\CryptoKey;
use OpenXPort\Jmap\JSContact\Calendar;
use OpenXPort\Jmap\JSContact\Author;
use OpenXPort\Jmap\JSContact\Pronouns;
use OpenXPort\Jmap\JSContact\AddressComponent;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\JSContactVCardAdapterUtil as Util;
use OpenXPort\Util\Logger;
use Sabre\VObject;
use Sabre\VObject\Property;
use Sabre\VObject\ParseException;

/**
 * Generic adapter to convert between vCard <-> JSContact.
 * Strictly follows the "JSContact: Converting from and to vCard" spec
 */
class JSContactVCardAdapter extends AbstractAdapter
{
    protected $logger;

    /** @var VObject\Component\VCard */
    protected $vCard;

    /** @var string */
    protected $rawVCard;

    protected $vCardChildren = array();

    /**
     * @var array<string, X> OXP-specific properties not present in vCard or JSContact:
     *  * addressBookId
     *  * vCardProps
     *      https://www.ietf.org/archive/id/draft-ietf-calext-jscontact-vCard-06.html#name-property-vCardprops
     *  * vCardParams array<PropertyName, array<ObjectId, array<Property, Value>>>
     *      https://www.ietf.org/archive/id/draft-ietf-calext-jscontact-vCard-06.html#name-property-vCardparams
     */
    protected $oxpProperties = [];

    /** @var bool When true, plain text birth/death places are kept as a full address string. */
    protected $placeTextAsFullAddress = true;

    /** @var bool When true, vCard ANNIVERSARY is treated as a wedding anniversary. */
    protected $mapVcardAnniversaryToWedding = true;

    protected $addressBookId = null;

    /**
     * @var string|null Config option that determines the behavior of the adapter when encountering 'broken'
     * vCards. Possible values are:
     * * 'strict' - Any vCard that cannot be parsed will be logged -,
     * * 'ignoreInvalidLines' - Invalid lines in a vCard will be skipped - or
     * * 'ignoreInvalidCards'. - Invalid vCards, which cannot be read after skipping invalid lines are skipped -
     *
     * Default behavior is 'strict'.
     */
    protected $parsingConfig;

    /**
     * @var bool|null Config option that determines if 'broken' vCards are dumped into the log or not.
     * Default behavior is 'false'
     */
    protected $dumpInvalidVCards;

    /**
     * Constructor of this class
     *
     * Initializes the $vCard property of this class to a new VCard() object
     *
     * @param null|string $parsingConfig Determines which behavior is expected when
     * encountering ParseExceptions while reading vCards. 'strict' will not change
     * anything about the parsing method. 'ignoreInvalidLines' will read the card
     * again after it has failed for the first time, ignoring any lines that the reader
     * does not recognise. 'IgnoreInvalidCards' will retry as well, but simply not map
     * the card if the exception persists after re-trying.
     *
     * @param null|bool $dumpInvalidVCards Determines whether a vCard that causes a
     * ParsException to be thrown gets dumped into the logs.
     */
    public function __construct($parsingConfig = 'strict', $dumpInvalidVCards = false)
    {
        $this->vCard = new VObject\Component\VCard();
        $this->logger = Logger::getInstance();
        $this->parsingConfig = $parsingConfig;
        $this->dumpInvalidVCards = $dumpInvalidVCards;

        $this->logger->info(
            "Using RFC 9555 compliant adapter with config: 'vCardParsing' => '"
            . $this->parsingConfig
            . "', 'dumpInvalidVCards' => "
            . ($this->dumpInvalidVCards ? 'true' : 'false')
            . '.'
        );
    }

    /**
     * Reset the content in the adapter.
     */
    public function reset()
    {
        $this->vCard = new VObject\Component\VCard();
        $this->rawVCard = null;
        $this->oxpProperties = array();
        $this->vCardChildren = array();
    }

    /**
     * Return the contents of this adapter as a hash.
     *
     * Right now there are two properties:
     * * "vCard": The serialized vCard is a single property of this hash
     * * "oxpProperties": properties not present in the vCard like addressBookId
     *
     * @return array The hash representation of the adapter
     */
    public function getAsHash()
    {
        return array(
            'vCard' => $this->getVCard(),
            'oxpProperties' => array(
                'addressBookId' => $this->addressBookId,
            ),
        );
    }

    /**
     * Set vCard and oxpProperties from a hash
     */
    public function setFromHash($cHash)
    {
        if (!is_array($cHash)) {
            return;
        }

        if (isset($cHash['vCard']) && is_string($cHash['vCard'])) {
            $this->setVCard($cHash['vCard']);
        }

        if (isset($cHash['oxpProperties']['addressBookId'])) {
            $this->addressBookId = $cHash['oxpProperties']['addressBookId'];
        }
    }

    /**
     * Getter for this class' $vCard property
     *
     * Obtain the vCard object represented in this adapter
     *
     * @return string The vCard of the adapter, serialized as string
     */
    public function getVCard()
    {
        if (!AdapterUtil::isSetAndNotNull($this->vCard)) {
            return null;
        }
        return $this->vCard->serialize();
    }

    /**
     * Setter for this class' $vCard property
     *
     * Set the vCard object represented in this adapter
     *
     * @param string $vCardString The vCard string used to initialize the vCard object of this adapter
     */
    public function setVCard($vCardString)
    {
        $this->rawVCard = $vCardString;

        try {
            $this->vCard = VObject\Reader::read($vCardString);
        } catch (VObject\ParseException $e) {
            $this->setBrokenVCard($vCardString, $e);
        }

        if (is_null($this->vCard)) {
            return;
        }

        foreach ($this->vCard->children() as $vCardChild) {
            $this->vCardChildren[] = $vCardChild->name;
        }
    }

    /**
     * Enter this method when reading a vCard string throws a ParseException.
     * In here, the exception is handled depending on what $parsingConfig is
     * set to.
     * If it is:
     *
     * * 'strict', it is re-thrown
     * * 'ignoreInvalidLines', it is retried with 'OPTION_IGNORE_INVALID_LINES'.
     * * 'ignoreInvalidVCards', it is retried and skipped if an error still persists.
     *
     * @param string $vCardString A broken vCard represented as a string.
     *
     * @param ParseException $e The exception thrown from the first try of running
     * VObject\Reader::read() without 'OPTION_IGNORE_INVALID_LINES'.
     */
    protected function setBrokenVCard($vCardString, VObject\ParseException $e)
    {
        switch ($this->parsingConfig) {
            case 'strict':
                $this->handleVCardDump($vCardString);
                throw $e;

            case 'ignoreInvalidLines':
                try {
                    $this->vCard = VObject\Reader::read(
                        $vCardString,
                        VObject\Reader::OPTION_IGNORE_INVALID_LINES
                    );
                } catch (VObject\ParseException $p) {
                    $this->handleVCardDump($vCardString);
                    throw $p;
                }
                break;

            case 'ignoreInvalidVCards':
                try {
                    $this->vCard = VObject\Reader::read(
                        $vCardString,
                        VObject\Reader::OPTION_IGNORE_INVALID_LINES
                    );
                } catch (VObject\ParseException $ignored) {
                    $this->handleVCardDump($vCardString);
                    $this->vCard = null;
                }
                break;

            default:
                $this->handleVCardDump($vCardString);
                throw $e;
        }
    }

    public function getAddressBookId(ContactCard $card)
    {
        if ($this->addressBookId === null) {
            $this->logger->warning(
                "addressBookId does not exist for card " . $this->getUid($card)
            );
        }

        return $this->addressBookId;
    }

    public function setAddressBookId($addressBookId)
    {
        $this->addressBookId = $addressBookId;
    }

    /**
     * If $dumpInvalidVCards is set to true, the vCard string is dumped using the logger.
     *
     * @param string $vCardString vCard to be dumped.
     */
    protected function handleVCardDump($vCardString)
    {
        if (!$this->dumpInvalidVCards) {
            return;
        }

        $this->logger->warning("Dumping vCard:\n$vCardString");
    }

    /**
     * Writes a vCard property, removing any existing copies of it first so there's never more than one.
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $params
     */
    protected function addSingleProperty($name, $value, array $params = array())
    {
        if (isset($this->vCard->{$name})) {
            foreach ($this->vCard->{$name} as $prop) {
                $this->vCard->remove($prop);
            }
        }

        $this->vCard->add($name, $value, $params);
    }

    /**
     * Reads the TYPE parameter off a vCard property and turns it into JSContact contexts.
     * "home" becomes "private" and "work" stays "work".
     *
     * @param mixed $prop
     * @return array<string, true>
     */
    protected function vCardTypeParamToContexts($prop)
    {
        return Util::vCardTypeParamToContexts($prop);
    }

    /**
     * Reads the PREF parameter off a vCard property and returns it as an integer.
     * Returns null if it's missing, empty, or not a positive number.
     *
     * @param mixed $prop
     * @return int|null
     */
    protected function vCardPrefParamToInt($prop)
    {
        return Util::vCardPrefParamToInt($prop);
    }

    /**
     * Converts JSContact contexts back to vCard TYPE values.
     * "private" becomes "home" and "work" stays "work".
     *
     * @param mixed $obj
     * @return array<int, string>
     */
    protected function contextsToVcardTypeParam($obj)
    {
        return Util::contextsToVcardTypeParam($obj);
    }

    /**
     * Reads the preference value from a JSContact object and returns it as a string for the vCard PREF parameter.
     * Returns null if there's nothing set.
     *
     * @param mixed $obj
     * @return string|null
     */
    protected function prefToVcardParam($obj)
    {
        return Util::prefToVcardParam($obj);
    }

    /**
     * Converts a Y-m-d date string to the compact vCard date format (Ymd).
     * Returns null for empty input or the zero-date placeholder '0000-00-00'.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseDateToVcardDate($value)
    {
        return Util::parseDateToVcardDate($value);
    }

    /**
     * Converts a JSContact UTC timestamp to vCard TIMESTAMP format (YmdTHisZ).
     * Returns null for empty input.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseDateTimeToVcardTimestamp($value)
    {
        return Util::parseDateTimeToVcardTimestamp($value);
    }

    /**
     * Converts a vCard TIMESTAMP to a JSContact UTC timestamp string.
     * Returns null for empty input.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseTimestampDateTime($value)
    {
        return Util::parseTimestampDateTime($value);
    }

    /**
     * Converts a vCard date value into a JSContact UTC string.
     * Returns null if none of the formats match.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseDateTimeToJscontactUtc($value)
    {
        return Util::parseDateTimeToJscontactUtc($value);
    }

    /**
     * Returns all non-empty values for a repeatable vCard property as an array.
     *
     * @param string $name
     * @return array<int, string>
     */
    protected function getPropertyValues($name)
    {
        $result = array();
        $props = $this->vCard->{$name};

        if (!AdapterUtil::isSetAndNotNull($props) || empty($props)) {
            return $result;
        }

        foreach ($props as $prop) {
            $value = trim((string) $prop);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns the value of a single-valued vCard property as a trimmed string, or null if absent or empty.
     *
     * @param string $name
     * @return string|null
     */
    protected function getSinglePropertyValue($name)
    {
        $prop = $this->vCard->{$name};
        if (!AdapterUtil::isSetAndNotNull($prop)) {
            return null;
        }

        $value = trim((string) $prop);
        return $value === '' ? null : $value;
    }


    /**
     * Writes the five standard name components to the vCard N property.
     * Any component that is null or empty is written as an empty string.
     *
     * @param string|null $lastName
     * @param string|null $firstName
     * @param string|null $middleName
     * @param string|null $prefix
     * @param string|null $suffix
     */
    protected function setN($lastName, $firstName, $middleName, $prefix, $suffix)
    {
        $lastName   = AdapterUtil::isSetAndNotNull($lastName)   && $lastName   !== '' ? $lastName   : '';
        $firstName  = AdapterUtil::isSetAndNotNull($firstName)  && $firstName  !== '' ? $firstName  : '';
        $middleName = AdapterUtil::isSetAndNotNull($middleName) && $middleName !== '' ? $middleName : '';
        $prefix     = AdapterUtil::isSetAndNotNull($prefix)     && $prefix     !== '' ? $prefix     : '';
        $suffix     = AdapterUtil::isSetAndNotNull($suffix)     && $suffix     !== '' ? $suffix     : '';

        $this->addSingleProperty('N', array($lastName, $firstName, $middleName, $prefix, $suffix));
    }

    /**
     * Returns the given (first) name from the vCard N property, or null if not set.
     *
     * @return string|null
     */
    protected function getFirstName()
    {
        $n = $this->vCard->N;
        if (AdapterUtil::isSetAndNotNull($n)) {
            $parts = $n->getParts();
            return isset($parts[1]) ? $parts[1] : null;
        }
        return null;
    }

    /**
     * Returns the family (last) name from the vCard N property, or null if not set.
     *
     * @return string|null
     */
    protected function getLastName()
    {
        $n = $this->vCard->N;
        if (AdapterUtil::isSetAndNotNull($n)) {
            $parts = $n->getParts();
            return isset($parts[0]) ? $parts[0] : null;
        }
        return null;
    }

    /**
     * Returns the middle name from the vCard N property, or null if absent or empty.
     *
     * @return string|null
     */
    protected function getMiddlename()
    {
        $n = $this->vCard->N;
        if (AdapterUtil::isSetAndNotNull($n)) {
            $parts = $n->getParts();
            if (isset($parts[2])) {
                $middle = $parts[2];
                if (AdapterUtil::isSetAndNotNull($middle) && $middle !== '') {
                    return $middle;
                }
            }
        }
        return null;
    }

    /**
     * Returns the honorific prefix (e.g. "Dr.", "Mr.") from the vCard N property, or null.
     *
     * @return string|null
     */
    protected function getPrefix()
    {
        $n = $this->vCard->N;
        if (AdapterUtil::isSetAndNotNull($n)) {
            $parts = $n->getParts();
            return isset($parts[3]) ? $parts[3] : null;
        }
        return null;
    }

    /**
     * Returns the honorific suffix (e.g. "Jr.", "PhD") from the vCard N property, or null.
     *
     * @return string|null
     */
    protected function getSuffix()
    {
        $n = $this->vCard->N;
        if (AdapterUtil::isSetAndNotNull($n)) {
            $parts = $n->getParts();
            return isset($parts[4]) ? $parts[4] : null;
        }
        return null;
    }

    /**
     * Writes the display name to the vCard FN property if the value is non-empty.
     *
     * @param string $displayname
     */
    protected function setDisplayname($displayname)
    {
        if (AdapterUtil::isSetAndNotNull($displayname) && $displayname !== '') {
            $this->addSingleProperty('FN', $displayname);
        }
    }

    /**
     * Returns the display name from the vCard FN property, or null if absent.
     *
     * @return string|null
     */
    protected function getDisplayname()
    {
        $fn = $this->vCard->FN;
        if (AdapterUtil::isSetAndNotNull($fn) && !empty($fn)) {
            return (string) $fn;
        }
        return null;
    }

    /**
     * This function maps the "uid" JSContact property to the UID vCard property
     *
     * @param ContactCard $card
     */
    public function setUid(ContactCard $card)
    {
        $uid = $card->getUid();

        if (!isset($uid) || empty($uid)) {
            return;
        }

        $this->addSingleProperty('UID', $uid);
    }

    /**
     * This function maps the vCard "UID" property to the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getUid(ContactCard $card)
    {
        $vCardUidProperty = $this->vCard->UID;

        if (AdapterUtil::isSetAndNotNull($vCardUidProperty)) {
            $vCardUidPropertyValue = trim((string) $vCardUidProperty);
            if (Util::isNonEmptyString($vCardUidPropertyValue)) {
                $card->setUid($vCardUidPropertyValue);
                return;
            }
        }

        $card->setUid(null);
    }

    /**
     * Writes the ContactCard's last-modified timestamp to the vCard REV property.
     *
     * @param ContactCard $card
     */
    public function setUpdated(ContactCard $card)
    {
        $updated = $card->getUpdated();
        $vRev = Util::parseDateTimeToVcardTimestamp($updated);
        if ($vRev !== null) {
            $this->addSingleProperty('REV', $vRev);
        }
    }

    /**
     * Reads the vCard REV timestamp and stores it as the ContactCard's updated date.
     *
     * @param ContactCard $card
     */
    public function getUpdated(ContactCard $card)
    {
        $rev = $this->vCard->REV;
        if (!AdapterUtil::isSetAndNotNull($rev)) {
            return;
        }

        $value = trim((string) $rev);
        $parsed = $this->parseTimestampDateTime($value);
        if ($parsed !== null) {
            $card->setUpdated($parsed);
        }
    }

    /**
     * Writes the ContactCard creation timestamp to the vCard CREATED property.
     *
     * @param ContactCard $card
     */
    public function setCreated(ContactCard $card)
    {
        $created = $card->getCreated();
        $vCreated = $this->parseDateTimeToVcardTimestamp($created);
        if ($vCreated !== null) {
            $this->addSingleProperty('CREATED', $vCreated);
        }
    }

    /**
     * Reads the vCard CREATED timestamp and stores it on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getCreated(ContactCard $card)
    {
        $created = $this->vCard->__get('CREATED');
        if (!AdapterUtil::isSetAndNotNull($created)) {
            return;
        }

        $value = trim((string) $created);
        if ($value === '') {
            return;
        }

        $parsed = $this->parseTimestampDateTime($value);
        if ($parsed !== null) {
            $card->setCreated($parsed);
        }
    }

    /**
     * Writes the ContactCard product ID to the vCard PRODID property.
     *
     * @param ContactCard $card
     */
    public function setProdId(ContactCard $card)
    {
        $prodId = $card->getProdId();
        if (is_string($prodId) && $prodId !== '') {
            $this->addSingleProperty('PRODID', $prodId);
        }
    }

    /**
     * Reads the vCard PRODID and stores it on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getProdId(ContactCard $card)
    {
        $value = $this->getSinglePropertyValue('PRODID');
        if ($value !== null) {
            $card->setProdId($value);
        }
    }

    /**
     * Writes the contact kind to the vCard KIND property.
     * If the card has members, KIND is always forced to "group".
     *
     * @param ContactCard $card
     */
    public function setKind(ContactCard $card)
    {
        $members = $card->getMembers();
        if (is_array($members) && !empty($members)) {
            $this->addSingleProperty('KIND', 'group');
            return;
        }

        $kind = $card->getKind();
        if (is_string($kind) && $kind !== '') {
            $this->addSingleProperty('KIND', $kind);
        }
    }

    /**
     * Reads the vCard KIND and stores it on the ContactCard.
     * Skips "group" since group membership is handled separately.
     *
     * @param ContactCard $card
     */
    public function getKind(ContactCard $card)
    {
        $kind = $this->vCard->KIND;
        if (!AdapterUtil::isSetAndNotNull($kind)) {
            return;
        }

        $value = trim((string) $kind);
        if ($value !== '' && $value !== 'group') {
            $card->setKind($value);
        }
    }

    /**
     * Writes the ContactCard language to the vCard LANGUAGE property.
     *
     * @param ContactCard $card
     */
    public function setLanguage(ContactCard $card)
    {
        $language = $card->getLanguage();
        if (is_string($language) && $language !== '') {
            $this->addSingleProperty('LANGUAGE', $language);
        }
    }

    /**
     * Reads the vCard LANGUAGE and stores it on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getLanguage(ContactCard $card)
    {
        $value = $this->getSinglePropertyValue('LANGUAGE');
        if ($value !== null) {
            $card->setLanguage($value);
        }
    }

    /**
     * Writes N property with JSCOMPS parameter reconstructed from ordered components.
     *
     * @param Name $name
     * @param array $components
     */
    protected function setNameWithJscomps($name, $components)
    {
        $kindToPosition = Util::getNameKindToPositionMap();
        $defaultSep = '';

        if (method_exists($name, 'getDefaultSeparator')) {
            $sep = $name->getDefaultSeparator();
            if ($sep !== null && $sep !== '') {
                $defaultSep = $sep;
            }
        }

        list($parts, $jscompsValue) = Util::buildJscompsData(
            $components,
            $kindToPosition,
            $defaultSep,
            8
        );

        $params = array('JSCOMPS' => $jscompsValue);
        $this->addSingleProperty('N', $parts, $params);
    }

    /**
     * Writes the vCard N property from the name components on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function setName(ContactCard $card)
    {
        $name = $card->getName();
        if (!($name instanceof Name)) {
            return;
        }

        $components = $name->getComponents();
        if (!is_array($components) || empty($components)) {
            return;
        }

        $isOrdered = method_exists($name, 'getIsOrdered') ? $name->getIsOrdered() : false;

        if ($isOrdered) {
            $this->setNameWithJscomps($name, $components);
            return;
        }

        $kindMap = ['surname' => null, 'given' => null, 'given2' => null, 'title' => null, 'credential' => null];
        foreach ($components as $component) {
            $kind = $component->getKind();
            if (isset($kindMap[$kind])) {
                $kindMap[$kind] = $component->getValue();
            }
        }

        $this->setN(
            $kindMap['surname'],
            $kindMap['given'],
            $kindMap['given2'],
            $kindMap['title'],
            $kindMap['credential']
        );
    }

    /**
     * Writes the vCard FN from name.full on the ContactCard.
     * Falls back to joining given, middle, and surname if name.full is empty.
     *
     * @param ContactCard $card
     */
    public function setFn(ContactCard $card)
    {
        $name = $card->getName();
        $full = ($name instanceof Name) ? $name->getFull() : null;

        if ($full === null || $full === '') {
            $full = $this->buildFullNameFromComponents($name);
        }

        if ($full !== null && $full !== '') {
            $this->setDisplayname($full);
        }
    }

    protected function buildFullNameFromComponents($name)
    {
        if (!($name instanceof Name)) {
            return null;
        }

        $components = $name->getComponents();
        if (!is_array($components)) {
            return null;
        }

        $parts = [];
        foreach ($components as $component) {
            $kind = $component->getKind();
            $value = $component->getValue();
            if (($kind === 'given' || $kind === 'given2' || $kind === 'surname') && $value !== null && $value !== '') {
                $parts[] = $value;
            }
        }

        return empty($parts) ? null : implode(' ', $parts);
    }

    /**
     * Reads the vCard N and FN properties and builds the ContactCard name with all its components.
     *
     * @param ContactCard $card
     */
    public function getName(ContactCard $card)
    {
        $n = $this->vCard->N;
        $name = new Name();

        $fn = $this->getDisplayname();
        if ($fn !== null && $fn !== '') {
            $name->setFull($fn);
        }

        // Check if JSCOMPS is present.if so, parse it to get the correct order
        $hasJscomps = AdapterUtil::isSetAndNotNull($n) && isset($n['JSCOMPS']);

        if ($hasJscomps) {
            $jscompsValue = (string) $n['JSCOMPS'];
            $parts = $n->getParts();
            $positionToKind = Util::getNamePositionToKindMap();

            $components = Util::parseJscompsData(
                $jscompsValue,
                $parts,
                $positionToKind,
                'OpenXPort\\Jmap\\JSContact\\NameComponent'
            );

            if (!empty($components)) {
                $name->setComponents($components);
                $name->setIsOrdered(true);

                $defaultSep = Util::getDefaultSeparatorFromJscomps($jscompsValue);
                if ($defaultSep !== null) {
                    $name->setDefaultSeparator($defaultSep);
                }
            }
        } else {
            $componentMap = array(
                'title' => $this->getPrefix(),
                'given' => $this->getFirstName(),
                'given2' => $this->getMiddlename(),
                'surname' => $this->getLastName(),
                'credential' => $this->getSuffix()
            );

            $components = array();
            foreach ($componentMap as $kind => $value) {
                if ($value !== null && $value !== '') {
                    $c = new NameComponent();
                    $c->setKind($kind);
                    $c->setValue($value);
                    $components[] = $c;
                }
            }

            if (!empty($components)) {
                $name->setComponents($components);
                $name->setIsOrdered(true);
            }
        }

        $card->setName($name);
    }

    /**
     * Writes each ContactCard nickname as a separate vCard NICKNAME property.
     *
     * @param ContactCard $card
     */
    public function setNickname(ContactCard $card)
    {
        $nicks = $card->getNicknames();
        if (!is_array($nicks) || empty($nicks)) {
            return;
        }

        foreach ($nicks as $id => $nick) {
            if (!($nick instanceof Nickname)) {
                continue;
            }

            $name = $nick->getName();
            if (is_string($name) && $name !== '') {
                $params = array();
                $params = Util::addPropIdParam($params, $id);
                $this->vCard->add('NICKNAME', $name, $params);
            }
        }
    }

    /**
     * Reads all vCard NICKNAME properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getNickname(ContactCard $card)
    {
        $vNicknames = $this->vCard->NICKNAME;
        if (!AdapterUtil::isSetAndNotNull($vNicknames) || empty($vNicknames)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($vNicknames as $prop) {
            $nickname = trim((string) $prop);
            if ($nickname === '') {
                continue;
            }

            $nickObj = new Nickname();
            $nickObj->setName($nickname);

            $key = Util::getPropId($prop);
            if ($key === null) {
                $key = md5($nickname);
                if (isset($map[$key])) {
                    $key = 'n' . $i++;
                }
            }

            $map[$key] = $nickObj;
        }

        if (!empty($map)) {
            $card->setNicknames($map);
        }
    }

    /**
     * Writes the grammatical gender to the vCard GRAMGENDER property in uppercase.
     *
     * @param ContactCard $card
     */
    public function setGramGender(ContactCard $card)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!is_object($speakToAs)) {
            return;
        }

        $gender = $speakToAs->getGrammaticalGender();
        if ($gender !== null && $gender !== '') {
            $this->addSingleProperty('GRAMGENDER', strtoupper($gender));
        }
    }

    /**
     * Reads the vCard GRAMGENDER property and stores it on the ContactCard in lowercase.
     * Falls back to the legacy GENDER property (vCard v3/v4) if GRAMGENDER is absent,
     * mapping M/F/N/O to the closest JSContact grammatical gender value.
     *
     * @param ContactCard $card
     */
    public function getGramGender(ContactCard $card)
    {
        $gramGender = $this->vCard->__get('GRAMGENDER');
        if (AdapterUtil::isSetAndNotNull($gramGender)) {
            $value = strtolower(trim((string) $gramGender));
            if ($value !== '') {
                $this->applyGrammaticalGenderToCard($card, $value);
                return;
            }
        }

        $gender = $this->vCard->__get('GENDER');
        if (!AdapterUtil::isSetAndNotNull($gender)) {
            return;
        }

        $raw   = trim((string) $gender);
        $parts = explode(';', $raw, 2);
        $sex   = trim($parts[0]);

        $mapped = Util::mapGenderToGrammatical($sex);
        if ($mapped !== null) {
            $this->applyGrammaticalGenderToCard($card, $mapped);
        }
    }

    /**
     * Sets grammaticalGender on the card's SpeakToAs object.
     *
     * @param ContactCard $card
     * @param string      $value  Lowercase JSContact gender value.
     */
    private function applyGrammaticalGenderToCard(ContactCard $card, $value)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!is_object($speakToAs)) {
            $speakToAs = new SpeakToAs();
        }

        if ($speakToAs) {
            $speakToAs->setGrammaticalGender($value);
            $card->setSpeakToAs($speakToAs);
        }
    }

    /**
     * Writes each set of pronouns from the ContactCard as a vCard PRONOUNS property.
     *
     * @param ContactCard $card
     */
    public function setPronouns(ContactCard $card)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!is_object($speakToAs)) {
            return;
        }

        $pronouns = $speakToAs->getPronouns();
        if (!is_array($pronouns) || empty($pronouns)) {
            return;
        }

        foreach ($pronouns as $id => $pronounObj) {
            if (!is_object($pronounObj)) {
                continue;
            }

            $value = $pronounObj->getPronouns();
            if (!is_string($value) || $value === '') {
                continue;
            }

            $params = array();
            if (method_exists($pronounObj, 'getPref') && method_exists($pronounObj, 'getContexts')) {
                $params = Util::buildContextPrefParams($pronounObj);
            } else {
                $pref = $this->prefToVcardParam($pronounObj);
                if ($pref !== null) {
                    $params['PREF'] = $pref;
                }
            }
            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add('PRONOUNS', $value, $params);
        }
    }

    /**
     * Reads all vCard PRONOUNS properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getPronouns(ContactCard $card)
    {
        $vPronouns = $this->vCard->__get('PRONOUNS');
        if (!AdapterUtil::isSetAndNotNull($vPronouns) || empty($vPronouns)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($vPronouns as $prop) {
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }

            $pronounObj = new Pronouns($value);

            if (!$pronounObj) {
                continue;
            }

            $pronounObj->setPronouns($value);
            Util::applyCommonContextAndPref($pronounObj, $prop);

            $key = Util::getMapKeyFromPropValue($prop, $value, 'pr', $idx, $map);
            $map[$key] = $pronounObj;
        }

        if (!empty($map)) {
            $speakToAs = $card->getSpeakToAs();
            if (!is_object($speakToAs)) {
                $speakToAs = new SpeakToAs();
            }

            if ($speakToAs) {
                $speakToAs->setPronouns($map);
                $card->setSpeakToAs($speakToAs);
            }
        }
    }

    /**
     * Writes each ContactCard organization as a vCard ORG property, including any department units.
     *
     * @param ContactCard $card
     */
    public function setOrganizations(ContactCard $card)
    {
        $orgs = $card->getOrganizations();
        if (!is_array($orgs) || empty($orgs)) {
            return;
        }

        foreach ($orgs as $id => $org) {
            if (!($org instanceof Organization)) {
                continue;
            }

            $name  = $org->getName();
            $units = array();

            $u = $org->getUnits();
            if (is_array($u)) {
                foreach ($u as $unitObj) {
                    if (is_object($unitObj)) {
                        $unitName = $unitObj->getName();
                        if (is_string($unitName) && $unitName !== '') {
                            $units[] = $unitName;
                        }
                    } elseif (is_string($unitObj) && $unitObj !== '') {
                        $units[] = $unitObj;
                    }
                }
            }

            $parts = array_merge(array($name), $units);

            $params = array();
            $types = $this->contextsToVcardTypeParam($org);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $params = Util::addPropIdParam($params, $id);
            $this->vCard->add('ORG', $parts, $params);
        }
    }

    /**
     * Reads vCard ORG properties and stores them on the ContactCard.
     * The first component is the org name; anything after that becomes units.
     *
     * @param ContactCard $card
     */
    public function getOrganizations(ContactCard $card)
    {
        $vOrgs = $this->vCard->ORG;
        if (!AdapterUtil::isSetAndNotNull($vOrgs) || empty($vOrgs)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($vOrgs as $vOrg) {
            if (!($vOrg instanceof Property)) {
                continue;
            }
            $parts = $vOrg->getParts();
            if (!is_array($parts) || empty($parts)) {
                $raw = trim((string) $vOrg);
                if ($raw === '') {
                    continue;
                }
                $parts = array($raw);
            }

            $org = new Organization();
            $org->setName(isset($parts[0]) ? (string) $parts[0] : '');

            $units = array();
            for ($i = 1; $i < count($parts); $i++) {
                $u = (string) $parts[$i];
                if ($u !== '') {
                    $units[] = $u;
                }
            }
            if (!empty($units)) {
                $org->setUnits($units);
            }

            $this->checkUnsupportedParams($vOrg, 'ORG');

            Util::applyCommonContextAndPref($org, $vOrg);

            $valueForKey = implode(';', $parts);
            $key = Util::getMapKeyFromPropValue($vOrg, $valueForKey, 'o', $idx, $map);
            $map[$key] = $org;
        }

        if (!empty($map)) {
            $card->setOrganizations($map);
        }
    }

    /**
     * Writes ContactCard titles to the vCard as TITLE or ROLE depending on their kind.
     *
     * @param ContactCard $card
     */
    public function setTitles(ContactCard $card)
    {
        $titles = $card->getTitles();
        if (!is_array($titles) || empty($titles)) {
            return;
        }

        foreach ($titles as $id => $titleObj) {
            if (!($titleObj instanceof Title)) {
                continue;
            }

            $kind = $titleObj->getKind();
            $name = $titleObj->getName();

            if ($name === null || $name === '') {
                continue;
            }

            $params = array();
            $params = Util::addPropIdParam($params, $id);

            if ($kind === 'role') {
                $this->vCard->add('ROLE', $name, $params);
            } else {
                $this->vCard->add('TITLE', $name, $params);
            }
        }
    }

    /**
     * Reads vCard TITLE and ROLE properties and stores them on the ContactCard.
     * TITLE gets kind "title", ROLE gets kind "role".
     *
     * @param ContactCard $card
     */
    public function getTitles(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $vTitles = $this->vCard->TITLE;
        if (AdapterUtil::isSetAndNotNull($vTitles) && !empty($vTitles)) {
            foreach ($vTitles as $vTitle) {
                $name = trim((string) $vTitle);
                if ($name === '') {
                    continue;
                }
                $t = new Title();
                $t->setKind('title');
                $t->setName($name);
                $this->checkUnsupportedParams($vTitle, 'TITLE');
                $key = Util::getMapKeyFromPropValue($vTitle, $name, 't', $idx, $map);
                $map[$key] = $t;
            }
        }

        $vRoles = $this->vCard->ROLE;
        if (AdapterUtil::isSetAndNotNull($vRoles) && !empty($vRoles)) {
            foreach ($vRoles as $vRole) {
                $name = trim((string) $vRole);
                if ($name === '') {
                    continue;
                }
                $t = new Title();
                $t->setKind('role');
                $t->setName($name);
                $this->checkUnsupportedParams($vRole, 'ROLE');
                $key = Util::getMapKeyFromPropValue($vRole, $name, 't', $idx, $map);
                $map[$key] = $t;
            }
        }

        if (!empty($map)) {
            $card->setTitles($map);
        }
    }

    /**
     * Writes each ContactCard note as a vCard NOTE property, including author and timestamp if present.
     *
     * @param ContactCard $card
     */
    public function setNotes(ContactCard $card)
    {
        $notes = $card->getNoteObjects();
        if (!is_array($notes) || empty($notes)) {
            return;
        }

        foreach ($notes as $id => $note) {
            if (!($note instanceof Note)) {
                continue;
            }

            $text = $note->getNote();
            if (is_string($text) && $text !== '') {
                $params = array();

                $created = $note->getCreated();
                if ($created !== null) {
                    $params['CREATED'] = $this->parseDateTimeToVcardTimestamp($created);
                }

                $author = $note->getAuthor();
                if (is_object($author)) {
                    $authorName = $author->getName();
                    if ($authorName !== null) {
                        $params['AUTHOR-NAME'] = $authorName;
                    }
                    $authorUri = $author->getUri();
                    if ($authorUri !== null) {
                        $params['AUTHOR'] = $authorUri;
                    }
                }
                $params = Util::addPropIdParam($params, $id);

                $this->vCard->add('NOTE', $text, $params);
            }
        }
    }

    /**
     * Reads vCard NOTE properties and stores them on the ContactCard, keeping author and timestamp.
     *
     * @param ContactCard $card
     */
    public function getNotes(ContactCard $card)
    {
        $vNotes = $this->vCard->NOTE;
        if (!AdapterUtil::isSetAndNotNull($vNotes) || empty($vNotes)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($vNotes as $prop) {
            $noteText = trim((string) $prop);
            if ($noteText === '') {
                continue;
            }

            $note = new Note();
            $note->setNote($noteText);

            if (isset($prop['CREATED'])) {
                $created = $this->parseTimestampDateTime((string) $prop['CREATED']);
                if ($created !== null) {
                    $note->setCreated($created);
                }
            }

            $hasAuthor = false;
            $author = null;

            if (isset($prop['AUTHOR-NAME']) || isset($prop['AUTHOR'])) {
                $author = new Author();
                $hasAuthor = true;
            }

            if ($hasAuthor && $author) {
                if (isset($prop['AUTHOR-NAME'])) {
                    $author->setName((string) $prop['AUTHOR-NAME']);
                }
                if (isset($prop['AUTHOR'])) {
                    $author->setUri((string) $prop['AUTHOR']);
                }
                $note->setAuthor($author);
            }
            $this->checkUnsupportedParams($prop, 'NOTE');

            $key = Util::getMapKeyFromPropValue($prop, $noteText, 'n', $i, $map);
            $map[$key] = $note;
        }

        if (!empty($map)) {
            $card->setNoteObjects($map);
        }
    }

    /**
     * Writes ContactCard email addresses as vCard EMAIL.
     *
     * @param ContactCard $card
     */
    public function setEmails(ContactCard $card)
    {
        $emails = $card->getEmails();
        if (!is_array($emails) || empty($emails)) {
            return;
        }

        foreach ($emails as $id => $email) {
            if (!($email instanceof EmailAddress)) {
                continue;
            }

            $addr = $email->getAddress();
            if (!is_string($addr) || trim($addr) === '') {
                continue;
            }

            $params = array();
            if (method_exists($email, 'getPref') && method_exists($email, 'getContexts')) {
                $params = Util::buildContextPrefParams($email);
            } else {
                // Fallback: manually build params
                $types = $this->contextsToVcardTypeParam($email);
                if (!empty($types)) {
                    $params['TYPE'] = $types;
                }
                $pref = $this->prefToVcardParam($email);
                if ($pref !== null) {
                    $params['PREF'] = $pref;
                }
            }
            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add('EMAIL', $addr, $params);
        }
    }

    /**
     * Reads vCard EMAIL properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getEmails(ContactCard $card)
    {
        $vEmails = $this->vCard->EMAIL;
        if (!AdapterUtil::isSetAndNotNull($vEmails) || empty($vEmails)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($vEmails as $prop) {
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }

            $e = new EmailAddress();
            $e->setAddress($value);

            Util::applyCommonContextAndPref($e, $prop);
            $this->checkUnsupportedParams($prop, 'EMAIL');

            $key = Util::getMapKeyFromPropValue($prop, $value, 'e', $i, $map);
            $map[$key] = $e;
        }

        if (!empty($map)) {
            $card->setEmails($map);
        }
    }

    /**
     * Writes ContactCard phone numbers as vCard TEL properties.
     * Handles Roundcube-specific labels like home2, work2, homefax, and workfax.
     *
     * @param ContactCard $card
     */
    public function setPhones($card)
    {
        $phones = $card->getPhones();
        if (!is_array($phones) || empty($phones)) {
            return;
        }

        foreach ($phones as $phone) {
            if (!($phone instanceof Phone)) {
                continue;
            }

            $number = $phone->getNumber();
            if (!is_string($number) || trim($number) === '') {
                continue;
            }

            $label = strtolower((string)$phone->getLabel());
            $roundcubeTypes = ['home2', 'work2', 'homefax', 'workfax'];

            $params = [];
            $pref = $this->prefToVcardParam($phone);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            if (in_array($label, $roundcubeTypes, true)) {
                $params['TYPE'] = [$label];
            } else {
                $types = $this->contextsToVcardTypeParam($phone);
                $features = $phone->getFeatures();
                if (is_array($features)) {
                    foreach ($features as $name => $flag) {
                        if ($flag) {
                            $types[] = ($name === 'mobile') ? 'cell' : $name;
                        }
                    }
                }
                if (!empty($types)) {
                    $params['TYPE'] = array_values(array_unique($types));
                }
            }

            $this->vCard->add('TEL', $number, $params);
        }
    }

    /**
     * Reads vCard TEL properties and stores them on the ContactCard.
     * TYPE values are mapped to contexts (home/work) and phone features (mobile, fax, etc.).
     *
     * @param ContactCard $card
     */
    public function getPhones(ContactCard $card)
    {
        $vPhones = $this->vCard->TEL;
        if (!AdapterUtil::isSetAndNotNull($vPhones) || empty($vPhones)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($vPhones as $prop) {
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }

            $p = new Phone();
            $p->setNumber($value);

            $ctx = $this->vCardTypeParamToContexts($prop);
            if (!empty($ctx)) {
                $p->setContexts($ctx);
            }

            $features = array();
            $labels   = array();

            if (isset($prop['TYPE'])) {
                $types = $prop['TYPE']->getParts();
                if (is_array($types)) {
                    foreach ($types as $t) {
                        $t = strtolower(trim((string) $t));
                        if ($t === '' || $t === 'home' || $t === 'work') {
                            continue;
                        }

                        if ($t === 'cell') {
                            $features['mobile'] = true;
                        } elseif (
                            in_array($t, array('voice', 'fax',
                            'pager', 'text', 'textphone', 'video', 'main-number'), true)
                        ) {
                            $features[$t] = true;
                        } else {
                            $labels[] = $t;
                        }
                    }
                }
            }

            if (!empty($features)) {
                $p->setFeatures($features);
            }

            if (!empty($labels)) {
                $p->setLabel(implode(', ', $labels));
            }

            $pref = $this->vCardPrefParamToInt($prop);
            if ($pref !== null) {
                $p->setPref($pref);
            }

            $this->checkUnsupportedParams($prop, 'TEL');

            $key = Util::getMapKeyFromPropValue($prop, $value, 'p', $i, $map);
            $map[$key] = $p;
        }

        if (!empty($map)) {
            $card->setPhones($map);
        }
    }

    /**
     * Writes ContactCard online services to the vCard as IMPP, SOCIALPROFILE, or URL
     * depending on the URI scheme and service name.
     *
     * @param ContactCard $card
     */
    public function setOnlineServices(ContactCard $card)
    {
        $online = $card->getOnlineServices();
        if (!is_array($online) || empty($online)) {
            return;
        }

        foreach ($online as $os) {
            if (!($os instanceof OnlineService)) {
                continue;
            }

            $service = $os->getService();
            $user    = $os->getUser();

            $value = Util::getOnlineServiceExportValue($os);
            if ($value === null || $value === '') {
                continue;
            }

            $params = array();

            if (is_string($service) && $service !== '') {
                $params['SERVICE-TYPE'] = $service;
            }

            if ($user !== null && $user !== '') {
                $params['USERNAME'] = $user;
            }

            $types = $this->contextsToVcardTypeParam($os);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($os);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $propName = Util::determineOnlinePropertyType(
                $os->getUri(),
                $os->getService()
            );

            $this->vCard->add($propName, $value, $params);
        }
    }

    /**
     * Reads vCard IMPP, SOCIALPROFILE, and URL properties and stores them as online services on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getOnlineServices(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $props = array('IMPP', 'SOCIALPROFILE', 'URL');

        foreach ($props as $propName) {
            $items = $propName === 'SOCIALPROFILE'
                ? $this->vCard->__get('SOCIALPROFILE')
                : $this->vCard->{$propName};

            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $value = trim((string) $prop);
                if ($value === '') {
                    continue;
                }

                $os = new OnlineService();

                if (isset($prop['SERVICE-TYPE'])) {
                    $os->setService((string) $prop['SERVICE-TYPE']);
                }

                $serviceType = isset($prop['SERVICE-TYPE'])
                    ? strtolower(trim((string) $prop['SERVICE-TYPE']))
                    : null;

                if (isset($prop['USERNAME'])) {
                    $os->setUser((string) $prop['USERNAME']);
                } else {
                    Util::assignOnlineValue($os, $propName, $value, $serviceType);
                }

                Util::applyCommonContextAndPref($os, $prop);

                $key = Util::getMapKeyFromPropValue($prop, $value, 'os', $idx, $map);
                $map[$key] = $os;
            }
        }

        if (!empty($map)) {
            $card->setOnlineServices($map);
        }
    }

    /**
     * Reads vCard LANG properties and stores them as preferred languages on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getPreferredLanguages(ContactCard $card)
    {
        $vLangs = $this->vCard->LANG;
        if (!AdapterUtil::isSetAndNotNull($vLangs) || empty($vLangs)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($vLangs as $prop) {
            $tag = trim((string) $prop);
            if ($tag === '') {
                continue;
            }

            $lp = new LanguagePref();
            $lp->setLanguage($tag);

            Util::applyCommonContextAndPref($lp, $prop);

            $key = Util::getMapKeyFromPropValue($prop, $tag, 'lp', $idx, $map);
            $map[$key] = $lp;
        }

        if (!empty($map)) {
            $card->setPreferredLanguages($map);
        }
    }

    /**
     * Writes ContactCard preferred languages as vCard LANG properties.
     *
     * @param ContactCard $card
     */
    public function setPreferredLanguages(ContactCard $card)
    {
        $langs = $card->getPreferredLanguages();
        if (!is_array($langs) || empty($langs)) {
            return;
        }

        foreach ($langs as $id => $lp) {
            if (!($lp instanceof LanguagePref)) {
                continue;
            }

            $tag = trim((string) $lp->getLanguage());
            if ($tag === '') {
                continue;
            }

            $params = array();
            if (method_exists($lp, 'getPref') && method_exists($lp, 'getContexts')) {
                $params = Util::buildContextPrefParams($lp);
            } else {
                // Fallback: manually build params
                $ctx = $lp->getContexts();
                if (is_array($ctx)) {
                    $types = array();
                    if (!empty($ctx['private'])) {
                        $types[] = 'home';
                    }
                    if (!empty($ctx['work'])) {
                        $types[] = 'work';
                    }
                    if (!empty($types)) {
                        $params['TYPE'] = $types;
                    }
                }
                $pref = $lp->getPref();
                if (is_int($pref) && $pref > 0) {
                    $params['PREF'] = (string) $pref;
                }
            }
            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add('LANG', $tag, $params);
        }
    }

    /**
     * Creates a JSContact Media object from a URI, kind, and optional vCard property for extra parameters.
     *
     * @param string     $uri
     * @param string     $kind  One of: photo, logo, sound.
     * @param mixed|null $prop
     * @return object|null
     */
    protected function makeMediaObject($uri, $kind, $prop = null)
    {
        return Util::createMediaObject($uri, $kind, $prop);
    }

    /**
     * Reads vCard PHOTO, LOGO, and SOUND properties and stores them as media on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getMedia(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'PHOTO' => 'photo',
            'LOGO'  => 'logo',
            'SOUND' => 'sound',
        );

        foreach ($sources as $propName => $kind) {
            $items = $this->vCard->{$propName};
            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $media = $this->makeMediaObject($uri, $kind, $prop);
                if ($media !== null) {
                    $key = Util::getMapKeyFromPropValue($prop, $uri, 'm', $idx, $map);
                    $map[$key] = $media;
                }
            }
        }

        if (!empty($map)) {
            $card->setMedia($map);
        }
    }

    /**
     * Writes ContactCard media entries as vCard PHOTO, LOGO, or SOUND properties.
     *
     * @param ContactCard $card
     */
    public function setMedia(ContactCard $card)
    {
        $mediaMap = $card->getMedia();
        if (!is_array($mediaMap) || empty($mediaMap)) {
            return;
        }

        $kindToVcard = array(
            'photo' => 'PHOTO',
            'logo'  => 'LOGO',
            'sound' => 'SOUND',
        );

        foreach ($mediaMap as $id => $media) {
            if (!is_object($media)) {
                continue;
            }

            $kind = strtolower((string) $media->getKind());

            if ($kind === null || !isset($kindToVcard[$kind])) {
                continue;
            }

            $uri = $media->getUri();

            if (!is_string($uri) || trim($uri) === '') {
                continue;
            }

            $params = $this->buildCommonUriObjectParams($media, $id);

            $this->vCard->add($kindToVcard[$kind], $uri, $params);
        }
    }

    /**
     * Reads vCard SOURCE and ORG-DIRECTORY properties and stores them as directories on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getDirectories(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'SOURCE'        => 'entry',
            'ORG-DIRECTORY' => 'directory',
        );

        foreach ($sources as $propName => $kind) {
            $items = $propName === 'ORG-DIRECTORY'
                ? $this->vCard->__get('ORG-DIRECTORY')
                : $this->vCard->{$propName};

            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $directory = new Directory($kind, $uri);

                if (isset($prop['INDEX']) && method_exists($directory, 'setListAs')) {
                    $rawIndex = trim((string) $prop['INDEX']);
                    if ($rawIndex !== '' && ctype_digit($rawIndex)) {
                        $directory->setListAs((int) $rawIndex);
                    }
                }

                if (isset($prop['MEDIATYPE'])) {
                    $directory->setMediaType((string) $prop['MEDIATYPE']);
                }

                Util::applyCommonContextAndPref($directory, $prop);

                $key = Util::getMapKeyFromPropValue($prop, $uri, 'd', $idx, $map);
                $map[$key] = $directory;
            }
        }

        if (!empty($map)) {
            $card->setDirectories($map);
        }
    }

    /**
     * Writes ContactCard directories as vCard SOURCE or ORG-DIRECTORY properties.
     *
     * @param ContactCard $card
     */
    public function setDirectories(ContactCard $card)
    {
        $directories = $card->getDirectories();
        if (!is_array($directories) || empty($directories)) {
            return;
        }

        foreach ($directories as $id => $dir) {
            if (!is_object($dir)) {
                continue;
            }

            $kind = strtolower((string) $dir->getKind());
            $uri = $dir->getUri();

            if ($uri === null || $uri === '') {
                continue;
            }

            $propName = null;
            if ($kind === 'entry') {
                $propName = 'SOURCE';
            } elseif ($kind === 'directory') {
                $propName = 'ORG-DIRECTORY';
            }

            if ($propName === null) {
                continue;
            }

            $params = $this->buildCommonUriObjectParams($dir, $id);

            // Add INDEX parameter if present
            if (method_exists($dir, 'getListAs')) {
                $listAs = $dir->getListAs();
                if (is_int($listAs)) {
                    $params['INDEX'] = (string) $listAs;
                }
            }

            $this->vCard->add($propName, $uri, $params);
        }
    }

    /**
     * Reads vCard URL and CONTACT-URI properties and stores them as links on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getLinks(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'URL'         => null,
            'CONTACT-URI' => 'contact',
        );

        foreach ($sources as $propName => $kind) {
            $items = $propName === 'CONTACT-URI'
                ? $this->vCard->__get('CONTACT-URI')
                : $this->vCard->{$propName};

            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                 $link = new Link($uri);

                if (!$link) {
                    continue;
                }

                $link->setUri($uri);

                if ($kind !== null) {
                    $link->setKind($kind);
                }

                if (isset($prop['MEDIATYPE'])) {
                    $link->setMediaType((string) $prop['MEDIATYPE']);
                }

                Util::applyCommonContextAndPref($link, $prop);

                $key = Util::getMapKeyFromPropValue($prop, $uri, 'l', $idx, $map);
                $map[$key] = $link;
            }
        }

        if (!empty($map)) {
            $card->setLinks($map);
        }
    }

    /**
     * Writes ContactCard links as vCard URL or CONTACT-URI properties.
     *
     * @param ContactCard $card
     */
    public function setLinks(ContactCard $card)
    {
        $links = $card->getLinks();
        if (!is_array($links) || empty($links)) {
            return;
        }

        foreach ($links as $id => $link) {
            if (!is_object($link)) {
                continue;
            }

            $uri = $link->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $kind = strtolower((string) $link->getKind());
            $propName = ($kind === 'contact') ? 'CONTACT-URI' : 'URL';

            $params = $this->buildCommonUriObjectParams($link, $id);

            $this->vCard->add($propName, $uri, $params);
        }
    }

    /**
     * Reads vCard KEY properties and stores them as crypto keys on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getCryptoKeys(ContactCard $card)
    {
        $items = $this->vCard->KEY;
        if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($items as $prop) {
            $uri = trim((string) $prop);
            if ($uri === '') {
                continue;
            }

            $key = new CryptoKey($uri);

            if (isset($prop['MEDIATYPE'])) {
                $key->setMediaType((string) $prop['MEDIATYPE']);
            }

            Util::applyCommonContextAndPref($key, $prop);

            $mapKey = Util::getMapKeyFromPropValue($prop, $uri, 'k', $idx, $map);
            $map[$mapKey] = $key;
        }

        if (!empty($map)) {
            $card->setCryptoKeys($map);
        }
    }

    /**
     * Writes ContactCard crypto keys as vCard KEY properties.
     *
     * @param ContactCard $card
     */
    public function setCryptoKeys(ContactCard $card)
    {
        $keys = $card->getCryptoKeys();
        if (!is_array($keys) || empty($keys)) {
            return;
        }

        foreach ($keys as $id => $key) {
            if (!is_object($key)) {
                continue;
            }

            $uri = $key->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $params = $this->buildCommonUriObjectParams($key, $id);

            $this->vCard->add('KEY', $uri, $params);
        }
    }

    /**
     * Reads vCard CALADRURI property and stores it as scheduling address on the ContactCard.
     *
     * CALADRURI entries have no kind (calendar invitation address).
     *
     * @param ContactCard $card
     */
    public function getSchedulingAddresses(ContactCard $card)
    {
        $items = $this->vCard->__get('CALADRURI');
        if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($items as $prop) {
            $uri = trim((string) $prop);
            if ($uri === '') {
                continue;
            }

            $sched = new SchedulingAddress();

            if (!$sched) {
                continue;
            }

            $sched->setUri($uri);

            Util::applyCommonContextAndPref($sched, $prop);

            $key = Util::getMapKeyFromPropValue($prop, $uri, 'sa', $idx, $map);
            $map[$key] = $sched;
        }

        if (!empty($map)) {
            $card->setSchedulingAddresses($map);
        }
    }

    /**
     * Writes ContactCard scheduling addresses as vCard CALADRURI properties.
     *
     * @param ContactCard $card
     */
    public function setSchedulingAddresses(ContactCard $card)
    {
        $schedules = $card->getSchedulingAddresses();
        if (!is_array($schedules) || empty($schedules)) {
            return;
        }

        foreach ($schedules as $id => $sched) {
            if (!is_object($sched)) {
                continue;
            }

            $uri = $sched->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $params = array();

            $types = $this->contextsToVcardTypeParam($sched);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($sched);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add('CALADRURI', $uri, $params);
        }
    }

    /**
     * Reads vCard CALURI and FBURL properties and stores them as calendars on the ContactCard.
     *
     * CALURI entries get kind 'calendar'.
     * FBURL entries get kind 'freeBusy'.
     *
     * @param ContactCard $card
     */
    public function getCalendars(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        // Each entry is [vCard property name, JSContact kind].
        $sources = array(
            array('CALURI', 'calendar'),
            array('FBURL',  'freeBusy'),
        );

        foreach ($sources as list($propName, $kind)) {
            $items = $this->vCard->__get($propName);
            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $calendar = new Calendar();

                if (!$calendar) {
                    continue;
                }

                $calendar->setKind($kind);
                $calendar->setUri($uri);

                if (isset($prop['MEDIATYPE'])) {
                    $calendar->setMediaType((string) $prop['MEDIATYPE']);
                }

                Util::applyCommonContextAndPref($calendar, $prop);

                $key = Util::getMapKeyFromPropValue($prop, $uri, 'cal', $idx, $map);
                $map[$key] = $calendar;
            }
        }

        if (!empty($map)) {
            $card->setCalendars($map);
        }
    }

    /**
     * Writes ContactCard calendars as vCard CALURI or FBURL properties.
     *
     * @param ContactCard $card
     */
    public function setCalendars(ContactCard $card)
    {
        $calendars = $card->getCalendars();
        if (!is_array($calendars) || empty($calendars)) {
            return;
        }

        foreach ($calendars as $id => $calendar) {
            if (!is_object($calendar)) {
                continue;
            }

            $uri = $calendar->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $kind = strtolower(trim((string) $calendar->getKind()));
            if ($kind === 'freebusy') {
                $propName = 'FBURL';
            } elseif ($kind === 'calendar') {
                $propName = 'CALURI';
            } else {
                continue;
            }

            $params = array();

            $mt = $calendar->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }

            $types = $this->contextsToVcardTypeParam($calendar);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($calendar);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add($propName, $uri, $params);
        }
    }

    /**
     * Writes ADR property with JSCOMPS parameter reconstructed from ordered components.
     *
     * @param Address $address
     * @param array $components
     * @param string $id
     */
    protected function setAddressWithJscomps($address, $components, $id)
    {
        $kindToPosition = Util::getAddressKindToPositionMap();
        $defaultSep = '';

        if (method_exists($address, 'getDefaultSeparator')) {
            $sep = $address->getDefaultSeparator();
            if ($sep !== null && $sep !== '') {
                $defaultSep = $sep;
            }
        }

        list($parts, $jscompsValue) = Util::buildJscompsData(
            $components,
            $kindToPosition,
            $defaultSep,
            17
        );

        $params = array('JSCOMPS' => $jscompsValue);

        $fullAddr = $address->getFullAddress();
        if ($fullAddr) {
            $params['LABEL'] = $fullAddr;
        }

        $countryCode = $address->getCountryCode();
        if ($countryCode) {
            $params['CC'] = $countryCode;
        }

        $coordinates = $address->getCoordinates();
        if ($coordinates) {
            $params['GEO'] = $coordinates;
        }

        $timeZone = $address->getTimeZone();
        if ($timeZone) {
            $params['TZ'] = $timeZone;
        }

        $types = $this->contextsToVcardTypeParam($address);
        if (!empty($types)) {
            $params['TYPE'] = $types;
        }

        $pref = $this->prefToVcardParam($address);
        if ($pref !== null) {
            $params['PREF'] = $pref;
        }

        $params = Util::addPropIdParam($params, $id);

        $this->vCard->add('ADR', $parts, $params);
    }

    /**
     * Writes ContactCard addresses as vCard ADR properties.
     * @param ContactCard $card
     */
    public function setAddresses(ContactCard $card)
    {
        $addresses = $card->getAddresses();
        if (!is_array($addresses)) {
            return;
        }

        foreach ($addresses as $id => $address) {
            if (!($address instanceof Address)) {
                continue;
            }

            $timeZone      = $address->getTimeZone();
            $components    = $address->getComponents();
            $fullAddr      = $address->getFullAddress();
            $coordinates   = $address->getCoordinates();
            $countryCode   = $address->getCountryCode();
            $hasComponents = is_array($components) && !empty($components);

            // Standalone timezone property
            if ($timeZone && !$hasComponents && !$fullAddr && !$coordinates && !$countryCode) {
                $this->addSingleProperty('TZ', $timeZone);
                continue;
            }

            $isOrdered = method_exists($address, 'getIsOrdered') ? $address->getIsOrdered() : false;

            if ($isOrdered && $hasComponents) {
                $this->setAddressWithJscomps($address, $components, $id);
                continue;
            }

            // Initialize 17 ADR parts (RFC 9554 extended format)
            $parts = array_fill(0, 17, '');

            // Component kind to index
            $kindToIndex = Util::getAddressKindToPositionMap();

            if ($hasComponents) {
                foreach ($components as $comp) {
                    if (!is_object($comp)) {
                        continue;
                    }

                    $kind = $comp->getKind();
                    $value = $comp->getValue();

                    if (isset($kindToIndex[$kind]) && is_string($value) && $value !== '') {
                        $parts[$kindToIndex[$kind]] = $value;
                    }
                }
            }

            // if no components mapped and we have fullAddr, use it as street
            $hasAny = (bool) array_filter($parts);
            if (!$hasAny && $fullAddr) {
                $parts[2] = $fullAddr;
            }

            $params = array();

            if ($countryCode) {
                $params['CC'] = $countryCode;
            }
            if ($coordinates) {
                $params['GEO'] = $coordinates;
            }
            if ($timeZone) {
                $params['TZ'] = $timeZone;
            }
            if ($fullAddr) {
                $params['LABEL'] = $fullAddr;
            }

            $types = $this->contextsToVcardTypeParam($address);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($address);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $params = Util::addPropIdParam($params, $id);

            $this->vCard->add('ADR', $parts, $params);
        }
    }

    /**
     * Reads vCard ADR properties and stores them as addresses on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getAddresses(ContactCard $card)
    {
        $vAddrs = $this->vCard->ADR;
        if (!AdapterUtil::isSetAndNotNull($vAddrs) || empty($vAddrs)) {
            return;
        }

        $map = array();
        $i = 1;

        foreach ($vAddrs as $vAddr) {
            if (!($vAddr instanceof Property)) {
                continue;
            }

            $parts = $vAddr->getParts();
            if (!AdapterUtil::isSetAndNotNull($parts) || empty($parts)) {
                continue;
            }

            $a = new Address();

            if (isset($vAddr['LABEL'])) {
                $a->setFullAddress((string) $vAddr['LABEL']);
            }

            $hasJscomps = isset($vAddr['JSCOMPS']);

            if ($hasJscomps) {
                $jscompsValue = (string) $vAddr['JSCOMPS'];
                $positionToKind = Util::getAddressPositionToKindMap();

                $components = Util::parseJscompsData(
                    $jscompsValue,
                    $parts,
                    $positionToKind,
                    new AddressComponent()
                );

                if (!empty($components)) {
                    $a->setComponents($components);
                    $a->setIsOrdered(true);

                    $defaultSep = Util::getDefaultSeparatorFromJscomps($jscompsValue);
                    if ($defaultSep !== null) {
                        $a->setDefaultSeparator($defaultSep);
                    }
                }
            } else {
                $isExtended = count($parts) > 7;
                $positionToKind = $isExtended
                    ? Util::getAddressPositionToKindMap()
                    : Util::getBasicAddressPositionToKindMap();

                $components = Util::buildComponentsFromParts(
                    $parts,
                    $positionToKind,
                    new AddressComponent()
                );

                if (!empty($components)) {
                    $a->setComponents($components);
                    $a->setIsOrdered(true);
                    $a->setDefaultSeparator(', ');
                }
            }

            // Generate fullAddress if not set
            if ($a->getFullAddress() === null) {
                $comps = $a->getComponents();
                if (!empty($comps)) {
                    $fullParts = array();
                    foreach ($comps as $comp) {
                        $val = $comp->getValue();
                        if ($val !== null && $val !== '') {
                            $fullParts[] = $val;
                        }
                    }
                    if (!empty($fullParts)) {
                        $a->setFullAddress(implode(', ', $fullParts));
                    }
                }
            }

            if (isset($vAddr['CC'])) {
                $a->setCountryCode((string) $vAddr['CC']);
            }
            if (isset($vAddr['GEO'])) {
                $a->setCoordinates((string) $vAddr['GEO']);
            }
            if (isset($vAddr['TZ'])) {
                $a->setTimeZone((string) $vAddr['TZ']);
            }

            $ctx = $this->vCardTypeParamToContexts($vAddr);
            if (!empty($ctx)) {
                $a->setContexts($ctx);
            }

            $pref = $this->vCardPrefParamToInt($vAddr);
            if ($pref !== null) {
                $a->setPref($pref);
            }
            $this->checkUnsupportedParams($vAddr, 'ADR');

            $key = Util::getMapKeyFromPropValue($vAddr, json_encode($parts), 'a', $i, $map);
            $map[$key] = $a;
        }

        $standaloneTz = $this->vCard->__get('TZ');
        if (AdapterUtil::isSetAndNotNull($standaloneTz)) {
            $tzValue = trim((string) $standaloneTz);
            if ($tzValue !== '') {
                $a = new Address();
                $a->setTimeZone($tzValue);
                $map['a' . $i++] = $a;
            }
        }

        if (!empty($map)) {
            $card->setAddresses($map);
        }
    }

    /**
     * Anniversaries
     * Returns the BDAY date as Y-m-d, or '0000-00-00' if it's missing or can't be parsed.
     * Handles both plain dates and date-time values like 19950505T000000Z.
     */
    protected function getBirthday()
    {
        $bday = $this->vCard->BDAY;
        if (!AdapterUtil::isSetAndNotNull($bday)) {
            return '0000-00-00';
        }

        $raw = trim((string) $bday);
        if ($raw === '') {
            return '0000-00-00';
        }

        $utc = $this->parseDateTimeToJscontactUtc($raw);
        if ($utc !== null) {
            return substr($utc, 0, 10);
        }

        $jmap = AdapterUtil::parseDateTime($raw, 'Y-m-d', 'Y-m-d', 'Ymd');
        return $jmap === null ? '0000-00-00' : $jmap;
    }

    /**
     * Writes a Y-m-d birthday to the vCard BDAY property.
     */
    protected function setBirthday($birthday)
    {
        $vDate = $this->parseDateToVcardDate($birthday);
        if ($vDate !== null) {
            $this->addSingleProperty('BDAY', $vDate, array('VALUE' => 'date'));
        }
    }

    /**
     * Returns the ANNIVERSARY date as Y-m-d, or '0000-00-00' if it's missing or can't be parsed.
     * Handles both plain dates and date-time values like 20051010T000000Z.
     */
    protected function getAnniversary()
    {
        $ann = $this->vCard->__get('ANNIVERSARY');
        if (!AdapterUtil::isSetAndNotNull($ann)) {
            return '0000-00-00';
        }

        $raw = trim((string) $ann);
        if ($raw === '') {
            return '0000-00-00';
        }

        $utc = $this->parseDateTimeToJscontactUtc($raw);
        if ($utc !== null) {
            return substr($utc, 0, 10);
        }

        $jmap = AdapterUtil::parseDateTime($raw, 'Ymd', 'Y-m-d', 'Y-m-d');
        return $jmap === null ? '0000-00-00' : $jmap;
    }

    /**
     * Writes a Y-m-d anniversary to the vCard ANNIVERSARY property.
     */
    protected function setAnniversary($anniversary)
    {
        $vDate = $this->parseDateToVcardDate($anniversary);
        if ($vDate !== null) {
            $this->addSingleProperty('ANNIVERSARY', $vDate, array('VALUE' => 'date'));
        }
    }

    /**
     * Returns the raw BIRTHPLACE value, or null if it's not set.
     * Unescapes any literal \n sequences in the value.
     */
    protected function getBirthPlaceRaw()
    {
        $p = $this->vCard->__get('BIRTHPLACE');
        if (!AdapterUtil::isSetAndNotNull($p)) {
            return null;
        }
        $raw = str_replace("\\n", "\n", (string) $p);
        $raw = trim($raw);
        return $raw === '' ? null : $raw;
    }

    /**
     * Returns the DEATHDATE as Y-m-d, or '0000-00-00' if it's missing or can't be parsed.
     */
    protected function getDeathDate()
    {
        $p = $this->vCard->__get('DEATHDATE');
        if (!AdapterUtil::isSetAndNotNull($p)) {
            return '0000-00-00';
        }

        $raw = trim((string) $p);
        if ($raw === '') {
            return '0000-00-00';
        }

        $utc = $this->parseDateTimeToJscontactUtc($raw);
        if ($utc !== null) {
            return substr($utc, 0, 10);
        }

        $jmap = AdapterUtil::parseDateTime($raw, 'Y-m-d', 'Y-m-d', 'Ymd');
        return $jmap === null ? '0000-00-00' : $jmap;
    }

    /**
     * Writes a Y-m-d death date to the vCard DEATHDATE property.
     */
    protected function setDeathDate($deathDate)
    {
        $vDate = $this->parseDateToVcardDate($deathDate);
        if ($vDate !== null) {
            $this->addSingleProperty('DEATHDATE', $vDate, array('VALUE' => 'date'));
        }
    }

    /**
     * Returns the raw DEATHPLACE value, or null if it's not set.
     *
     * @return string|null
     */
    protected function getDeathPlaceRaw()
    {
        $p = $this->vCard->__get('DEATHPLACE');
        if (!AdapterUtil::isSetAndNotNull($p)) {
            return null;
        }
        $raw = str_replace("\\n", "\n", (string) $p);
        $raw = trim($raw);
        return $raw === '' ? null : $raw;
    }

    /**
     * Converts a raw place string (plain text or geo: URI) to a JSContact Address object.
     *
     * @param string $raw
     * @return Address|null
     */
    protected function placeRawToAddress($raw)
    {
        return Util::placeToAddress($raw, $this->placeTextAsFullAddress);
    }

    /**
     * Pulls the place value (coordinates or text) out of a JSContact Address so it can be
     * written to a BIRTHPLACE or DEATHPLACE vCard property.
     *
     * @param Address $addr
     * @return string|null
     */
    protected function getPlaceValueFromAddress(Address $addr)
    {
        return Util::addressToPlace($addr, $this->placeTextAsFullAddress);
    }

    /**
     * Writes a JSContact Address to the vCard BIRTHPLACE property.
     *
     * @param Address $addr
     */
    protected function setBirthPlaceFromAddress(Address $addr)
    {
        $value = $this->getPlaceValueFromAddress($addr);
        if ($value !== null) {
            $this->addSingleProperty('BIRTHPLACE', $value);
        }
    }

    /**
     * Writes a JSContact Address to the vCard DEATHPLACE property.
     *
     * @param Address $addr
     */
    protected function setDeathPlaceFromAddress(Address $addr)
    {
        $value = $this->getPlaceValueFromAddress($addr);
        if ($value !== null) {
            $this->addSingleProperty('DEATHPLACE', $value);
        }
    }

    /**
     * Writes ContactCard anniversaries to the vCard as BDAY, BIRTHPLACE, DEATHDATE, DEATHPLACE, and ANNIVERSARY.
     * Only explicitly recognized anniversary kinds are exported.
     *
     * @param ContactCard $card
     */
    public function setAnniversaries(ContactCard $card)
    {
        $anns = $card->getAnniversaries();
        if (!is_array($anns) || empty($anns)) {
            return;
        }

        $birth   = null;
        $death   = null;
        $wedding = null;

        foreach ($anns as $ann) {
            if (!($ann instanceof Anniversary)) {
                continue;
            }

            $kind  = strtolower(trim((string) $ann->getKind()));
            $label = strtolower(trim((string) $ann->getLabel()));

            if ($kind === 'birth' && $birth === null) {
                $birth = $ann;
            } elseif ($kind === 'death' && $death === null) {
                $death = $ann;
            } elseif (
                $wedding === null
                && (
                    $kind === 'wedding'
                    || ($kind === 'other' && in_array($label, array('wedding',
                    'marriage', 'marriage date', 'anniversary'), true))
                )
            ) {
                $wedding = $ann;
            }
        }

        if ($birth instanceof Anniversary) {
            $this->setBirthday($birth->getDate());
            $place = $birth->getPlace();
            if ($place instanceof Address) {
                $this->setBirthPlaceFromAddress($place);
            }
        }

        if ($death instanceof Anniversary) {
            $this->setDeathDate($death->getDate());
            $place = $death->getPlace();
            if ($place instanceof Address) {
                $this->setDeathPlaceFromAddress($place);
            }
        }

        if ($this->mapVcardAnniversaryToWedding && $wedding instanceof Anniversary) {
            $this->setAnniversary($wedding->getDate());
        }
    }

    /**
     * Reads vCard BDAY, BIRTHPLACE, DEATHDATE, DEATHPLACE, and ANNIVERSARY and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getAnniversaries(ContactCard $card)
    {
        $anns = array();

        $bday   = $this->getBirthday();
        $bplace = $this->getBirthPlaceRaw();
        if ($bday !== '0000-00-00' || $bplace !== null) {
            $a = new Anniversary();
            $a->setKind('birth');
            if ($bday !== '0000-00-00') {
                $a->setDate($bday);
            }
            if ($bplace !== null) {
                $addr = $this->placeRawToAddress($bplace);
                if ($addr instanceof Address) {
                    $a->setPlace($addr);
                }
            }
            $anns[] = $a;
        }

        $ddate  = $this->getDeathDate();
        $dplace = $this->getDeathPlaceRaw();
        if ($ddate !== '0000-00-00' || $dplace !== null) {
            $a = new Anniversary();
            $a->setKind('death');
            if ($ddate !== '0000-00-00') {
                $a->setDate($ddate);
            }
            if ($dplace !== null) {
                $addr = $this->placeRawToAddress($dplace);
                if ($addr instanceof Address) {
                    $a->setPlace($addr);
                }
            }
            $anns[] = $a;
        }

        if ($this->mapVcardAnniversaryToWedding) {
            $anniv = $this->getAnniversary();
            if ($anniv !== '0000-00-00') {
                $a = new Anniversary();
                $a->setKind('wedding');
                $a->setLabel('anniversary');
                $a->setDate($anniv);
                $anns[] = $a;
            }
        }

        if (!empty($anns)) {
            $card->setAnniversaries($anns);
        }
    }

    /**
     * Writes ContactCard relations as vCard RELATED properties, with relation types as TYPE parameters.
     *
     * @param ContactCard $card
     */
    public function setRelatedTo(ContactCard $card)
    {
        $relatedTo = $card->getRelatedTo();
        if (!is_array($relatedTo) || empty($relatedTo)) {
            return;
        }

        foreach ($relatedTo as $key => $relationObj) {
            if ($key === null || $key === '') {
                continue;
            }

            if (!is_object($relationObj)) {
                $this->vCard->add('RELATED', $key);
                continue;
            }

            $relationMap = $relationObj->getRelation();
            $types       = array();

            if (is_array($relationMap)) {
                foreach ($relationMap as $type => $flag) {
                    if ($flag) {
                        $types[] = $type;
                    }
                }
            }

            if (empty($types)) {
                $this->vCard->add('RELATED', $key);
            } else {
                $this->vCard->add('RELATED', $key, array('TYPE' => $types));
            }
        }
    }

    /**
     * Reads vCard RELATED properties and stores them as relations on the ContactCard.
     * TYPE parameters become the relation type keys.
     *
     * @param ContactCard $card
     */
    public function getRelatedTo(ContactCard $card)
    {
        $vRelated = $this->vCard->RELATED;
        if (!AdapterUtil::isSetAndNotNull($vRelated) || empty($vRelated)) {
            return;
        }

        $relatedMap = array();

        foreach ($vRelated as $rel) {
            $key = trim((string) $rel);
            if ($key === '') {
                continue;
            }

            $relationTypes = array();
            if (isset($rel['TYPE'])) {
                $typeParts = $rel['TYPE']->getParts();
                if (is_array($typeParts)) {
                    foreach ($typeParts as $type) {
                        if ($type !== '' && $type !== null) {
                            $relationTypes[$type] = true;
                        }
                    }
                }
            }

            $relationObj = new Relation();
            $relationObj->setRelation($relationTypes);
            $relatedMap[$key] = $relationObj;
        }

        if (!empty($relatedMap)) {
            $card->setRelatedTo($relatedMap);
        }
    }

    /**
     * Writes ContactCard group members as vCard MEMBER properties and sets KIND to "group".
     *
     * @param ContactCard $card
     */
    public function setMembers(ContactCard $card)
    {
        $members = $card->getMembers();
        if (!is_array($members) || empty($members)) {
            return;
        }

        $wroteMember = false;
        foreach ($members as $uid => $flag) {
            if ($uid === null || $uid === '' || $flag !== true) {
                continue;
            }

            $this->vCard->add('MEMBER', $uid, array('VALUE' => 'uri'));
            $wroteMember = true;
        }

        if ($wroteMember) {
            $this->addSingleProperty('KIND', 'group');
        }
    }

    /**
     * Reads vCard MEMBER properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getMembers(ContactCard $card)
    {
        if (!is_string($this->rawVCard) || $this->rawVCard === '') {
            return;
        }

        $members = array();
        $vcf     = preg_replace("/\r\n[ \t]/", '', $this->rawVCard);

        if (preg_match_all('/^MEMBER(?:;[^:]*)?:(.+)$/im', $vcf, $matches)) {
            foreach ($matches[1] as $value) {
                $uri = trim($value);
                if ($uri === '') {
                    continue;
                }
                $members[$uri] = true;
            }
        }

        if (!empty($members)) {
            $card->setMembers($members);
        }
    }

    /**
     * Reads vCard CATEGORIES properties and stores them as keywords on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getKeywords(ContactCard $card)
    {
        $vCats = $this->vCard->CATEGORIES;
        if (!AdapterUtil::isSetAndNotNull($vCats) || empty($vCats)) {
            return;
        }

        $keywords = array();

        foreach ($vCats as $catProp) {
            if (!($catProp instanceof Property)) {
                continue;
            }
            $parts = $catProp->getParts();
            if (!is_array($parts) || empty($parts)) {
                $val = trim((string) $catProp);
                if ($val !== '') {
                    $keywords[$val] = true;
                }
                continue;
            }

            foreach ($parts as $p) {
                $p = trim((string) $p);
                if ($p !== '') {
                    $keywords[$p] = true;
                }
            }
        }

        if (!empty($keywords)) {
            $card->setKeywords($keywords);
        }
    }

    /**
     * Writes ContactCard keywords as a single vCard CATEGORIES property with all values combined.
     *
     * @param ContactCard $card
     */
    public function setKeywords(ContactCard $card)
    {
        $keywords = $card->getKeywords();
        if (!is_array($keywords) || empty($keywords)) {
            return;
        }

        $values = array();
        foreach ($keywords as $kw => $flag) {
            if ($flag && is_string($kw) && $kw !== '') {
                $values[] = $kw;
            }
        }

        if (!empty($values)) {
            $this->vCard->add('CATEGORIES', $values);
        }
    }
    /**
     * Reads vCard EXPERTISE, HOBBY, and INTEREST properties and stores them on the ContactCard.
     * LEVEL values are mapped: beginner -> low, average/medium -> medium, expert -> high.
     *
     * @param ContactCard $card
     */
    public function getPersonalInfo(ContactCard $card)
    {
        $info = array();

        $readProps = function ($propName, $kind) use (&$info) {
            $props = $this->vCard->{$propName};
            if (!AdapterUtil::isSetAndNotNull($props) || empty($props)) {
                return;
            }

            foreach ($props as $prop) {
                $value = trim((string) $prop);
                if ($value === '') {
                    continue;
                }

                $level = null;
                if (isset($prop['LEVEL'])) {
                    $level = Util::mapLevelFromVcard((string) $prop['LEVEL']);
                }

                $pi = new PersonalInformation($kind, $value);

                if ($level !== null) {
                    $pi->setLevel($level);
                }

                if (isset($prop['INDEX'])) {
                    $rawIndex = trim((string) $prop['INDEX']);
                    if ($rawIndex !== '' && ctype_digit($rawIndex)) {
                        $pi->setListAs((int) $rawIndex);
                    }
                }

                $info[] = $pi;
            }
        };

        $readProps('EXPERTISE', 'expertise');
        $readProps('HOBBY', 'hobby');
        $readProps('INTEREST', 'interest');

        if (!empty($info)) {
            $card->setPersonalInfo($info);
        }
    }

    /**
     * Writes ContactCard personal info entries as vCard EXPERTISE, HOBBY, or INTEREST properties.
     * LEVEL values are mapped back: low -> beginner, medium -> average, high -> expert.
     *
     * @param ContactCard $card
     */
    public function setPersonalInfo(ContactCard $card)
    {
        $info = $card->getPersonalInfo();
        if (!is_array($info) || empty($info)) {
            return;
        }

        foreach ($info as $pi) {
            if (!($pi instanceof PersonalInformation)) {
                continue;
            }

            $kind  = $pi->getKind();
            $value = $pi->getValue();
            if (!is_string($value) || $value === '') {
                continue;
            }

            if ($kind === 'expertise') {
                $propName = 'EXPERTISE';
            } elseif ($kind === 'hobby') {
                $propName = 'HOBBY';
            } elseif ($kind === 'interest') {
                $propName = 'INTEREST';
            } else {
                continue;
            }

            $params = array();

            $level = $pi->getLevel();
            if (is_string($level) && $level !== '') {
                $mapped = Util::mapLevelToVcard($level);
                if ($mapped !== null) {
                    $params['LEVEL'] = $mapped;
                }
            }

            $idx = $pi->getListAs();
            if (is_int($idx) && $idx >= 0) {
                $params['INDEX'] = (string) $idx;
            }

            $this->vCard->add($propName, $value, $params);
        }
    }

    /**
     * Builds common vCard parameters for objects that have mediaType, contexts, pref.
     * Used by media, directories, links, and crypto keys.
     *
     * @param object $obj The object to extract parameters from
     * @param mixed $id The map key/id for PROP-ID parameter
     * @return array<string, mixed> The vCard parameters
     */
    protected function buildCommonUriObjectParams($obj, $id = null)
    {
        $params = array();

        // MediaType
        if (method_exists($obj, 'getMediaType')) {
            $mt = $obj->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }
        }

        // Contexts (TYPE parameter)
        $types = $this->contextsToVcardTypeParam($obj);
        if (!empty($types)) {
            $params['TYPE'] = $types;
        }

        // Preference
        $pref = $this->prefToVcardParam($obj);
        if ($pref !== null) {
            $params['PREF'] = $pref;
        }

        // PROP-ID
        if ($id !== null) {
            $params = Util::addPropIdParam($params, $id);
        }

        return $params;
    }

     /* Check if the currently unsupported vCard parameter ALTID is present
     * If yes, then provide an error log with some information that it is not supported
     * TODO : Implement support for ALTID and LANGUAGE parameters in the future
     * */
    protected function checkUnsupportedParams($prop, $propName)
    {
        if (isset($prop['ALTID']) && !empty($prop['ALTID'])) {
            $this->logger->error(
                "Currently unsupported vCard Parameter ALTID encountered for vCard property {$propName}"
            );
        }

        if (isset($prop['LANGUAGE']) && !empty($prop['LANGUAGE'])) {
            $this->logger->error(
                "Currently unsupported vCard Parameter LANGUAGE encountered for vCard property {$propName}"
            );
        }
    }
}
