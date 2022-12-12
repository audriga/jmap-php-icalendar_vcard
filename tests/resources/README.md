This folder contains iCalendar and vCard files for testing.

vCards:
* `horde.vcf` - a vCard exported from Horde
* `rc_vcard.vcf` - vCard exported from RC
* `invalid-vcard.vcf` - Contains incorrectly escaped special char "." (based on `rc_vcard.vcf`)
* `vcard-special-chars.vcf` - Contains correctly escaped special chars (based on `rc_vcard.vcf`)
* `test_vcard.vcf` - just a vCard
* `test_vcard_2.vcf` - contains an empty name (vCard "N") property
* `test_vcard_v3.vcf` - yet another vCard

iCalendars:
* `test_icalendar.ics` - some random iCalendar
* `nextcloud_conversion_event_1.ics` - iCalendar created in Nextcloud
* `recurring_event_with_changed_occurrence.ics` - iCal event with recurrence and a changed occurrence
* `calendar_witch_two_events.ics` - iCal file with two separate events.

JSCalendars:
* `jscalendar_basic.json` - JSCalendar with very basic properties
* `jscalendar_extended.json` - JSCalendar with an extended set of properties
* `jscalendar_with_recurrence_overrides.json` - JSCalendar with a recurrenceOverride property
* `jscalendar_two_events.json` - JSCalendar consisting of an array of two events.
* `jscalendar_all_basic_properties.json` - JSCalendar containing every property that is not a custom object
* `jscalendar_with_locations.json` - JSCalendar containing Locations
* `jscalendar_with_links.json` - JSCalendar conatining Links
* `jscalendar_with_alerts.json` - JSCalendar containing alerts