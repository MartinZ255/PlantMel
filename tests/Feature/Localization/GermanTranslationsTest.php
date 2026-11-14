<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-42 (Slice 3): Die App spricht Deutsch. Default-Locale `de`, Fallback `en`;
 * lang/de/*.php decken alle Laravel-12-Standardschluessel ab.
 */
#[Group('F-42')]
class GermanTranslationsTest extends TestCase
{
    private const ORIGINALS = 'vendor/laravel/framework/src/Illuminate/Translation/lang/en';

    public function test_default_locale_is_german_with_english_fallback(): void
    {
        $this->assertSame('de', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function languageFiles(): array
    {
        return [
            'validation' => ['validation'],
            'auth' => ['auth'],
            'passwords' => ['passwords'],
            'pagination' => ['pagination'],
        ];
    }

    #[DataProvider('languageFiles')]
    public function test_german_file_has_exactly_the_keys_of_the_english_original(string $file): void
    {
        $english = require base_path(self::ORIGINALS."/{$file}.php");
        $german = require lang_path("de/{$file}.php");

        $ignore = fn (array $lines) => Arr::except($lines, ['custom', 'attributes']);

        $englishKeys = array_keys(Arr::dot($ignore($english)));
        $germanKeys = array_keys(Arr::dot($ignore($german)));
        sort($englishKeys);
        sort($germanKeys);

        $this->assertSame([], array_values(array_diff($englishKeys, $germanKeys)), "Fehlende Schluessel in lang/de/{$file}.php");
        $this->assertSame([], array_values(array_diff($germanKeys, $englishKeys)), "Unbekannte Schluessel in lang/de/{$file}.php");
    }

    #[DataProvider('languageFiles')]
    public function test_german_lines_keep_all_placeholders(string $file): void
    {
        $english = Arr::dot(Arr::except(require base_path(self::ORIGINALS."/{$file}.php"), ['custom', 'attributes']));
        $german = Arr::dot(Arr::except(require lang_path("de/{$file}.php"), ['custom', 'attributes']));

        foreach ($english as $key => $line) {
            preg_match_all('/:[a-z_]+/', (string) $line, $expected);
            preg_match_all('/:[a-z_]+/', (string) ($german[$key] ?? ''), $actual);

            $this->assertEqualsCanonicalizing(
                array_unique($expected[0]),
                array_unique($actual[0]),
                "Platzhalter in {$file}.{$key} weichen ab",
            );
        }
    }

    public function test_validation_uses_german_attribute_names(): void
    {
        $validator = validator(
            ['email' => 'keine-adresse', 'url' => 'kein-link', 'description' => str_repeat('x', 20)],
            ['email' => 'email', 'url' => 'url', 'description' => 'max:10', 'image' => 'required'],
        );

        $errors = $validator->errors();
        $this->assertSame('E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.', $errors->first('email'));
        $this->assertSame('Link muss eine gültige URL sein.', $errors->first('url'));
        $this->assertSame('Beschreibung darf höchstens 10 Zeichen lang sein.', $errors->first('description'));
        $this->assertSame('Bild muss ausgefüllt werden.', $errors->first('image'));
    }

    public function test_fortify_json_strings_are_translated(): void
    {
        $this->assertSame('Das eingegebene Passwort ist falsch.', __('The provided password was incorrect.'));
        $this->assertSame(
            'Zu viele Anmeldeversuche. Bitte versuche es in 30 Sekunden erneut.',
            trans('auth.throttle', ['seconds' => 30]),
        );
    }

    public function test_german_json_file_is_valid(): void
    {
        $lines = json_decode((string) file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($lines);
        $this->assertNotEmpty($lines);
    }

    public function test_html_lang_attribute_is_german(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<html lang="de">', false);
    }
}
