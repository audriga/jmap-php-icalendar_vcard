==================================
JMAP iCalendar/vCard Release Notes
==================================

.. contents:: Topics

v0.2.0
=======

Release summary
---------------
Brings proper CalendarEvent deserialization, fixes some warnings.

Details
-------
* Proper deserialization of CalendarEvent JSON #5994
* Squash a bunch of warnings found during testing

v0.1.0
=======

Release summary
---------------
JSCalendar <-> should now be close to completion in both directions

Details
-------
* Add most of the JSCalendar <-> iCalendar transformation #5872

v0.0.2
=======

Release summary
---------------
Split OXP into separate components

Details
-------
* iCalendar/vCard lives in its own repository now.
* Fix various issues with JSContact (#5734)
* Verify checksum for composer installer script
* Add a roundtrip test for JSContact <-> vCard
* Add initial code for JSCalendar <-> iCalendar transformation
