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
use OpenXPort\Jmap\JSContact\AddressComponent;
use OpenXPort\Jmap\JSContact\Anniversary;
use OpenXPort\Jmap\JSContact\Relation;
use OpenXPort\Jmap\JSContact\LanguagePref;
use OpenXPort\Jmap\JSContact\PersonalInformation;
use OpenXPort\Jmap\JSContact\Directory;
use OpenXPort\Util\AdapterUtil;
use OpenXPort\Util\Logger;
use Sabre\VObject;

/**
 * Converts contacts back and forth between vCard and JSContact formats.
 *
 * Handles the following contact fields in both directions:
 *   uid, updated (REV), created, prodId, kind, language, name (N), fullName (FN),
 *   nicknames, grammaticalGender, pronouns, organizations, titles, notes, emails,
 *   phones, onlineServices (IMPP / SOCIALPROFILE), preferredLanguages, media (PHOTO),
 *   directories (SOURCE / ORG-DIRECTORY), links (URL / CONTACT-URI), cryptoKeys (KEY),
 *   schedulingAddresses (FBURL / CALADRURI / CALURI), addresses (ADR), anniversaries
 *   (BDAY / ANNIVERSARY), relatedTo, members, keywords (CATEGORIES), and personalInfo
 *   (EXPERTISE / HOBBY / INTEREST).
 *
 * Follows RFC 9555 for conversion rules and RFC 9554 for extended vCard properties.
 */
class JSContactVCardAdapter extends AbstractAdapter
{
    protected $vcard;
    protected $logger;
    protected $rawVCard;

    /** @var array<int, string> Property names found in the current vCard. */
    protected $vCardChildren = array();

    /** @var array<string, mixed> Extra fields that don't fit in vCard or JSContact directly. */
    protected $oxpProperties = array();

    /** @var bool When true, plain text birth/death places are kept as a full address string. */
    protected $placeTextAsFullAddress = true;

    /** @var bool When true, vCard ANNIVERSARY is treated as a wedding anniversary. */
    protected $mapVcardAnniversaryToWedding = true;

    protected $addressBookId = null;

    /** @var string How strict the vCard parser should be (strict / ignoreInvalidLines / ignoreInvalidVCards). */
    protected $parsingConfig;

    /** @var bool Whether to dump broken vCards to the log for debugging. */
    protected $dumpInvalidVCards;

    /**
     * Sets up the adapter and configures how the vCard parser handles bad input.
     *
     * @param string|null $parsingConfig
     * @param bool|null   $dumpInvalidVCards
     */
    public function __construct($parsingConfig = 'strict', $dumpInvalidVCards = false)
    {
        $this->vcard = new VObject\Component\VCard();
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
     * Wipes the current vCard and all stored state so the adapter can be reused.
     */
    public function reset()
    {
        $this->vcard = new VObject\Component\VCard();
        $this->rawVCard = null;
        $this->vCardChildren = array();
        $this->oxpProperties = array();
    }

    /**
     * Returns the current vCard as a string, or null if nothing is loaded.
     *
     * @return string|null
     */
    public function getContact()
    {
        if (!AdapterUtil::isSetAndNotNull($this->vcard)) {
            return null;
        }
        return (string) $this->vcard->serialize();
    }

    /**
     * Loads a vCard from a string.
     *
     * @param string $vCardString
     */
    public function setContact($vCardString)
    {
        $this->rawVCard = $vCardString;

        try {
            $this->vcard = VObject\Reader::read($vCardString);
        } catch (VObject\ParseException $e) {
            $this->setBrokenVCard($vCardString, $e);
        }
    }

    /**
     * Handles a vCard that failed to parse, according to the configured $parsingConfig mode.
     *
     * 'strict' - rethrows the exception immediately.
     * 'ignoreInvalidLines' - retries with OPTION_IGNORE_INVALID_LINES; rethrows if it still fails.
     * 'ignoreInvalidVCards' - retries with OPTION_IGNORE_INVALID_LINES; silently sets vcard to null if it still fails.
     *
     * @param string                  $vCardString The raw vCard that failed to parse.
     * @param VObject\ParseException  $e           The original parse exception.
     */
    protected function setBrokenVCard($vCardString, VObject\ParseException $e)
    {
        switch ($this->parsingConfig) {
            case 'strict':
                $this->handleVCardDump($vCardString);
                throw $e;

            case 'ignoreInvalidLines':
                try {
                    $this->vcard = VObject\Reader::read(
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
                    $this->vcard = VObject\Reader::read(
                        $vCardString,
                        VObject\Reader::OPTION_IGNORE_INVALID_LINES
                    );
                } catch (VObject\ParseException $ignored) {
                    $this->handleVCardDump($vCardString);
                    $this->vcard = null;
                }
                break;

            default:
                $this->handleVCardDump($vCardString);
                throw $e;
        }
    }

    /**
     * Same as getContact() - returns the current vCard as a string.
     *
     * @return string|null
     */
    public function getVCard()
    {
        return $this->getContact();
    }

    /**
     * Same as setContact() - loads a vCard from a string.
     *
     * @param string $vCardString
     */
    public function setVCard($vCardString)
    {
        $this->setContact($vCardString);
    }

    /**
     * Returns the adapter contents as an array with a 'vCard' key.
     *
     * @return array
     */
    public function getAsHash()
    {
        return array(
            'vCard' => $this->getContact(),
            'oxpProperties' => array(
                'addressBookId' => $this->addressBookId,
            ),
        );
    }

    /**
     * Loads the adapter from an array previously returned by getAsHash().
     *
     * @param array $cHash
     */
    public function setFromHash($cHash)
    {
        if (!is_array($cHash)) {
            return;
        }

        if (isset($cHash['vCard']) && is_string($cHash['vCard'])) {
            $this->setContact($cHash['vCard']);
        }

        if (isset($cHash['oxpProperties']['addressBookId'])) {
            $this->addressBookId = $cHash['oxpProperties']['addressBookId'];
        }
    }

    /**
     * Returns the UID of the current vCard, or null if it isn't set.
     *
     * @return string|null
     */
    public function getUid()
    {
        $uid = isset($this->vcard->UID) ? $this->vcard->UID : null;
        if (!AdapterUtil::isSetAndNotNull($uid)) {
            return null;
        }

        $value = trim((string) $uid);
        return $value !== '' ? $value : null;
    }

    /**
     * Returns the address book ID, logging a warning if none has been set.
     *
     * @return string|null
     */
    public function getAddressBookId()
    {
        if ($this->addressBookId === null) {
            $this->logger->warning(
                "addressBookId does not exist for card " . $this->getUid()
            );
        }

        return $this->addressBookId;
    }

    /**
     * Sets the address book ID on this adapter.
     *
     * @param string|null $addressBookId
     */
    public function setAddressBookId($addressBookId)
    {
        $this->addressBookId = $addressBookId;
    }

    /**
     * Logs the full vCard string if the dump option is on - handy for debugging bad input.
     *
     * @param string $vCardString
     */
    protected function handleVCardDump($vCardString)
    {
        if (!$this->dumpInvalidVCards) {
            return;
        }

        $this->logger->warning("Dumping vCard:\n$vCardString");
    }

    // Internal helpers
    /**
     * Writes a vCard property, removing any existing copies of it first so there's never more than one.
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $params
     */
    protected function addSingleProperty($name, $value, array $params = array())
    {
        if (isset($this->vcard->{$name})) {
            foreach ($this->vcard->{$name} as $prop) {
                $this->vcard->remove($prop);
            }
        }

        $this->vcard->add($name, $value, $params);
    }

    /**
     * Reads the TYPE parameter off a vCard property and turns it into JSContact contexts.
     * "home" becomes "private" and "work" stays "work".
     *
     * @param mixed $prop
     * @return array<string, true>
     */
    protected function vcardTypeParamToContexts($prop)
    {
        $contexts = array();

        if (isset($prop['TYPE'])) {
            $types = $prop['TYPE']->getParts();
            if (is_array($types)) {
                foreach ($types as $t) {
                    $t = strtolower(trim((string) $t));
                    if ($t === 'home') {
                        $contexts['private'] = true;
                    } elseif ($t === 'work') {
                        $contexts['work'] = true;
                    }
                }
            }
        }

        return $contexts;
    }

    /**
     * Reads the PREF parameter off a vCard property and returns it as an integer.
     * Returns null if it's missing, empty, or not a positive number.
     *
     * @param mixed $prop
     * @return int|null
     */
    protected function vcardPrefParamToInt($prop)
    {
        if (!isset($prop['PREF'])) {
            return null;
        }

        $raw = trim((string) $prop['PREF']);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $n = (int) $raw;
        return $n > 0 ? $n : null;
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
        $types = array();

        if (is_object($obj)) {
            $ctx = $obj->getContexts();
            if (is_array($ctx)) {
                if (!empty($ctx['private'])) {
                    $types[] = 'home';
                }
                if (!empty($ctx['work'])) {
                    $types[] = 'work';
                }
            }
        }

        return $types;
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
        if (!is_object($obj)) {
            return null;
        }

        $pref = $obj->getPref();
        if ($pref === null) {
            return null;
        }

        $pref = (int) $pref;
        return $pref > 0 ? (string) $pref : null;
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
        if (!is_string($value) || trim($value) === '' || $value === '0000-00-00') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Y-m-d', 'Ymd');
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
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Y-m-d\TH:i:s\Z', 'Ymd\THis\Z');
    }

    /**
     * Converts a vCard TIMESTAMP to a JSContact UTC timestamp string.
     * Returns null for empty input.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseTimestampToJmapDateTime($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Ymd\THis\Z', 'Y-m-d\TH:i:s\Z');
    }

    /**
     * Tries several common date/time formats to convert a vCard date value into a JSContact UTC string.
     * Returns null if none of the formats match.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function parseDateTimeToJscontactUtc($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $formats = array(
            'Ymd\THis\Z',
            'Y-m-d\TH:i:s\Z',
            'Ymd\THis',
            'Y-m-d\TH:i:s',
            'Ymd',
            'Y-m-d',
        );

        foreach ($formats as $from) {
            $parsed = AdapterUtil::parseDateTime($value, $from, 'Y-m-d\TH:i:s\Z');
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
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
        $props = $this->vcard->{$name};

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
        $prop = $this->vcard->{$name};
        if (!AdapterUtil::isSetAndNotNull($prop)) {
            return null;
        }

        $value = trim((string) $prop);
        return $value === '' ? null : $value;
    }

    /**
     * Tries to create an object from a list of class names, returning the first one that exists.
     * Useful for safely supporting optional JSContact model classes.
     *
     * @param array<int, string> $classNames
     * @return object|null
     */
    protected function instantiateJscontactObject(array $classNames, array $constructorArgs = [])
    {
        foreach ($classNames as $className) {
            if (class_exists($className)) {
                return new $className(...$constructorArgs);
            }
        }

        return null;
    }

    /**
     * Copies the context (TYPE) and preference (PREF) from a vCard property onto a JSContact object.
     *
     * @param object $obj
     * @param mixed  $prop
     */
    protected function applyCommonContextAndPref($obj, $prop)
    {
        if (!is_object($obj) || $prop === null) {
            return;
        }

        $ctx = $this->vcardTypeParamToContexts($prop);
        if (!empty($ctx)) {
            $obj->setContexts($ctx);
        }

        $pref = $this->vcardPrefParamToInt($prop);
        if ($pref !== null) {
            $obj->setPref($pref);
        }
    }
    // Name helpers
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
    protected function setName($lastName, $firstName, $middleName, $prefix, $suffix)
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
        $n = $this->vcard->N;
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
        $n = $this->vcard->N;
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
        $n = $this->vcard->N;
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
        $n = $this->vcard->N;
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
        $n = $this->vcard->N;
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
        $fn = $this->vcard->FN;
        if (AdapterUtil::isSetAndNotNull($fn) && !empty($fn)) {
            return (string) $fn;
        }
        return null;
    }
    // Core identity
    /**
     * Writes the ContactCard UID to the vCard UID property.
     *
     * @param ContactCard $card
     */
    public function setUidFromJmap(ContactCard $card)
    {
        $uid = $card->getUid();
        if (is_string($uid) && $uid !== '') {
            $this->addSingleProperty('UID', $uid);
        }
    }

    /**
     * Reads the vCard UID and stores it on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getUidToJmap(ContactCard $card)
    {
        $uid = $this->vcard->UID;
        if (AdapterUtil::isSetAndNotNull($uid)) {
            $value = trim((string) $uid);
            if ($value !== '') {
                $card->setUid($value);
            }
        }
    }

    /**
     * Writes the ContactCard's last-modified timestamp to the vCard REV property.
     *
     * @param ContactCard $card
     */
    public function setUpdatedFromJmap(ContactCard $card)
    {
        $updated = $card->getUpdated();
        $vRev = $this->parseDateTimeToVcardTimestamp($updated);
        if ($vRev !== null) {
            $this->addSingleProperty('REV', $vRev);
        }
    }

    /**
     * Reads the vCard REV timestamp and stores it as the ContactCard's updated date.
     *
     * @param ContactCard $card
     */
    public function getUpdatedToJmap(ContactCard $card)
    {
        $rev = $this->vcard->REV;
        if (!AdapterUtil::isSetAndNotNull($rev)) {
            return;
        }

        $value = trim((string) $rev);
        $parsed = $this->parseTimestampToJmapDateTime($value);
        if ($parsed !== null) {
            $card->setUpdated($parsed);
        }
    }

    /**
     * Writes the ContactCard creation timestamp to the vCard CREATED property.
     *
     * @param ContactCard $card
     */
    public function setCreatedFromJmap(ContactCard $card)
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
    public function getCreatedToJmap(ContactCard $card)
    {
        $created = $this->vcard->__get('CREATED');
        if (!AdapterUtil::isSetAndNotNull($created)) {
            return;
        }

        $value = trim((string) $created);
        if ($value === '') {
            return;
        }

        $parsed = $this->parseTimestampToJmapDateTime($value);
        if ($parsed !== null) {
            $card->setCreated($parsed);
        }
    }

    /**
     * Writes the ContactCard product ID to the vCard PRODID property.
     *
     * @param ContactCard $card
     */
    public function setProdIdFromJmap(ContactCard $card)
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
    public function getProdIdToJmap(ContactCard $card)
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
    public function setKindFromJmap(ContactCard $card)
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
    public function getKindToJmap(ContactCard $card)
    {
        $kind = $this->vcard->KIND;
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
    public function setLanguageFromJmap(ContactCard $card)
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
    public function getLanguageToJmap(ContactCard $card)
    {
        $value = $this->getSinglePropertyValue('LANGUAGE');
        if ($value !== null) {
            $card->setLanguage($value);
        }
    }
    // Name
    /**
     * Writes the vCard N property from the name components on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function setNameFromJmap(ContactCard $card)
    {
        $name   = $card->getName();
        $family = null;
        $given  = null;
        $middle = null;
        $prefix = null;
        $suffix = null;

        if ($name instanceof Name) {
            $components = $name->getComponents();
            if (is_array($components)) {
                foreach ($components as $component) {
                    $kind  = $component->getKind();
                    $value = $component->getValue();

                    switch ($kind) {
                        case 'surname':
                            $family = $value;
                            break;
                        case 'given':
                            $given  = $value;
                            break;
                        case 'given2':
                            $middle = $value;
                            break;
                        case 'title':
                            $prefix = $value;
                            break;
                        case 'credential':
                            $suffix = $value;
                            break;
                    }
                }
            }
        }

        $this->setName($family, $given, $middle, $prefix, $suffix);
    }

    /**
     * Writes the vCard FN from name.full on the ContactCard.
     * Falls back to joining given, middle, and surname if name.full is empty.
     *
     * @param ContactCard $card
     */
    public function setFnFromJmap(ContactCard $card)
    {
        $name = $card->getName();
        $full = ($name instanceof Name) ? $name->getFull() : null;

        if (($full === null || $full === '') && $name instanceof Name) {
            $components = $name->getComponents();
            if (is_array($components)) {
                $given   = array();
                $middle  = array();
                $surname = array();

                foreach ($components as $component) {
                    $kind  = $component->getKind();
                    $value = $component->getValue();
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if ($kind === 'given') {
                        $given[] = $value;
                    } elseif ($kind === 'given2') {
                        $middle[] = $value;
                    } elseif ($kind === 'surname') {
                        $surname[] = $value;
                    }
                }

                $parts = array_merge($given, $middle, $surname);
                if (!empty($parts)) {
                    $full = implode(' ', $parts);
                }
            }
        }

        if ($full !== null && $full !== '') {
            $this->setDisplayname($full);
        }
    }

    /**
     * Reads the vCard N and FN properties and builds the ContactCard name with all its components.
     *
     * @param ContactCard $card
     */
    public function getNameToJmap(ContactCard $card)
    {
        $family = $this->getLastName();
        $given  = $this->getFirstName();
        $middle = $this->getMiddlename();
        $prefix = $this->getPrefix();
        $suffix = $this->getSuffix();
        $fn     = $this->getDisplayname();

        $name = new Name();

        if ($fn !== null && $fn !== '') {
            $name->setFull($fn);
        }

        $components = array();

        if ($prefix) {
            $c = new NameComponent();
            $c->setKind('title');
            $c->setValue($prefix);
            $components[] = $c;
        }
        if ($given) {
            $c = new NameComponent();
            $c->setKind('given');
            $c->setValue($given);
            $components[] = $c;
        }
        if ($middle) {
            $c = new NameComponent();
            $c->setKind('given2');
            $c->setValue($middle);
            $components[] = $c;
        }
        if ($family) {
            $c = new NameComponent();
            $c->setKind('surname');
            $c->setValue($family);
            $components[] = $c;
        }
        if ($suffix) {
            $c = new NameComponent();
            $c->setKind('credential');
            $c->setValue($suffix);
            $components[] = $c;
        }

        if (!empty($components)) {
            $name->setIsOrdered(true);
            $name->setComponents($components);
        }

        $card->setName($name);
    }

    /**
     * Writes each ContactCard nickname as a separate vCard NICKNAME property.
     *
     * @param ContactCard $card
     */
    public function setNicknameFromJmap(ContactCard $card)
    {
        $nicks = $card->getNicknames();
        if (!is_array($nicks) || empty($nicks)) {
            return;
        }

        foreach ($nicks as $nick) {
            if (!($nick instanceof Nickname)) {
                continue;
            }

            $name = $nick->getName();
            if (is_string($name) && $name !== '') {
                $this->vcard->add('NICKNAME', $name);
            }
        }
    }

    /**
     * Reads all vCard NICKNAME properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getNicknameToJmap(ContactCard $card)
    {
        $values = $this->getPropertyValues('NICKNAME');
        if (empty($values)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($values as $nickname) {
            $nickObj = new Nickname();
            $nickObj->setName($nickname);
            $map['n' . $i++] = $nickObj;
        }

        $card->setNicknames($map);
    }
    // Speaking (grammatical gender / pronouns)
    /**
     * Writes the grammatical gender to the vCard GRAMGENDER property in uppercase.
     *
     * @param ContactCard $card
     */
    public function setGramGenderFromJmap(ContactCard $card)
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
    public function getGramGenderToJmap(ContactCard $card)
    {
        $gramGender = $this->vcard->__get('GRAMGENDER');
        if (AdapterUtil::isSetAndNotNull($gramGender)) {
            $value = strtolower(trim((string) $gramGender));
            if ($value !== '') {
                $this->applyGrammaticalGenderToCard($card, $value);
                return;
            }
        }

        $gender = $this->vcard->__get('GENDER');
        if (!AdapterUtil::isSetAndNotNull($gender)) {
            return;
        }

        $raw   = trim((string) $gender);
        $parts = explode(';', $raw, 2);
        $sex   = strtolower(trim($parts[0]));

        $mapping = array(
            'm'      => 'male',
            'male'   => 'male',
            'f'      => 'female',
            'female' => 'female',
            'n'      => 'neuter',
            'neuter' => 'neuter',
            'o'      => 'animate',   // "other" - closest JSContact value
            'other'  => 'animate',
        );

        if (isset($mapping[$sex])) {
            $this->applyGrammaticalGenderToCard($card, $mapping[$sex]);
        }
    }

    /**
     * Sets grammaticalGender on the card's SpeakToAs object, creating it if needed.
     *
     * @param ContactCard $card
     * @param string      $value  Lowercase JSContact gender value.
     */
    private function applyGrammaticalGenderToCard(ContactCard $card, $value)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!is_object($speakToAs)) {
            $speakToAs = $this->instantiateJscontactObject(array(
                'OpenXPort\\Jmap\\JSContact\\SpeakToAs',
            ));
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
    public function setPronounsFromJmap(ContactCard $card)
    {
        $speakToAs = $card->getSpeakToAs();
        if (!is_object($speakToAs)) {
            return;
        }

        $pronouns = $speakToAs->getPronouns();
        if (!is_array($pronouns) || empty($pronouns)) {
            return;
        }

        foreach ($pronouns as $pronounObj) {
            if (!is_object($pronounObj)) {
                continue;
            }

            $value = $pronounObj->getPronouns();
            if (!is_string($value) || $value === '') {
                continue;
            }

            $params = array();
            $pref = $this->prefToVcardParam($pronounObj);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add('PRONOUNS', $value, $params);
        }
    }

    /**
     * Reads all vCard PRONOUNS properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getPronounsToJmap(ContactCard $card)
    {
        $vPronouns = $this->vcard->__get('PRONOUNS');
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

            $pronounObj = $this->instantiateJscontactObject(array(
                'OpenXPort\\Jmap\\JSContact\\Pronouns',
            ));

            if (!$pronounObj) {
                continue;
            }

            $pronounObj->setPronouns($value);

            $pref = $this->vcardPrefParamToInt($prop);
            if ($pref !== null) {
                $pronounObj->setPref($pref);
            }

            $map['pr' . $idx++] = $pronounObj;
        }

        if (!empty($map)) {
            $speakToAs = $card->getSpeakToAs();
            if (!is_object($speakToAs)) {
                $speakToAs = $this->instantiateJscontactObject(array(
                    'OpenXPort\\Jmap\\JSContact\\SpeakToAs',
                ));
            }

            if ($speakToAs) {
                $speakToAs->setPronouns($map);
                $card->setSpeakToAs($speakToAs);
            }
        }
    }
    // Organization
    /**
     * Writes each ContactCard organization as a vCard ORG property, including any department units.
     *
     * @param ContactCard $card
     */
    public function setOrganizationFromJmap(ContactCard $card)
    {
        $orgs = $card->getOrganizations();
        if (!is_array($orgs) || empty($orgs)) {
            return;
        }

        foreach ($orgs as $org) {
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

            $this->vcard->add('ORG', $parts, $params);
        }
    }

    /**
     * Reads vCard ORG properties and stores them on the ContactCard.
     * The first component is the org name; anything after that becomes units.
     *
     * @param ContactCard $card
     */
    public function getOrganizationToJmap(ContactCard $card)
    {
        $vOrgs = $this->vcard->ORG;
        if (!AdapterUtil::isSetAndNotNull($vOrgs) || empty($vOrgs)) {
            return;
        }

        $map = array();
        $idx = 1;

        foreach ($vOrgs as $vOrg) {
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

            $ctx = $this->vcardTypeParamToContexts($vOrg);
            if (!empty($ctx)) {
                $org->setContexts($ctx);
            }

            $map['o' . $idx++] = $org;
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
    public function setTitlesFromJmap(ContactCard $card)
    {
        $titles = $card->getTitles();
        if (!is_array($titles) || empty($titles)) {
            return;
        }

        foreach ($titles as $titleObj) {
            if (!($titleObj instanceof Title)) {
                continue;
            }

            $kind = $titleObj->getKind();
            $name = $titleObj->getName();

            if ($name === null || $name === '') {
                continue;
            }

            if ($kind === 'role') {
                $this->vcard->add('ROLE', $name);
            } else {
                $this->vcard->add('TITLE', $name);
            }
        }
    }

    /**
     * Reads vCard TITLE and ROLE properties and stores them on the ContactCard.
     * TITLE gets kind "title", ROLE gets kind "role".
     *
     * @param ContactCard $card
     */
    public function getTitlesToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $vTitles = $this->vcard->TITLE;
        if (AdapterUtil::isSetAndNotNull($vTitles) && !empty($vTitles)) {
            foreach ($vTitles as $vTitle) {
                $name = trim((string) $vTitle);
                if ($name === '') {
                    continue;
                }
                $t = new Title();
                $t->setKind('title');
                $t->setName($name);
                $map['t' . $idx++] = $t;
            }
        }

        $vRoles = $this->vcard->ROLE;
        if (AdapterUtil::isSetAndNotNull($vRoles) && !empty($vRoles)) {
            foreach ($vRoles as $vRole) {
                $name = trim((string) $vRole);
                if ($name === '') {
                    continue;
                }
                $t = new Title();
                $t->setKind('role');
                $t->setName($name);
                $map['t' . $idx++] = $t;
            }
        }

        if (!empty($map)) {
            $card->setTitles($map);
        }
    }
    // Notes
    /**
     * Writes each ContactCard note as a vCard NOTE property, including author and timestamp if present.
     *
     * @param ContactCard $card
     */
    public function setNotesFromJmap(ContactCard $card)
    {
        $notes = $card->getNoteObjects();
        if (!is_array($notes) || empty($notes)) {
            return;
        }

        foreach ($notes as $note) {
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

                $this->vcard->add('NOTE', $text, $params);
            }
        }
    }

    /**
     * Reads vCard NOTE properties and stores them on the ContactCard, keeping author and timestamp.
     *
     * @param ContactCard $card
     */
    public function getNotesToJmap(ContactCard $card)
    {
        $vNotes = $this->vcard->NOTE;
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
                $created = $this->parseTimestampToJmapDateTime((string) $prop['CREATED']);
                if ($created !== null) {
                    $note->setCreated($created);
                }
            }

            $hasAuthor = false;
            $author = null;

            if (isset($prop['AUTHOR-NAME']) || isset($prop['AUTHOR'])) {
                $author = $this->instantiateJscontactObject(array(
                    'OpenXPort\\Jmap\\JSContact\\Author',
                ));
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

            $map['n' . $i++] = $note;
        }

        if (!empty($map)) {
            $card->setNoteObjects($map);
        }
    }
    // Communications
    /**
     * Writes ContactCard email addresses as vCard EMAIL properties, preserving context and preference.
     *
     * @param ContactCard $card
     */
    public function setEmailsFromJmap(ContactCard $card)
    {
        $emails = $card->getEmails();
        if (!is_array($emails) || empty($emails)) {
            return;
        }

        foreach ($emails as $email) {
            if (!($email instanceof EmailAddress)) {
                continue;
            }

            $addr = $email->getAddress();
            if (!is_string($addr) || trim($addr) === '') {
                continue;
            }

            $params = array();

            $types = $this->contextsToVcardTypeParam($email);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($email);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add('EMAIL', $addr, $params);
        }
    }

    /**
     * Reads vCard EMAIL properties and stores them on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getEmailsToJmap(ContactCard $card)
    {
        $vEmails = $this->vcard->EMAIL;
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

            $this->applyCommonContextAndPref($e, $prop);

            $map['e' . $i++] = $e;
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
    public function setPhonesFromJmap($card)
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

            if (in_array($label, $roundcubeTypes, true)) {
                $params = ['TYPE' => [$label]];

                $pref = $this->prefToVcardParam($phone);
                if ($pref !== null) {
                    $params['PREF'] = $pref;
                }

                $this->vcard->add('TEL', $number, $params);
            } else {
                $params = [];
                $types = $this->contextsToVcardTypeParam($phone);

                $features = $phone->getFeatures();
                if (is_array($features)) {
                    foreach ($features as $name => $flag) {
                        if (!$flag) {
                            continue;
                        }
                        $name = strtolower((string)$name);
                        if (
                            in_array($name, ['voice', 'fax',
                            'pager', 'text', 'textphone', 'video', 'main-number'], true)
                        ) {
                            $types[] = $name;
                        } elseif ($name === 'mobile') {
                            $types[] = 'cell';
                        }
                    }
                }

                $types = array_values(array_unique($types));
                if (!empty($types)) {
                    $params['TYPE'] = $types;
                }

                $pref = $this->prefToVcardParam($phone);
                if ($pref !== null) {
                    $params['PREF'] = $pref;
                }

                $this->vcard->add('TEL', $number, $params);
            }
        }
    }

    /**
     * Reads vCard TEL properties and stores them on the ContactCard.
     * TYPE values are mapped to contexts (home/work) and phone features (mobile, fax, etc.).
     *
     * @param ContactCard $card
     */
    public function getPhonesToJmap(ContactCard $card)
    {
        $vPhones = $this->vcard->TEL;
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

            $ctx = $this->vcardTypeParamToContexts($prop);
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

            $pref = $this->vcardPrefParamToInt($prop);
            if ($pref !== null) {
                $p->setPref($pref);
            }

            $map['p' . $i++] = $p;
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
    public function setOnlineFromJmap(ContactCard $card)
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

            $value = $this->determineOnlineExportValue($os);
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

            $propName = $this->determineOnlinePropertyType(
                $os->getUri(),
                $os->getService()
            );

            $this->vcard->add($propName, $value, $params);
        }
    }

    /**
     * Picks whether an online service should go into IMPP, SOCIALPROFILE, or URL.
     * IMPP is used for instant messaging URIs, SOCIALPROFILE for known social networks, URL for everything else.
     *
     * @param string|null $uri
     * @param string|null $service
     * @return string
     */
    private function determineOnlinePropertyType($uri, $service)
    {
        if ($uri !== null && $uri !== '') {
            $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
            $imppSchemes = array(
                'xmpp', 'sip', 'sips', 'tel', 'aim', 'msnim', 'ymsgr', 'skype', 'irc'
            );
            if (in_array($scheme, $imppSchemes, true)) {
                return 'IMPP';
            }
        }

        if ($service !== null && $service !== '') {
            $socialServices = array(
                'facebook', 'twitter', 'x', 'linkedin', 'instagram', 'mastodon',
                'github', 'gitlab', 'reddit', 'youtube', 'tiktok', 'snapchat',
                'pinterest', 'flickr', 'vimeo', 'twitch', 'discord', 'telegram',
                'whatsapp', 'signal', 'matrix', 'bluesky', 'threads'
            );
            if (in_array(strtolower((string) $service), $socialServices, true)) {
                return 'SOCIALPROFILE';
            }
        }

        return 'URL';
    }

    protected function determineOnlineExportValue(OnlineService $os)
    {
        $uri = $os->getUri();
        $user = $os->getUser();
        $service = strtolower(trim((string) $os->getService()));

        if (in_array($service, ['aim', 'jabber', 'xmpp', 'sip'], true)) {
            if (is_string($uri) && $uri !== '') {
                return $uri;
            }
            if (is_string($user) && $user !== '') {
                return $user;
            }
        }

        if (in_array($service, ['skype', 'icq', 'msn', 'yahoo'], true)) {
            if (is_string($user) && $user !== '') {
                return $user;
            }
            if (is_string($uri) && $uri !== '') {
                return $uri;
            }
        }

        if (is_string($uri) && $uri !== '') {
            return $uri;
        }

        if (is_string($user) && $user !== '') {
            return $user;
        }

        return null;
    }

    /**
     * Reads vCard IMPP, SOCIALPROFILE, and URL properties and stores them as online services on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getOnlineToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $props = array('IMPP', 'SOCIALPROFILE', 'URL');

        foreach ($props as $propName) {
            $items = $propName === 'SOCIALPROFILE'
                ? $this->vcard->__get('SOCIALPROFILE')
                : $this->vcard->{$propName};

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

                if (isset($prop['USERNAME'])) {
                    $os->setUser((string) $prop['USERNAME']);
                } else {
                    $this->assignOnlineValueToObject($os, $propName, $value, $prop);
                }

                $this->applyCommonContextAndPref($os, $prop);

                $map['os' . $idx++] = $os;
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
    public function getPreferredLanguagesToJmap(ContactCard $card)
    {
        $vLangs = $this->vcard->LANG;
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

            $this->applyCommonContextAndPref($lp, $prop);

            $map['lp' . $idx++] = $lp;
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
    public function setPreferredLanguagesFromJmap(ContactCard $card)
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

            $this->vcard->add('LANG', $tag, $params);
        }
    }
    // Media, directories, links, security, calendar
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
        $className = 'OpenXPort\\Jmap\\JSContact\\Media';

        if (!class_exists($className)) {
            return null;
        }

        $media = new $className($kind);
        $media->setUri($uri);

        if ($prop !== null && isset($prop['MEDIATYPE'])) {
            $media->setMediaType((string) $prop['MEDIATYPE']);
        }

        $this->applyCommonContextAndPref($media, $prop);

        return $media;
    }

    /**
     * Reads vCard PHOTO, LOGO, and SOUND properties and stores them as media on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getMediaToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'PHOTO' => 'photo',
            'LOGO'  => 'logo',
            'SOUND' => 'sound',
        );

        foreach ($sources as $propName => $kind) {
            $items = $this->vcard->{$propName};
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
                    $map['m' . $idx++] = $media;
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
    public function setMediaFromJmap(ContactCard $card)
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

        foreach ($mediaMap as $media) {
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

            $params = array();

            $mt = $media->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }

            $types = $this->contextsToVcardTypeParam($media);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($media);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add($kindToVcard[$kind], $uri, $params);
        }
    }

    /**
     * Reads vCard SOURCE and ORG-DIRECTORY properties and stores them as directories on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getDirectoriesToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'SOURCE'        => 'entry',
            'ORG-DIRECTORY' => 'directory',
        );

        foreach ($sources as $propName => $kind) {
            $items = $propName === 'ORG-DIRECTORY'
                ? $this->vcard->__get('ORG-DIRECTORY')
                : $this->vcard->{$propName};

            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $directory = $this->instantiateJscontactObject(array(
                    'OpenXPort\\Jmap\\JSContact\\Directory',
                ));

                if (!$directory) {
                    continue;
                }

                $directory->setKind($kind);
                $directory->setUri($uri);

                if (isset($prop['INDEX']) && method_exists($directory, 'setListAs')) {
                    $rawIndex = trim((string) $prop['INDEX']);
                    if ($rawIndex !== '' && ctype_digit($rawIndex)) {
                        $directory->setListAs((int) $rawIndex);
                    }
                }

                if (isset($prop['MEDIATYPE'])) {
                    $directory->setMediaType((string) $prop['MEDIATYPE']);
                }

                $this->applyCommonContextAndPref($directory, $prop);

                $map['d' . $idx++] = $directory;
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
    public function setDirectoriesFromJmap(ContactCard $card)
    {
        $directories = $card->getDirectories();
        if (!is_array($directories) || empty($directories)) {
            return;
        }

        foreach ($directories as $dir) {
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

            $params = array();

            if (method_exists($dir, 'getListAs')) {
                $listAs = $dir->getListAs();
                if (is_int($listAs)) {
                    $params['INDEX'] = (string) $listAs;
                }
            }

            $mt = $dir->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }

            $types = $this->contextsToVcardTypeParam($dir);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($dir);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add($propName, $uri, $params);
        }
    }

    /**
     * Reads vCard URL and CONTACT-URI properties and stores them as links on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getLinksToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        $sources = array(
            'URL'         => null,
            'CONTACT-URI' => 'contact',
        );

        foreach ($sources as $propName => $kind) {
            $items = $propName === 'CONTACT-URI'
                ? $this->vcard->__get('CONTACT-URI')
                : $this->vcard->{$propName};

            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $link = $this->instantiateJscontactObject(
                    array('OpenXPort\\Jmap\\JSContact\\Link'),
                    array($uri)
                );

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

                $this->applyCommonContextAndPref($link, $prop);

                $map['l' . $idx++] = $link;
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
    public function setLinksFromJmap(ContactCard $card)
    {
        $links = $card->getLinks();
        if (!is_array($links) || empty($links)) {
            return;
        }

        foreach ($links as $link) {
            if (!is_object($link)) {
                continue;
            }

            $uri = $link->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $kind = strtolower((string) $link->getKind());
            $propName = ($kind === 'contact') ? 'CONTACT-URI' : 'URL';

            $params = array();

            $mt = $link->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }

            $types = $this->contextsToVcardTypeParam($link);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($link);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add($propName, $uri, $params);
        }
    }

    /**
     * Reads vCard KEY properties and stores them as crypto keys on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getCryptoKeysToJmap(ContactCard $card)
    {
        $items = $this->vcard->KEY;
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

            $key = $this->instantiateJscontactObject(array(
                'OpenXPort\\Jmap\\JSContact\\CryptoKey',
            ));

            if (!$key) {
                continue;
            }

            $key->setUri($uri);

            if (isset($prop['MEDIATYPE'])) {
                $key->setMediaType((string) $prop['MEDIATYPE']);
            }

            $this->applyCommonContextAndPref($key, $prop);

            $map['k' . $idx++] = $key;
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
    public function setCryptoKeysFromJmap(ContactCard $card)
    {
        $keys = $card->getCryptoKeys();
        if (!is_array($keys) || empty($keys)) {
            return;
        }

        foreach ($keys as $key) {
            if (!is_object($key)) {
                continue;
            }

            $uri = $key->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $params = array();

            $mt = $key->getMediaType();
            if (is_string($mt) && $mt !== '') {
                $params['MEDIATYPE'] = $mt;
            }

            $types = $this->contextsToVcardTypeParam($key);
            if (!empty($types)) {
                $params['TYPE'] = $types;
            }

            $pref = $this->prefToVcardParam($key);
            if ($pref !== null) {
                $params['PREF'] = $pref;
            }

            $this->vcard->add('KEY', $uri, $params);
        }
    }

    /**
     * Reads vCard CALADRURI, FBURL, and CALURI properties and stores them as scheduling
     * addresses on the ContactCard.
     *
     * CALADRURI entries get no kind (calendar invitation address).
     * FBURL entries get kind 'freeBusy' (free-busy URL).
     * CALURI entries get kind 'calendar' (calendar subscription URI).
     *
     * @param ContactCard $card
     */
    public function getSchedulingAddressesToJmap(ContactCard $card)
    {
        $map = array();
        $idx = 1;

        // Each entry is [vCard property name, JSContact kind or null].
        $sources = array(
            array('CALADRURI', null),
            array('FBURL',     'freeBusy'),
            array('CALURI',    'calendar'),
        );

        foreach ($sources as list($propName, $kind)) {
            $items = $this->vcard->__get($propName);
            if (!AdapterUtil::isSetAndNotNull($items) || empty($items)) {
                continue;
            }

            foreach ($items as $prop) {
                $uri = trim((string) $prop);
                if ($uri === '') {
                    continue;
                }

                $sched = $this->instantiateJscontactObject(array(
                    'OpenXPort\\Jmap\\JSContact\\SchedulingAddress',
                ));

                if (!$sched) {
                    continue;
                }

                $sched->setUri($uri);

                if ($kind !== null) {
                    $sched->setKind($kind);
                }

                $this->applyCommonContextAndPref($sched, $prop);

                $map['sa' . $idx++] = $sched;
            }
        }

        if (!empty($map)) {
            $card->setSchedulingAddresses($map);
        }
    }

    /**
     * Writes ContactCard scheduling addresses as vCard properties.
     *
     * @param ContactCard $card
     */
    public function setSchedulingAddressesFromJmap(ContactCard $card)
    {
        $schedules = $card->getSchedulingAddresses();
        if (!is_array($schedules) || empty($schedules)) {
            return;
        }

        foreach ($schedules as $sched) {
            if (!is_object($sched)) {
                continue;
            }

            $uri = $sched->getUri();
            if ($uri === null || $uri === '') {
                continue;
            }

            $kind = strtolower(trim((string) $sched->getKind()));
            if ($kind === 'freebusy') {
                $propName = 'FBURL';
            } elseif ($kind === 'calendar') {
                $propName = 'CALURI';
            } else {
                $propName = 'CALADRURI';
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

            $this->vcard->add($propName, $uri, $params);
        }
    }
    // Addresses
    /**
     * Writes ContactCard addresses as vCard ADR properties.
     * Uses the extended component layout from RFC 9554.
     *
     * A timezone-only address (label = 'timezone', no components, no full address) is
     * written as a standalone vCard TZ property rather than an empty ADR line, matching
     * the behaviour expected by clients that emit bare TZ properties.
     *
     * @param ContactCard $card
     */
    public function setAddressesFromJmap(ContactCard $card)
    {
        $addresses = $card->getAddresses();
        if (!is_array($addresses)) {
            return;
        }

        foreach ($addresses as $address) {
            if (!($address instanceof Address)) {
                continue;
            }

            $timeZone      = $address->getTimeZone();
            $components    = $address->getComponents();
            $fullAddr      = $address->getFullAddress();
            $coordinates   = $address->getCoordinates();
            $countryCode   = $address->getCountryCode();
            $hasComponents = is_array($components) && !empty($components);
            $hasFullAddr   = is_string($fullAddr) && $fullAddr !== '';
            $hasCoords     = is_string($coordinates) && $coordinates !== '';
            $hasCountry    = is_string($countryCode) && $countryCode !== '';

            if (
                is_string($timeZone) && $timeZone !== ''
                && !$hasComponents
                && !$hasFullAddr
                && !$hasCoords
                && !$hasCountry
            ) {
                $this->addSingleProperty('TZ', $timeZone);
                continue;
            }

            $parts = array(
                '', '', '', '', '', '', '',
                '', '', '', '', '', '', '', '', '', '',
            );

            if ($hasComponents) {
                foreach ($components as $comp) {
                    if (!is_object($comp)) {
                        continue;
                    }

                    $kind  = $comp->getValue();
                    $value = $comp->getKind();

                    if (!is_string($kind) || $kind === '') {
                        continue;
                    }

                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    switch ($kind) {
                        case 'postOfficeBox':
                            $parts[0]  = $value;
                            break;
                        case 'apartment':
                            $parts[7]  = $value;
                            break;
                        case 'room':
                            $parts[8]  = $value;
                            break;
                        case 'floor':
                            $parts[9]  = $value;
                            break;
                        case 'number':
                            $parts[10] = $value;
                            break;
                        case 'name':
                            $parts[2]  = $value;
                            break;
                        case 'block':
                            $parts[11] = $value;
                            break;
                        case 'building':
                            $parts[12] = $value;
                            break;
                        case 'direction':
                            $parts[13] = $value;
                            break;
                        case 'landmark':
                            $parts[14] = $value;
                            break;
                        case 'district':
                            $parts[15] = $value;
                            break;
                        case 'subdistrict':
                            $parts[16] = $value;
                            break;
                        case 'locality':
                            $parts[3]  = $value;
                            break;
                        case 'region':
                            $parts[4]  = $value;
                            break;
                        case 'postcode':
                            $parts[5]  = $value;
                            break;
                        case 'country':
                            $parts[6]  = $value;
                            break;
                    }
                }
            }

            $hasAny = false;
            foreach ($parts as $p) {
                if ($p !== '') {
                    $hasAny = true;
                    break;
                }
            }

            if (!$hasAny && $hasFullAddr) {
                $parts[2] = $fullAddr;
            }

            $params = array();

            if ($hasCountry) {
                $params['CC']    = $countryCode;
            }
            if ($hasCoords) {
                $params['GEO']   = $coordinates;
            }
            if ($timeZone !== null && $timeZone !== '') {
                $params['TZ'] = $timeZone;
            }
            if ($hasFullAddr) {
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

            $this->vcard->add('ADR', $parts, $params);
        }
    }

    /**
     * Reads vCard ADR properties and stores them as addresses on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getAddressesToJmap(ContactCard $card)
    {
        $vAddrs = $this->vcard->ADR;
        if (!AdapterUtil::isSetAndNotNull($vAddrs) || empty($vAddrs)) {
            return;
        }

        $map = array();
        $i   = 1;

        foreach ($vAddrs as $vAddr) {
            $parts = $vAddr->getParts();
            if (!AdapterUtil::isSetAndNotNull($parts) || empty($parts)) {
                continue;
            }

            $a = new Address();

            if (isset($vAddr['LABEL'])) {
                $a->setFullAddress((string) $vAddr['LABEL']);
            }

            $components = array();

            if (isset($parts[0]) && $parts[0] !== '') {
                $components[] = new AddressComponent('postOfficeBox', $parts[0]);
            }

            $isExtended = count($parts) > 7;

            if ($isExtended) {
                if (isset($parts[7])  && $parts[7]  !== '') {
                    $components[] = new AddressComponent('apartment', $parts[7]);
                }
                if (isset($parts[8])  && $parts[8]  !== '') {
                    $components[] = new AddressComponent('room', $parts[8]);
                }
                if (isset($parts[9])  && $parts[9]  !== '') {
                    $components[] = new AddressComponent('floor', $parts[9]);
                }
                if (isset($parts[10]) && $parts[10] !== '') {
                    $components[] = new AddressComponent('number', $parts[10]);
                }
                if (isset($parts[2])  && $parts[2]  !== '') {
                    $components[] = new AddressComponent('name', $parts[2]);
                }
                if (isset($parts[11]) && $parts[11] !== '') {
                    $components[] = new AddressComponent('block', $parts[11]);
                }
                if (isset($parts[12]) && $parts[12] !== '') {
                    $components[] = new AddressComponent('building', $parts[12]);
                }
                if (isset($parts[13]) && $parts[13] !== '') {
                    $components[] = new AddressComponent('direction', $parts[13]);
                }
                if (isset($parts[14]) && $parts[14] !== '') {
                    $components[] = new AddressComponent('landmark', $parts[14]);
                }
                if (isset($parts[16]) && $parts[16] !== '') {
                    $components[] = new AddressComponent('subdistrict', $parts[16]);
                }
                if (isset($parts[15]) && $parts[15] !== '') {
                    $components[] = new AddressComponent('district', $parts[15]);
                }
            } else {
                if (isset($parts[1]) && $parts[1] !== '') {
                    $components[] = new AddressComponent('apartment', $parts[1]);
                }
                if (isset($parts[2]) && $parts[2] !== '') {
                    $components[] = new AddressComponent('name', $parts[2]);
                }
            }

            if (isset($parts[3]) && $parts[3] !== '') {
                $components[] = new AddressComponent('locality', $parts[3]);
            }
            if (isset($parts[4]) && $parts[4] !== '') {
                $components[] = new AddressComponent('region', $parts[4]);
            }
            if (isset($parts[5]) && $parts[5] !== '') {
                $components[] = new AddressComponent('postcode', $parts[5]);
            }
            if (isset($parts[6]) && $parts[6] !== '') {
                $components[] = new AddressComponent('country', $parts[6]);
            }

            if (!empty($components)) {
                $a->setIsOrdered(true);
                $a->setDefaultSeparator(', ');
                $a->setComponents($components);
            }

            if ($a->getFullAddress() === null) {
                $fullParts = array();
                foreach ($components as $comp) {
                    if (!is_object($comp) || !method_exists($comp, 'getKind')) {
                        continue;
                    }

                    // In this AddressComponent model:
                    // - getValue() is the component type (e.g. "locality")
                    // - getKind() is the actual text (e.g. "Berlin")
                    $val = $comp->getKind();
                    if ($val !== null && $val !== '') {
                        $fullParts[] = $val;
                    }
                }

                if (!empty($fullParts)) {
                    $a->setFullAddress(implode(', ', $fullParts));
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

            $ctx = $this->vcardTypeParamToContexts($vAddr);
            if (!empty($ctx)) {
                $a->setContexts($ctx);
            }

            $pref = $this->vcardPrefParamToInt($vAddr);
            if ($pref !== null) {
                $a->setPref($pref);
            }

            $map['a' . $i++] = $a;
        }

        $standaloneTz = $this->vcard->__get('TZ');
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
    // Anniversaries
    /**
     * Returns the BDAY date as Y-m-d, or '0000-00-00' if it's missing or can't be parsed.
     * Handles both plain dates and date-time values like 19950505T000000Z.
     */
    protected function getBirthday()
    {
        $bday = $this->vcard->BDAY;
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
        $ann = $this->vcard->__get('ANNIVERSARY');
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
        $p = $this->vcard->__get('BIRTHPLACE');
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
        $p = $this->vcard->__get('DEATHDATE');
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
     * Unescapes any literal \n sequences in the value.
     *
     * @return string|null
     */
    protected function getDeathPlaceRaw()
    {
        $p = $this->vcard->__get('DEATHPLACE');
        if (!AdapterUtil::isSetAndNotNull($p)) {
            return null;
        }
        $raw = str_replace("\\n", "\n", (string) $p);
        $raw = trim($raw);
        return $raw === '' ? null : $raw;
    }

    /**
     * Converts a raw place string (plain text or geo: URI) to a JSContact Address object.
     * Returns null if the input is empty or can't be meaningfully converted.
     *
     * @param string $raw
     * @return Address|null
     */
    protected function placeRawToAddress($raw)
    {
        if (!is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $addr = new Address();

        if (stripos($raw, 'geo:') === 0) {
            $addr->setCoordinates($raw);
            return $addr;
        }

        if ($this->placeTextAsFullAddress) {
            $addr->setFullAddress($raw);
            return $addr;
        }

        return null;
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
        $coordinates = $addr->getCoordinates();
        if (is_string($coordinates) && trim($coordinates) !== '') {
            $coordinates = trim($coordinates);
            if (stripos($coordinates, 'geo:') === 0) {
                return $coordinates;
            }
            return 'geo:' . ltrim($coordinates, ':');
        }

        $text = $addr->getFullAddress();
        if (is_string($text) && trim($text) !== '' && $this->placeTextAsFullAddress) {
            return trim($text);
        }

        return null;
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
    public function setAnniversariesFromJmap(ContactCard $card)
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
    public function getAnniversariesToJmap(ContactCard $card)
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
    // Relations
    /**
     * Writes ContactCard relations as vCard RELATED properties, with relation types as TYPE parameters.
     *
     * @param ContactCard $card
     */
    public function setRelatedToFromJmap(ContactCard $card)
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
                $this->vcard->add('RELATED', $key);
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
                $this->vcard->add('RELATED', $key);
            } else {
                $this->vcard->add('RELATED', $key, array('TYPE' => $types));
            }
        }
    }

    /**
     * Reads vCard RELATED properties and stores them as relations on the ContactCard.
     * TYPE parameters become the relation type keys.
     *
     * @param ContactCard $card
     */
    public function getRelatedToToJmap(ContactCard $card)
    {
        $vRelated = $this->vcard->RELATED;
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
    public function setMembersFromJmap(ContactCard $card)
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

            $this->vcard->add('MEMBER', $uid, array('VALUE' => 'uri'));
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
    public function getMembersToJmap(ContactCard $card)
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
    // Keywords
    /**
     * Reads vCard CATEGORIES properties and stores them as keywords on the ContactCard.
     *
     * @param ContactCard $card
     */
    public function getKeywordsToJmap(ContactCard $card)
    {
        $vCats = $this->vcard->CATEGORIES;
        if (!AdapterUtil::isSetAndNotNull($vCats) || empty($vCats)) {
            return;
        }

        $keywords = array();

        foreach ($vCats as $catProp) {
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
    public function setKeywordsFromJmap(ContactCard $card)
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
            $this->vcard->add('CATEGORIES', $values);
        }
    }
    // Personal info
    /**
     * Reads vCard EXPERTISE, HOBBY, and INTEREST properties and stores them on the ContactCard.
     * LEVEL values are mapped: beginner -> low, average/medium -> medium, expert -> high.
     *
     * @param ContactCard $card
     */
    public function getPersonalInfoToJmap(ContactCard $card)
    {
        $info = array();

        $readProps = function ($propName, $kind) use (&$info) {
            $props = $this->vcard->{$propName};
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
                    $rawLevel = strtolower((string) $prop['LEVEL']);
                    if ($rawLevel === 'beginner') {
                        $level = 'low';
                    } elseif ($rawLevel === 'average' || $rawLevel === 'medium') {
                        $level = 'medium';
                    } elseif ($rawLevel === 'expert') {
                        $level = 'high';
                    }
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
    public function setPersonalInfoFromJmap(ContactCard $card)
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
                if ($level === 'low') {
                    $params['LEVEL'] = 'beginner';
                } elseif ($level === 'medium') {
                    $params['LEVEL'] = 'average';
                } elseif ($level === 'high') {
                    $params['LEVEL'] = 'expert';
                }
            }

            $idx = $pi->getListAs();
            if (is_int($idx) && $idx >= 0) {
                $params['INDEX'] = (string) $idx;
            }

            $this->vcard->add($propName, $value, $params);
        }
    }
    /**
     * Returns true if the value looks like a URI or scheme-based identifier.
     *
     * @param mixed $value
     * @return bool
     */
    protected function looksLikeUri($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $value = trim($value);

        return (bool) preg_match('/^[a-z][a-z0-9+.\-]*:/i', $value)
            || (bool) preg_match('/^https?:\/\//i', $value);
    }

    /**
     * Assigns an online service value to uri or user based on property type and service type.
     *
     * @param OnlineService $os
     * @param string        $propName
     * @param string        $value
     * @param mixed|null    $prop
     */
    protected function assignOnlineValueToObject(OnlineService $os, $propName, $value, $prop = null)
    {
        $serviceType = isset($prop['SERVICE-TYPE'])
            ? strtolower(trim((string) $prop['SERVICE-TYPE']))
            : null;

        // IMPP is always URI-like.
        if ($propName === 'IMPP') {
            $os->setUri($value);
            return;
        }

        // Known URI-style services.
        if (in_array($serviceType, ['aim', 'jabber', 'xmpp', 'sip', 'sips'], true)) {
            $os->setUri($value);
            return;
        }

        // Known username-style services in this adapter.
        if (in_array($serviceType, ['skype', 'icq', 'msn', 'yahoo'], true)) {
            if ($this->looksLikeUri($value)) {
                $os->setUri($value);
            } else {
                $os->setUser($value);
            }
            return;
        }

        // Social profiles and generic URLs are usually URI-like.
        if ($propName === 'SOCIALPROFILE' || $propName === 'URL') {
            if ($this->looksLikeUri($value)) {
                $os->setUri($value);
            } else {
                $os->setUser($value);
            }
            return;
        }

        // Fallback.
        if ($this->looksLikeUri($value)) {
            $os->setUri($value);
        } else {
            $os->setUser($value);
        }
    }
}
