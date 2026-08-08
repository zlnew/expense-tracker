<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLanguageFiles extends Command
{
    protected $signature = 'app:generate-language-files';

    protected $description = 'Generate language files from lang/translations.csv';

    public function handle(): void
    {
        $csvPath = base_path('lang/translations.csv');

        if (! file_exists($csvPath)) {
            $this->error('Missing translations CSV at: '.$csvPath);

            return;
        }

        $translations = $this->readTranslations($csvPath);

        if ($translations === null) {
            $this->error('Unable to read translations CSV.');

            return;
        }

        if (empty($translations['en']) && empty($translations['id'])) {
            $this->warn('No translations found.');

            return;
        }

        $this->writePhpLangFile('en', $translations['en']);
        $this->writePhpLangFile('id', $translations['id']);

        $enJsonPath = $this->writeJsonLangFile('en', $translations['en']);
        $idJsonPath = $this->writeJsonLangFile('id', $translations['id']);

        copy($enJsonPath, 'resources/js/lang/en/app.json');
        copy($idJsonPath, 'resources/js/lang/id/app.json');

        if (! app()->environment('testing')) {
            $this->info('Running code quality tools...');
            shell_exec('vendor/bin/pint');
        }

        $this->info('Language files generated successfully.');
    }

    /**
     * @return array{en: array<string, string>, id: array<string, string>}|null
     */
    private function readTranslations(string $csvPath): ?array
    {
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return null;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return null;
        }

        $header = array_map(
            fn (?string $value): string => $value ?? '',
            $header,
        );

        $indexes = array_flip($header);
        $requiredHeaders = ['key', 'en', 'id'];

        foreach ($requiredHeaders as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $indexes)) {
                fclose($handle);

                return null;
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowData = [];

            foreach ($header as $columnIndex => $columnName) {
                $rowData[$columnName] = $row[$columnIndex] ?? '';
            }

            $key = mb_trim($rowData['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'data' => $rowData,
            ];
        }

        fclose($handle);

        usort(
            $rows,
            fn (array $left, array $right): int => strcmp($left['key'], $right['key']),
        );

        $this->writeSortedCsv($csvPath, $header, $rows);

        $translations = [
            'en' => [],
            'id' => [],
        ];

        foreach ($rows as $row) {
            $translations['en'][$row['key']] = $row['data']['en'] ?? '';
            $translations['id'][$row['key']] = $row['data']['id'] ?? '';
        }

        return $translations;
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function writePhpLangFile(string $locale, array $translations): string
    {
        $path = lang_path($locale.'/app.php');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $content = "<?php\n\nreturn ".$this->exportArray($translations).";\n";
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function writeJsonLangFile(string $locale, array $translations): string
    {
        $path = lang_path($locale.'.json');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $normalizedTranslations = [];

        foreach ($translations as $key => $value) {
            $normalizedTranslations[$key] = $this->normalizeTranslationPlaceholders($value);
        }

        $content = json_encode($normalizedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            $this->error(sprintf('Unable to encode %s JSON language file.', $locale));

            return $path;
        }

        file_put_contents($path, $content."\n");

        return $path;
    }

    private function normalizeTranslationPlaceholders(string $value): string
    {
        return str_replace(
            [
                ':data',
                ':date',
                ':name',
                ':student',
                ':activity',
                ':period',
            ],
            [
                '{data}',
                '{date}',
                '{name}',
                '{student}',
                '{activity}',
                '{period}',
            ],
            $value
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    private function exportArray(array $data, int $indent = 0): string
    {
        if ($data === []) {
            return '[]';
        }

        $indentation = str_repeat('    ', $indent);
        $nextIndentation = str_repeat('    ', $indent + 1);
        $lines = [];

        foreach ($data as $key => $value) {
            $exportedKey = var_export($key, true);
            $exportedValue = var_export($value, true);

            $lines[] = $nextIndentation.$exportedKey.' => '.$exportedValue.',';
        }

        return "[\n".implode("\n", $lines)."\n".$indentation.']';
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array{key: string, data: array<string, string>}>  $rows
     */
    private function writeSortedCsv(string $csvPath, array $header, array $rows): void
    {
        $handle = fopen($csvPath, 'w');

        if ($handle === false) {
            return;
        }

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            $outputRow = [];

            foreach ($header as $columnName) {
                $outputRow[] = $row['data'][$columnName] ?? '';
            }

            fputcsv($handle, $outputRow);
        }

        fclose($handle);
    }
}
