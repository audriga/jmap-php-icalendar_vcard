# OpenXPort JMAP iCalendar/vCard library
The JMAP iCalendar/vCard library extends the core OpenXPort framework with the ability to convert between iCalendar and JSCalendar as well as vCard and JSContact.

It should be simple for consumers to migrate from another service to your service and vice-versa. OpenXPort makes it easy to expose a RESTful API Endpoint for data portability. It is built on top of the interoperable protocol [JMAP](https://jmap.io/), which already supports a wide variety of data types and can be extended for more.

Currently supports conversion between vCard and JSContact.

OpenXPort is built with compatibility for older systems in mind. We support all PHP versions down to 5.6 to provide data portability even for older systems.

## Installation
### Local installation
1. Run `make` to initialize the project for the default PHP version (8.1). Use other build targets (e.g. `make php56_mode` or `make php70_mode`) instead, in case you need to build for a different version.

## Development
### Installation
1. Run `make` or one of the targets for old PHP versions above.
1. Run `make update` to update depdendencies and make devtools available

### Tests
To run all tests run `make fulltest`. This requires [Podman](https://podman.io/)
(for Static Anaylsis) and [Ansible](https://www.ansible.com/) (for Integration
Tests).

You can also run them separately:

* **Static Analysis** via `make lint`
* **Unit Tests** via `make unit_test`
