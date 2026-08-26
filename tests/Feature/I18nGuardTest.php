<?php

/**
 * FLAG-4 (t_dd9cb24b): i18n audit guard.
 *
 * The pipeline is lang/translations.csv -> app:generate-language-files, which
 * must keep four surfaces in lockstep:
 *   - lang/{en,id}.json                  (backend JSON loader)
 *   - lang/{en,id}/app.php               (backend group loader)
 *   - resources/js/lang/{en,id}/app.json (vue-i18n bundle)
 *
 * Two regressions happen in practice when keys are added by hand or a surface
 * regenerates from stale input:
 *   1. code references a key no locale defines (users see raw keys — this
 *      shipped once: validation_error/update_data were undefined until the
 *      2026-08-26 audit),
 *   2. surfaces drift apart (one locale or bundle missing keys).
 *
 * These tests re-run the audit at test time so either regression goes red.
 * Pure filesystem checks — no database.
 */
function i18nExtractUsedKeys(string $dir, array $regexes): array
{
    $keys = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (! in_array($file->getExtension(), ['php', 'vue', 'ts'])) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        foreach ($regexes as $regex) {
            preg_match_all($regex, $contents, $m);
            $keys = [...$keys, ...$m[1]];
        }
    }

    return array_values(array_unique($keys));
}

/** Flat 'key' => ... entries of a lang/{locale}/app.php group file. */
function i18nPhpGroupKeys(string $path): array
{
    preg_match_all("/^\s{4}'([a-z0-9_]+)'\s*=>/m", (string) file_get_contents($path), $m);

    return $m[1];
}

/** Top-level keys of a flat translation JSON file. */
function i18nJsonKeys(string $path): array
{
    return array_keys(json_decode((string) file_get_contents($path), true));
}

/**
 * __('app.x') reads group 'app' => key 'x' in app.php; bare keys read the
 * JSON loader — normalize before membership checks.
 */
function i18nBackendMissing(array $used, string $locale): array
{
    $defined = [
        ...i18nPhpGroupKeys(lang_path($locale.'/app.php')),
        ...i18nJsonKeys(lang_path("$locale.json")),
    ];

    return array_values(array_filter(
        $used,
        fn (string $key) => ! in_array(
            str_starts_with($key, 'app.') ? substr($key, 4) : $key,
            $defined
        )
    ));
}

test('every frontend translation key is defined in both locale bundles', function () {
    $used = i18nExtractUsedKeys(resource_path('js'), [
        "/__\(\s*['\"]([a-zA-Z0-9_.]+)['\"]/",       // js __() helper
        "/(?:\\\$|\W)t\(\s*['\"]([a-zA-Z0-9_.]+)['\"]/", // vue-i18n $t()/t()
    ]);

    expect($used)->not->toBeEmpty();

    foreach (['en', 'id'] as $locale) {
        $defined = i18nJsonKeys(resource_path("js/lang/$locale/app.json"));
        $missing = array_values(array_diff($used, $defined));

        // [] = every used key is defined; a non-empty diff lists raw-key leaks
        expect($missing)->toBe([]);
    }
});

test('every backend translation key is defined in both locales', function () {
    $used = [
        ...i18nExtractUsedKeys(app_path(), ["/__\(\s*['\"]([a-zA-Z0-9_.]+)['\"]/"]),
        ...i18nExtractUsedKeys(base_path('routes'), ["/__\(\s*['\"]([a-zA-Z0-9_.]+)['\"]/"]),
    ];

    expect($used)->not->toBeEmpty();

    foreach (['en', 'id'] as $locale) {
        expect(i18nBackendMissing($used, $locale))->toBe([]);
    }
});

test('generated language surfaces stay byte-identical between backend and frontend bundles', function () {
    foreach (['en', 'id'] as $locale) {
        $backend = lang_path("$locale.json");
        $frontend = resource_path("js/lang/$locale/app.json");

        // identical hashes = bundles generated from the same source
        expect(md5_file($backend))->toBe(md5_file($frontend));
    }
});

test('en and id locales define the same keysets on every surface', function () {
    // JSON loader surface
    $en = i18nJsonKeys(lang_path('en.json'));
    $id = i18nJsonKeys(lang_path('id.json'));
    sort($en);
    sort($id);
    expect($id)->toBe($en);

    // Backend group surface
    $en = i18nPhpGroupKeys(lang_path('en/app.php'));
    $id = i18nPhpGroupKeys(lang_path('id/app.php'));
    sort($en);
    sort($id);
    expect($id)->toBe($en);

    // Frontend bundle surface
    $en = i18nJsonKeys(resource_path('js/lang/en/app.json'));
    $id = i18nJsonKeys(resource_path('js/lang/id/app.json'));
    sort($en);
    sort($id);
    expect($id)->toBe($en);
});
