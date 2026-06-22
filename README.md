# OpenXPort JMAP iCalendar/vCard library
The JMAP iCalendar/vCard library extends [openxport-jmap](https://github.com/audriga/openxport-jmap) with the ability to convert between iCalendar and JSCalendar as well as vCard and JSContact.

It should be simple for consumers to migrate from another service to your service and vice versa. OpenXPort makes it easy to expose a RESTful API Endpoint for data portability. It is built on top of the interoperable protocol [JMAP](https://jmap.io/), which already supports a wide variety of data types and can be extended for more.

This library currently aims to implement the following specifications:

* JSCalendar: Converting from and to iCalendar [draft-ietf-calext-jscalendar-icalendar](https://datatracker.ietf.org/doc/draft-ietf-calext-jscalendar-icalendar/)
* JSContact: Converting from and to vCard [draft-ietf-calext-jscontact-vcard](https://datatracker.ietf.org/doc/draft-ietf-calext-jscontact-vcard/)

OpenXPort requires PHP 8.0 or higher.

## Installation
### Local installation
1. Run `make` to initialize the project.

## Usage
### Standalone
Will be supported in a future version.

### Usage in openxport-jmap
This library provides Adapters and Mappers specific to openxport-jmap. There are different versions of adapters and mappers to choose from:

* Those aiming to be fully compliant with above's IETF specs (e.g. `JSCalendarICalendarAdapter`)
* System-specific adaptions to the conversion (e.g. `NextcloudJSContactVCardAdapter`)

They can be included in OpenXPort projects the usual way:

```php
$adapters = array(
    "Cards" => new \OpenXPort\Adapter\NextcloudJSContactVCardAdapter()
...
$mappers = array(
    "Cards" => new \OpenXPort\Mapper\JSContactVCardMapper(),
...
```

## Development
### Installation
1. Run `make` or one of the targets for old PHP versions above.
1. Run `make update` to update dependencies and make development tools available

### Tests
To run all tests run `make fulltest`. This requires [Podman](https://podman.io/)
(for Static Analysis).

You can also run them separately:

* **Static Analysis** via `make lint`
* **Unit Tests** via `make unit_test`