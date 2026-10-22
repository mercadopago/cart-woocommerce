<?php

namespace MercadoPago\Woocommerce\Tests\Translations;

use PHPUnit\Framework\TestCase;

/**
 * The generic es catalog serves every Spanish locale without its own catalog (PSW-4730): it is the
 * Argentine catalog, except for the card checkout copy (Super Token block and new card form), which
 * comes from es_MX, and two Credits entries use the neutral wording approved in the handoff.
 * WordPress loads the compiled .mo files, so they must match their .po sources.
 */
class SpanishCatalogParityTest extends TestCase
{
    private const LANGUAGES_DIR = __DIR__ . '/../../i18n/languages/';

    private const NEUTRAL_SPANISH_CONTEXT_PREFIXES = ['super token', 'card checkout'];

    private const CONTEXT_SEPARATOR = "\x04";

    private const NEUTRAL_CREDITS_COPY = [
        'credits buyer banner' . self::CONTEXT_SEPARATOR . 'Buy now, pay later' => 'Compra ahora, paga después',
        'Buy in up to 12 installments without credit cards' => 'Compra ahora y paga después en hasta 12 cuotas',
    ];

    private const LOCALES = ['es', 'es_AR', 'es_CL', 'es_MX', 'pt'];

    public function testGivenGenericSpanishCatalogWhenComparedThenCardCheckoutCopyComesFromMexicanCatalog(): void
    {
        $generic = $this->readPo('es');
        $mexican = $this->readPo('es_MX');

        $this->assertNotEmpty($this->neutralSpanishEntries($generic));
        $this->assertSame(
            $this->neutralSpanishEntries($mexican),
            $this->neutralSpanishEntries($generic),
            'Run `npm run i18n:generic-es`: the card checkout copy of es must come from es_MX.'
        );
    }

    public function testGivenGenericSpanishCatalogWhenComparedThenTheRestOfThePluginComesFromArgentineCatalog(): void
    {
        $generic   = $this->readPo('es');
        $argentine = $this->readPo('es_AR');

        $this->assertSame(
            $this->otherEntries($argentine),
            $this->otherEntries($generic),
            'Run `npm run i18n:generic-es`: outside card checkout and the two Credits exceptions, es must match es_AR.'
        );
    }

    public function testCompiledNeutralCreditsCopyMatchesTheApprovedWording(): void
    {
        $catalog = $this->readMo('es');
        foreach (self::NEUTRAL_CREDITS_COPY as $key => $expected) {
            $this->assertSame($expected, $catalog[$key], "es: $key");
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function testGivenCatalogWhenCompiledThenMoMatchesPo(string $locale): void
    {
        $translated = array_filter($this->readPo($locale), fn(string $value) => $value !== '');

        $this->assertSame($translated, $this->readMo($locale), "Run `make make-mo`: woocommerce-mercadopago-$locale.mo is stale.");
    }

    /**
     * @dataProvider localeProvider
     */
    public function testGivenCatalogWhenReadThenEveryCardCheckoutEntryIsTranslated(string $locale): void
    {
        foreach ($this->neutralSpanishEntries($this->readPo($locale)) as $key => $value) {
            $this->assertNotSame('', $value, "woocommerce-mercadopago-$locale.po must translate the card checkout entry: $key");
        }
    }

    public function localeProvider(): array
    {
        return array_combine(self::LOCALES, array_map(fn(string $locale) => [$locale], self::LOCALES));
    }

    /** @dataProvider localeProvider */
    public function testTraditionalCreditsNamesAndBannerCopyFollowTheCatalogLanguage(string $locale): void
    {
        $catalog = $this->readMo($locale);
        $expected = $locale === 'pt'
            ? ['Mercado Parcelado', 'Mercado Parcelado', 'Mercado Parcelado']
            : ['Mercado Crédito', 'Mercado Crédito', 'Mercado Crédito'];

        foreach (['credits product name', 'credits seller promotion name', 'credits seller alternative name'] as $index => $context) {
            $this->assertSame($expected[$index], $catalog[$context . self::CONTEXT_SEPARATOR . 'Buy now, pay later']);
        }

        $bannerCopy = $locale === 'pt' ? 'Compre agora, pague depois' : 'Compra ahora, paga después';
        foreach (['credits buyer banner', 'credits seller banner'] as $context) {
            $this->assertSame($bannerCopy, $catalog[$context . self::CONTEXT_SEPARATOR . 'Buy now, pay later']);
        }
    }

    /** @dataProvider creditsCopyProvider */
    public function testCompiledCreditsCopyMatchesTheApprovedWording(string $locale, array $expected): void
    {
        $catalog = $this->readMo($locale);
        $keys = [
            'Buy through Mercado Pago without cards and pay month by month',
            'Find out the available limit',
            'of your Línea de Crédito and',
            'choose the number of installments',
            'Confirm your payment,',
            'which is credited right away and is 100% protected',
            'Pay month by month',
            'from the Mercado Pago app with your preferred payment method',
            'We will take you to Mercado Pago',
            "If you don't have a credits line yet, active it when paying.",
        ];
        foreach (array_combine($keys, $expected) as $key => $translation) {
            $this->assertSame($translation, $catalog[$key], "$locale: $key");
        }
    }

    public function creditsCopyProvider(): array
    {
        $spanish = [
            'Compra con Mercado Pago sin tarjeta y paga mes a mes',
            'Conoce el límite disponible', 'de tu Línea de Crédito y', 'elige la cantidad de cuotas',
            'Confirma tu pago,', 'se acredita al instante y está 100% protegido',
            'Paga mes a mes', 'desde la app de Mercado Pago con el medio que prefieras',
            'Te llevaremos a Mercado Pago',
            'Si aún no tienes la Línea de Crédito, actívala al momento de pagar.',
        ];

        return [
            'Portuguese' => ['pt', [
                'Compre com Mercado Pago sem cartão e pague por mês',
                'Conheça o limite disponível', 'da sua Linha de Crédito e', 'escolha a quantidade de parcelas',
                'Confirme seu pagamento,', 'ele é creditado na hora e está 100% protegido',
                'Pague mês a mês', 'pelo app do Mercado Pago com o meio que preferir',
                'Vamos te levar ao Mercado Pago',
                'Se você ainda não tem a Linha de Crédito, ative-a na hora de pagar.',
            ]],
            'Spanish' => ['es', $spanish],
            'Argentine Spanish' => ['es_AR', $spanish],
            'Mexican Spanish' => ['es_MX', [
                'Compra con Mercado Pago sin tarjeta y paga mes a mes',
                'Conoce el límite disponible', 'de tu Línea de Crédito y', 'elige la cantidad de meses',
                'Confirma tu pago,', 'se acredita al instante y está 100% protegido',
                'Paga mes a mes', 'desde la app de Mercado Pago con el medio que prefieras',
                'Te llevaremos a Mercado Pago',
                'Si aún no tienes la Línea de Crédito, actívala al momento de pagar.',
            ]],
        ];
    }

    public function testCreditsCallersUseContextsPresentInTheCatalogSources(): void
    {
        $catalogs = ['pt' => $this->readPo('pt'), 'es' => $this->readPo('es')];
        $creditsIds = [];
        foreach (array_keys($catalogs['pt']) as $key) {
            if (strpos($key, 'credits ') === 0) {
                $creditsIds[explode(self::CONTEXT_SEPARATOR, $key, 2)[1]] = true;
            }
        }

        foreach (['Helpers/CreditsPresentation.php', 'Translations/AdminTranslations.php', 'Translations/StoreTranslations.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../../src/' . $file);
            $this->assertIsString($source);
            // Literal _x calls only: tokenization tolerates formatting and ignores comments.
            $tokens = array_values(array_filter(token_get_all($source), function ($token) {
                return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }));
            $checked = 0;
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== '_x') {
                    continue;
                }
                $msgid = is_array($tokens[$index + 2]) && $tokens[$index + 2][0] === T_CONSTANT_ENCAPSED_STRING
                    ? stripcslashes(substr($tokens[$index + 2][1], 1, -1)) : null;
                $context = is_array($tokens[$index + 4]) && $tokens[$index + 4][0] === T_CONSTANT_ENCAPSED_STRING
                    ? stripcslashes(substr($tokens[$index + 4][1], 1, -1)) : null;
                if (strpos($context ?? '', 'credits ') !== 0 && !isset($creditsIds[$msgid])) {
                    continue;
                }
                $this->assertNotNull($msgid, "$file: Credits msgid must be literal");
                $this->assertNotNull($context, "$file: Credits context must be literal");
                $this->assertSame('woocommerce-mercadopago', stripcslashes(substr($tokens[$index + 6][1], 1, -1)), $file);
                foreach ($catalogs as $locale => $catalog) {
                    $key = $context . self::CONTEXT_SEPARATOR . $msgid;
                    $this->assertArrayHasKey($key, $catalog, "$file: $locale / $context");
                    $this->assertNotSame('', $catalog[$key], "$file: $locale / $context must be translated");
                }
                $checked++;
            }
            $this->assertGreaterThan(0, $checked, $file);
        }
    }

    private function neutralSpanishEntries(array $entries): array
    {
        return array_filter($entries, fn(string $key) => $this->usesNeutralSpanish($key), ARRAY_FILTER_USE_KEY);
    }

    private function otherEntries(array $entries): array
    {
        return array_filter(
            $entries,
            fn(string $key) => !$this->usesNeutralSpanish($key) && !isset(self::NEUTRAL_CREDITS_COPY[$key]),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function usesNeutralSpanish(string $key): bool
    {
        if (strpos($key, self::CONTEXT_SEPARATOR) === false) {
            return false;
        }
        foreach (self::NEUTRAL_SPANISH_CONTEXT_PREFIXES as $prefix) {
            if (strpos($key, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, string> msgstr by "msgctxt\x04msgid" (or msgid), without the header entry
     */
    private function readPo(string $locale): array
    {
        $content = file_get_contents(self::LANGUAGES_DIR . "woocommerce-mercadopago-$locale.po");
        $this->assertIsString($content);

        $entries = [];
        foreach (preg_split('/\n\s*\n/', trim($content)) as $block) {
            $fields  = ['msgctxt' => null, 'msgid' => null, 'msgstr' => null];
            $current = null;
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^(msgctxt|msgid|msgstr) "(.*)"$/', $line, $match)) {
                    $current          = $match[1];
                    $fields[$current] = stripcslashes($match[2]);
                } elseif ($current !== null && preg_match('/^"(.*)"$/', $line, $match)) {
                    $fields[$current] .= stripcslashes($match[1]);
                }
            }

            if ($fields['msgid'] === null || $fields['msgid'] === '') {
                continue;
            }
            $key           = $fields['msgctxt'] === null ? $fields['msgid'] : $fields['msgctxt'] . self::CONTEXT_SEPARATOR . $fields['msgid'];
            $entries[$key] = (string) $fields['msgstr'];
        }
        ksort($entries);

        return $entries;
    }

    /**
     * @return array<string, string> msgstr by "msgctxt\x04msgid" (or msgid), without the header entry
     */
    private function readMo(string $locale): array
    {
        $content = file_get_contents(self::LANGUAGES_DIR . "woocommerce-mercadopago-$locale.mo");
        $this->assertIsString($content);

        $format = unpack('V', substr($content, 0, 4))[1] === 0x950412de ? 'V' : 'N';
        [, $count, $originalsOffset, $translationsOffset] = array_values(unpack("{$format}4", substr($content, 4, 16)));

        $entries = [];
        for ($index = 0; $index < $count; $index++) {
            $original    = unpack("{$format}2", substr($content, $originalsOffset + $index * 8, 8));
            $translation = unpack("{$format}2", substr($content, $translationsOffset + $index * 8, 8));
            $key         = substr($content, $original[2], $original[1]);
            if ($key === '') {
                continue;
            }
            $entries[$key] = substr($content, $translation[2], $translation[1]);
        }
        ksort($entries);

        return $entries;
    }
}
