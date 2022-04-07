<?php

namespace Test\VObject;

use Sabre\VObject;
use PHPUnit\Framework\TestCase;

final class VObjectTest extends TestCase
{
    public function testReadVcard(): void
    {
        // Read the vCard from the file test_vcard.vcf
        $vcard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_vcard.vcf', 'r')
        );

        $this->assertEquals('Forrest Gump', $vcard->FN);
        $this->assertEquals('Some text bla', $vcard->__get('X-PROP'));
    }

    public function testReadIcalendar(): void
    {
        // Read the iCalendar from the file test_icalendar.ics
        $icalendar = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/test_icalendar.ics', 'r')
        );

        $this->assertEquals('Just a Test', $icalendar->VEVENT->SUMMARY);
    }

    public function testReadHordeVcard(): void
    {
        // Read the vCard from the file horde.vcf
        $hordeVcard = VObject\Reader::read(
            fopen(__DIR__ . '/../resources/horde.vcf', 'r')
        );

        $this->assertEquals('1912-10-15', $hordeVcard->__get('X-ANNIVERSARY'));
        $this->assertEquals('mySpouse', $hordeVcard->__get('X-SPOUSE'));
        $this->assertEquals('IM1', $hordeVcard->__get('X-WV-ID'));
    }
}
