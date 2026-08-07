<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Ensures ASU-1864 offer UI strings have Finnish translations in .po files.
 *
 * @group asu_application
 */
final class OfferFinnishTranslationsTest extends UnitTestCase {

  /**
   * Offer-related English source strings that must have Finnish translations.
   *
   * @return array<string, string>
   *   Map of English msgid => expected Finnish msgstr.
   */
  public static function requiredOfferTranslationsProvider(): array {
    return [
      'Accept offer' => ['Accept offer', 'Hyväksy tarjous'],
      'Reject offer' => ['Reject offer', 'Hylkää tarjous'],
      'Are you sure you want to accept this offer?' => [
        'Are you sure you want to accept this offer?',
        'Haluatko varmasti hyväksyä tämän tarjouksen?',
      ],
      'Are you sure you want to reject this offer?' => [
        'Are you sure you want to reject this offer?',
        'Haluatko varmasti hylätä tämän tarjouksen?',
      ],
      'Offer status' => ['Offer status', 'Tarjouksen tila'],
      'Offer valid until' => ['Offer valid until', 'Viimeinen voimassaolopäivä'],
      'Show offer details' => ['Show offer details', 'Näytä tarjouksen tiedot'],
      'Hide offer details' => ['Hide offer details', 'Piilota tarjouksen tiedot'],
      'Loading offer details…' => [
        'Loading offer details…',
        'Ladataan tarjouksen tietoja…',
      ],
      'Loading offers…' => ['Loading offers…', 'Ladataan tarjouksia…'],
      'Could not load offer details.' => [
        'Could not load offer details.',
        'Tarjouksen tietoja ei voitu ladata.',
      ],
      'You have accepted this offer.' => [
        'You have accepted this offer.',
        'Olet hyväksynyt tämän tarjouksen.',
      ],
      'You have rejected this offer.' => [
        'You have rejected this offer.',
        'Olet hylännyt tämän tarjouksen.',
      ],
      'Could not update the offer. Please try again.' => [
        'Could not update the offer. Please try again.',
        'Tarjouksen päivitys epäonnistui. Yritä uudelleen.',
      ],
      'Offers' => ['Offers', 'Tarjoukset'],
      'No offers' => ['No offers', 'Ei tarjouksia'],
      'You will see your offers here' => [
        'You will see your offers here',
        'Näet tarjouksesi täällä',
      ],
      'Actions' => ['Actions', 'Toiminnot'],
      'Project' => ['Project', 'Kohde'],
      'Apartment' => ['Apartment', 'Asunto'],
      'Offer expired' => ['Offer expired', 'Tarjous vanhentunut'],
      'Project material bank' => [
        'Project material bank',
        'Kohteen materiaalipankki',
      ],
      'Apartment page' => ['Apartment page', 'Asunnon sivu'],
      'pending' => ['pending', 'voimassa'],
      'offered' => ['offered', 'tarjottu'],
      'offer accepted' => ['offer accepted', 'Tarjous hyväksytty'],
      'Offer rejected' => ['Offer rejected', 'Tarjous hylätty'],
      'accepted' => ['accepted', 'hyväksytty'],
      'rejected' => ['rejected', 'hylätty'],
      'Customer accepted apartment offer' => [
        'Customer accepted apartment offer',
        'Asiakas hyväksyi asuntotarjouksen',
      ],
      'Customer rejected apartment offer' => [
        'Customer rejected apartment offer',
        'Asiakas hylkäsi asuntotarjouksen',
      ],
      'Reminder: apartment offer awaiting customer response' => [
        'Reminder: apartment offer awaiting customer response',
        'Muistutus: asiakas ei ole vielä vastannut asuntotarjoukseen',
      ],
      'A customer has accepted an apartment offer.' => [
        'A customer has accepted an apartment offer.',
        'Asiakas on hyväksynyt asuntotarjouksen.',
      ],
      'A customer has rejected an apartment offer.' => [
        'A customer has rejected an apartment offer.',
        'Asiakas on hylännyt asuntotarjouksen.',
      ],
      'A customer has not yet responded to an apartment offer before the deadline.' => [
        'A customer has not yet responded to an apartment offer before the deadline.',
        'Asiakas ei ole vielä vastannut asuntotarjoukseen ennen määräaikaa.',
      ],
    ];
  }

  /**
   * asu_content fi.po must contain Finnish translations for offer strings.
   *
   * - Positive: each required msgid has a non-empty Finnish msgstr
   * - Positive: msgstr matches the expected Finnish text
   *
   * @dataProvider requiredOfferTranslationsProvider
   */
  public function testAsuContentHasFinnishOfferTranslation(
    string $msgid,
    string $expectedMsgstr,
  ): void {
    $translations = $this->parsePoFile(
      dirname(__DIR__, 4) . '/asu_content/translations/fi.po'
    );
    $this->assertArrayHasKey(
      $msgid,
      $translations,
      sprintf('Missing msgid "%s" in asu_content/translations/fi.po', $msgid)
    );
    $this->assertNotSame(
      '',
      $translations[$msgid],
      sprintf('Empty msgstr for msgid "%s" in asu_content/translations/fi.po', $msgid)
    );
    $this->assertSame($expectedMsgstr, $translations[$msgid]);
  }

  /**
   * asu_application fi.po must contain Finnish translations for offer strings.
   *
   * - Positive: each required msgid has a non-empty Finnish msgstr
   * - Positive: msgstr matches the expected Finnish text
   *
   * @dataProvider requiredOfferTranslationsProvider
   */
  public function testAsuApplicationHasFinnishOfferTranslation(
    string $msgid,
    string $expectedMsgstr,
  ): void {
    $translations = $this->parsePoFile(
      dirname(__DIR__, 3) . '/translations/fi.po'
    );
    $this->assertArrayHasKey(
      $msgid,
      $translations,
      sprintf('Missing msgid "%s" in asu_application/translations/fi.po', $msgid)
    );
    $this->assertNotSame(
      '',
      $translations[$msgid],
      sprintf(
        'Empty msgstr for msgid "%s" in asu_application/translations/fi.po',
        $msgid
      )
    );
    $this->assertSame($expectedMsgstr, $translations[$msgid]);
  }

  /**
   * Empty msgstr entries must not override a later Finnish translation.
   *
   * - Negative: Project must not resolve to an empty translation
   */
  public function testAsuContentProjectTranslationIsNotEmpty(): void {
    $path = dirname(__DIR__, 4) . '/asu_content/translations/fi.po';
    $entries = $this->parseAllPoEntries($path);
    $projectEntries = array_values(
      array_filter($entries, static fn(array $e): bool => $e['msgid'] === 'Project')
    );
    $this->assertNotEmpty($projectEntries);
    foreach ($projectEntries as $entry) {
      $this->assertNotSame(
        '',
        $entry['msgstr'],
        'asu_content fi.po must not contain empty msgstr for msgid "Project"'
      );
      $this->assertSame('Kohde', $entry['msgstr']);
    }
  }

  /**
   * Parse a .po file into msgid => last non-empty msgstr map.
   *
   * @return array<string, string>
   *   Translation map.
   */
  private function parsePoFile(string $path): array {
    $map = [];
    foreach ($this->parseAllPoEntries($path) as $entry) {
      if ($entry['msgstr'] !== '') {
        $map[$entry['msgid']] = $entry['msgstr'];
      }
      elseif (!isset($map[$entry['msgid']])) {
        $map[$entry['msgid']] = '';
      }
    }
    return $map;
  }

  /**
   * Parse all single-line msgid/msgstr pairs from a .po file.
   *
   * @return list<array{msgid: string, msgstr: string}>
   *   Ordered entries.
   */
  private function parseAllPoEntries(string $path): array {
    $this->assertFileExists($path);
    $contents = file_get_contents($path);
    $this->assertNotFalse($contents);
    preg_match_all(
      '/^msgid "(.*)"\nmsgstr "(.*)"/m',
      $contents,
      $matches,
      PREG_SET_ORDER
    );
    $entries = [];
    foreach ($matches as $match) {
      $entries[] = [
        'msgid' => stripcslashes($match[1]),
        'msgstr' => stripcslashes($match[2]),
      ];
    }
    return $entries;
  }

}
