<?php

namespace OpenXPort\Util;

use OpenXPort\Jmap\JSContact\Address;
use OpenXPort\Jmap\JSContact\AddressComponent;
use OpenXPort\Jmap\JSContact\NameComponent;
use OpenXPort\Util\AdapterUtil;
use InvalidArgumentException;

/**
 * Utility class used by JSContactVCardAdapters to convert property values.
 */
class JSContactVCardAdapterUtil
{
    protected static $logger;

    /**
     * Converts vCard TYPE parameter to JSContact contexts
     * "home" becomes "private" and "work" stays "work"
     */
    public static function vCardTypeParamToContexts($prop)
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
     * Converts vCard PREF parameter to integer
     */
    public static function vCardPrefParamToInt($prop)
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
     * Converts JSContact contexts to vCard TYPE parameter values
     * "private" becomes "home" and "work" stays "work"
     */
    public static function contextsToVcardTypeParam($obj)
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
     * Converts JSContact preference to vCard PREF parameter string
     */
    public static function prefToVcardParam($obj)
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
     * Applies context and preference from vCard property to JSContact object
     */
    public static function applyCommonContextAndPref($obj, $prop)
    {
        if (!is_object($obj) || $prop === null) {
            return;
        }

        $ctx = self::vCardTypeParamToContexts($prop);
        if (!empty($ctx)) {
            $obj->setContexts($ctx);
        }

        $pref = self::vCardPrefParamToInt($prop);
        if ($pref !== null) {
            $obj->setPref($pref);
        }
    }

    /**
     * Builds parameters with TYPE and PREF from JSContact object
     */
    public static function buildContextPrefParams($obj, array $params = array())
    {
        $types = self::contextsToVcardTypeParam($obj);
        if (!empty($types)) {
            $params['TYPE'] = $types;
        }

        $pref = self::prefToVcardParam($obj);
        if ($pref !== null) {
            $params['PREF'] = $pref;
        }

        return $params;
    }

    /**
     * Converts Y-m-d date to vCard date format (Ymd)
     */
    public static function parseDateToVcardDate($value)
    {
        if (!is_string($value) || trim($value) === '' || $value === '0000-00-00') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Y-m-d', 'Ymd');
    }

    /**
     * Converts JSContact UTC timestamp to vCard TIMESTAMP format (YmdTHisZ)
     */
    public static function parseDateTimeToVcardTimestamp($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Y-m-d\TH:i:s\Z', 'Ymd\THis\Z');
    }

    /**
     * Converts vCard TIMESTAMP to JSContact UTC timestamp
     */
    public static function parseTimestampToJmapDateTime($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return AdapterUtil::parseDateTime($value, 'Ymd\THis\Z', 'Y-m-d\TH:i:s\Z');
    }

    /**
     * Converts various vCard date/time formats to JSContact UTC
     */
    public static function parseDateTimeToJscontactUtc($value)
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
     * Extracts PROP-ID parameter from vCard property
     */
    public static function getPropId($prop)
    {
        if (!isset($prop['PROP-ID'])) {
            return null;
        }

        $value = trim((string) $prop['PROP-ID']);
        return $value !== '' ? $value : null;
    }

    /**
     * Adds PROP-ID to params if the map key is suitable
     */
    public static function addPropIdParam(array $params, $mapKey)
    {
        if (!is_string($mapKey)) {
            return $params;
        }

        $mapKey = trim($mapKey);
        if ($mapKey === '') {
            return $params;
        }

        // Skip auto-generated keys
        if (preg_match('/^(n|pr|o|t|e|p|os|lp|m|d|l|k|sa|a)\d+$/', $mapKey)) {
            return $params;
        }

        // Skip MD5 hashes
        if (preg_match('/^[a-f0-9]{32}$/i', $mapKey)) {
            return $params;
        }

        $params['PROP-ID'] = $mapKey;

        return $params;
    }

    /**
     * Gets map key from PROP-ID or falls back to provided key
     */
    public static function getMapKeyFromProp($prop, $fallback)
    {
        $propId = self::getPropId($prop);
        return $propId !== null ? $propId : $fallback;
    }

    /**
     * Gets map key from PROP-ID, INDEX, or generates from value hash
     */
    public static function getMapKeyFromPropValue($prop, $value, $fallbackPrefix, &$index, array $existingMap = array())
    {
        $key = self::getPropId($prop);

        if ($key === null && isset($prop['INDEX'])) {
            $indexValue = trim((string) $prop['INDEX']);
            if ($indexValue !== '') {
                $key = $indexValue;
            }
        }

        if ($key === null) {
            $key = md5((string) $value);
        }

        if (isset($existingMap[$key])) {
            $key = $fallbackPrefix . $index++;
        }

        return $key;
    }

    /**
     * Escapes value for JSCOMPS parameter
     */
    public static function escapeJscompsValue($value)
    {
        return str_replace(array('\\', ',', ';'), array('\\\\', '\\,', '\\;'), $value);
    }

    /**
     * Unescapes value from JSCOMPS parameter
     */
    public static function unescapeJscompsValue($value)
    {
        return str_replace(array('\\,', '\\;', '\\\\'), array(',', ';', '\\'), $value);
    }

    /**
     * Builds JSCOMPS parameter value and property parts array
     *
     * @param array $components Array of NameComponent or AddressComponent objects
     * @param array $kindToPosition Map of component kinds to positions
     * @param string $defaultSep Default separator string
     * @param int $partsCount Number of property parts (8 for N, 17 for ADR)
     * @return array [$parts, $jscompsValue]
     */
    public static function buildJscompsData($components, $kindToPosition, $defaultSep, $partsCount)
    {
        $parts = array_fill(0, $partsCount, '');
        $jscompsEntries = array();
        $positionMap = array();

        foreach ($components as $component) {
            $kind = $component->getKind();
            $value = $component->getValue();

            if ($kind === 'separator') {
                $escaped = self::escapeJscompsValue($value);
                $jscompsEntries[] = 's,' . $escaped;
            } elseif (isset($kindToPosition[$kind])) {
                $position = $kindToPosition[$kind];

                if (!isset($positionMap[$position])) {
                    $positionMap[$position] = array();
                }

                $subIndex = count($positionMap[$position]);
                $positionMap[$position][] = $value;

                $jscompsEntries[] = $subIndex === 0 ? (string)$position : $position . ',' . $subIndex;

                if ($subIndex === 0) {
                    $parts[$position] = $value;
                } else {
                    $parts[$position] .= ',' . $value;
                }
            }
        }

        $defaultSepEntry = '';
        if ($defaultSep !== null && $defaultSep !== '') {
            $defaultSepEntry = 's,' . self::escapeJscompsValue($defaultSep);
        }

        $jscompsValue = $defaultSepEntry . ';' . implode(';', $jscompsEntries);

        return array($parts, $jscompsValue);
    }

    /**
     * Parses JSCOMPS parameter value back into component objects
     *
     * @param string $jscompsValue JSCOMPS parameter value
     * @param array $parts Property parts array
     * @param array $positionToKind Map of positions to component kinds
     * @param string $componentClass Class name for components (NameComponent or AddressComponent)
     * @return array Array of component objects
     */
    public static function parseJscompsData($jscompsValue, $parts, $positionToKind, $componentClass)
    {
        if (!class_exists($componentClass)) {
            return array();
        }

        $entries = explode(';', $jscompsValue);
        $components = array();

        array_shift($entries);

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (strpos($entry, 's,') === 0) {
                $sepValue = self::unescapeJscompsValue(substr($entry, 2));
                $components[] = new $componentClass('separator', $sepValue);
            } else {
                $posParts = explode(',', $entry);
                $position = (int)$posParts[0];
                $subIndex = isset($posParts[1]) ? (int)$posParts[1] : 0;

                if (!isset($positionToKind[$position]) || !isset($parts[$position])) {
                    continue;
                }

                $value = $parts[$position];

                if (is_string($value) && strpos($value, ',') !== false) {
                    $values = explode(',', $value);
                    if (isset($values[$subIndex])) {
                        $value = $values[$subIndex];
                    }
                }

                if ($value !== '' && $value !== null) {
                    $components[] = new $componentClass($positionToKind[$position], $value);
                }
            }
        }

        return $components;
    }

    /**
     * Extracts default separator from JSCOMPS parameter value
     */
    public static function getDefaultSeparatorFromJscomps($jscompsValue)
    {
        $entries = explode(';', $jscompsValue);
        if (!empty($entries[0]) && strpos($entries[0], 's,') === 0) {
            return self::unescapeJscompsValue(substr($entries[0], 2));
        }
        return null;
    }

    /**
     * Builds component objects from property parts (non-JSCOMPS mode)
     *
     * @param array $parts Property parts array
     * @param array $indexToKind Map of part indexes to component kinds
     * @param string $componentClass Class name for components
     * @return array Array of component objects
     */
    public static function buildComponentsFromParts($parts, $indexToKind, $componentClass)
    {
        if (!class_exists($componentClass)) {
            return array();
        }

        $components = array();

        foreach ($indexToKind as $index => $kind) {
            if (isset($parts[$index]) && $parts[$index] !== '') {
                $components[] = new $componentClass($kind, $parts[$index]);
            }
        }

        return $components;
    }

    /**
     * Standard N property kind to position mapping
     */
    public static function getNameKindToPositionMap()
    {
        return array(
            'surname'    => 0,
            'given'      => 1,
            'given2'     => 2,
            'title'      => 3,
            'credential' => 4,
            'generation' => 6,
            'surname2'   => 7,
        );
    }

    /**
     * Standard N property position to kind mapping
     */
    public static function getNamePositionToKindMap()
    {
        return array(
            0 => 'surname',
            1 => 'given',
            2 => 'given2',
            3 => 'title',
            4 => 'credential',
            6 => 'generation',
            7 => 'surname2',
        );
    }

    /**
     * Basic N property position to kind mapping (vCard 3.0/4.0)
     */
    public static function getBasicNamePositionToKindMap()
    {
        return array(
            0 => 'surname',
            1 => 'given',
            2 => 'given2',
            3 => 'title',
            4 => 'credential',
        );
    }

    /**
     * Standard ADR property kind to position mapping (RFC 9554)
     */
    public static function getAddressKindToPositionMap()
    {
        return array(
            'postOfficeBox' => 0,
            'apartment'     => 7,
            'room'          => 8,
            'floor'         => 9,
            'number'        => 10,
            'name'          => 2,
            'block'         => 11,
            'building'      => 12,
            'direction'     => 13,
            'landmark'      => 14,
            'district'      => 15,
            'subdistrict'   => 16,
            'locality'      => 3,
            'region'        => 4,
            'postcode'      => 5,
            'country'       => 6,
        );
    }

    /**
     * Standard ADR property position to kind mapping (RFC 9554)
     */
    public static function getAddressPositionToKindMap()
    {
        return array(
            0  => 'postOfficeBox',
            2  => 'name',
            3  => 'locality',
            4  => 'region',
            5  => 'postcode',
            6  => 'country',
            7  => 'apartment',
            8  => 'room',
            9  => 'floor',
            10 => 'number',
            11 => 'block',
            12 => 'building',
            13 => 'direction',
            14 => 'landmark',
            15 => 'district',
            16 => 'subdistrict',
        );
    }

    /**
     * Basic ADR property position to kind mapping
     */
    public static function getBasicAddressPositionToKindMap()
    {
        return array(
            0 => 'postOfficeBox',
            1 => 'apartment',
            2 => 'name',
            3 => 'locality',
            4 => 'region',
            5 => 'postcode',
            6 => 'country',
        );
    }

    /**
     * Maps vCard GENDER to JSContact grammatical gender
     */
    public static function mapGenderToGrammatical($genderValue)
    {
        $mapping = array(
            'm'      => 'male',
            'male'   => 'male',
            'f'      => 'female',
            'female' => 'female',
            'n'      => 'neuter',
            'neuter' => 'neuter',
            'o'      => 'animate',
            'other'  => 'animate',
        );

        $key = strtolower(trim($genderValue));
        return isset($mapping[$key]) ? $mapping[$key] : null;
    }

    /**
     * Maps JSContact level to vCard LEVEL parameter
     */
    public static function mapLevelToVcard($level)
    {
        $mapping = array(
            'low'    => 'beginner',
            'medium' => 'average',
            'high'   => 'expert',
        );

        if (!is_string($level)) {
            return null;
        }

        $key = strtolower(trim($level));
        return isset($mapping[$key]) ? $mapping[$key] : null;
    }

    /**
     * Maps vCard LEVEL parameter to JSContact level
     */
    public static function mapLevelFromVcard($level)
    {
        $mapping = array(
            'beginner' => 'low',
            'average'  => 'medium',
            'medium'   => 'medium',
            'expert'   => 'high',
        );

        $key = strtolower(trim($level));
        return isset($mapping[$key]) ? $mapping[$key] : null;
    }

    /**
     * Checks if value looks like a URI
     */
    public static function isUri($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $value = trim($value);

        return (bool) preg_match('/^[a-z][a-z0-9+.\-]*:/i', $value)
            || (bool) preg_match('/^https?:\/\//i', $value);
    }

    /**
     * Normalizes geo: URI
     */
    public static function normalizeGeoUri($coordinates)
    {
        if (!is_string($coordinates) || trim($coordinates) === '') {
            return null;
        }

        $coordinates = trim($coordinates);

        if (stripos($coordinates, 'geo:') === 0) {
            return $coordinates;
        }

        return 'geo:' . ltrim($coordinates, ':');
    }

    /**
     * Converts place string (text or geo: URI) to Address object
     *
     * @param string $raw Raw place value
     * @param bool $textAsFullAddress If true, plain text becomes fullAddress
     * @return Address|null
     */
    public static function placeToAddress($raw, $textAsFullAddress = true)
    {
        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (!class_exists('OpenXPort\\Jmap\\JSContact\\Address')) {
            return null;
        }

        $addr = new Address();

        if (stripos($raw, 'geo:') === 0) {
            $addr->setCoordinates($raw);
        } elseif ($textAsFullAddress) {
            $addr->setFullAddress($raw);
        }

        return $addr;
    }

    /**
     * Extracts place value from Address object (coordinates or text)
     *
     * @param Address $addr
     * @param bool $allowText If true, returns fullAddress as fallback
     * @return string|null
     */
    public static function addressToPlace(Address $addr, $allowText = true)
    {
        $coordinates = $addr->getCoordinates();
        if (is_string($coordinates) && trim($coordinates) !== '') {
            return self::normalizeGeoUri($coordinates);
        }

        if (!$allowText) {
            return null;
        }

        $text = $addr->getFullAddress();
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        return null;
    }

    /**
     * Determines vCard property type for online service (IMPP, SOCIALPROFILE, or URL)
     */
    public static function determineOnlinePropertyType($uri, $service)
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

    /**
     * Assigns online service value to uri or user based on service type
     */
    public static function assignOnlineValue($os, $propName, $value, $serviceType = null)
    {
        $serviceType = $serviceType !== null ? strtolower(trim((string) $serviceType)) : null;

        // IMPP is always URI
        if ($propName === 'IMPP') {
            $os->setUri($value);
            return;
        }

        // URI-style services
        if (in_array($serviceType, ['aim', 'jabber', 'xmpp', 'sip', 'sips'], true)) {
            $os->setUri($value);
            return;
        }

        // Username-style services
        if (in_array($serviceType, ['skype', 'icq', 'msn', 'yahoo'], true)) {
            if (self::isUri($value)) {
                $os->setUri($value);
            } else {
                $os->setUser($value);
            }
            return;
        }

        // Default: check if URI
        if (self::isUri($value)) {
            $os->setUri($value);
        } else {
            $os->setUser($value);
        }
    }

    /**
     * Gets export value from online service
     */
    public static function getOnlineExportValue($os)
    {
        $uri = $os->getUri();
        $user = $os->getUser();
        $service = strtolower(trim((string) $os->getService()));

        // URI-first services
        if (in_array($service, ['aim', 'jabber', 'xmpp', 'sip'], true)) {
            return $uri ?? $user;
        }

        // Username-first services
        if (in_array($service, ['skype', 'icq', 'msn', 'yahoo'], true)) {
            return $user ?? $uri;
        }

        // Default: uri first
        return $uri ?? $user;
    }

    /**
     * Tries to instantiate first available class from list
     */
    public static function instantiateJscontactObject(array $classNames, array $constructorArgs = array())
    {
        foreach ($classNames as $className) {
            if (class_exists($className)) {
                return new $className(...$constructorArgs);
            }
        }

        return null;
    }

    /**
     * Creates Media object
     */
    public static function createMediaObject($uri, $kind, $prop = null)
    {
        $className = 'OpenXPort\\Jmap\\JSContact\\Media';

        $media = new $className($kind);
        $media->setUri($uri);

        if ($prop !== null && isset($prop['MEDIATYPE'])) {
            $media->setMediaType((string) $prop['MEDIATYPE']);
        }

        self::applyCommonContextAndPref($media, $prop);

        return $media;
    }

    /**
     * Checks if value is set and not null
     */
    public static function isSetAndNotNull($value)
    {
        return AdapterUtil::isSetAndNotNull($value);
    }

    /**
     * Checks if value is non-empty string
     */
    public static function isNonEmptyString($value)
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Checks if value is non-empty array
     */
    public static function isNonEmptyArray($value)
    {
        return is_array($value) && !empty($value);
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
            $params = $this->addPropIdParam($params, $id);
        }

        return $params;
    }
}
