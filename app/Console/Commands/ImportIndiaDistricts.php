<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SplFileObject;

class ImportIndiaDistricts extends Command
{

    protected $signature = 'import:india-districts {csv : Path to the official LGD India state/district CSV file}';

    protected $description = 'Import Indian States/UTs and Districts from an official LGD CSV file.';

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        $fullPath = $this->resolvePath($path);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            $this->error("CSV file not found or not readable: {$path}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            $this->error('Required tables are missing. Run database/manual/ded_district_setup.sql manually first.');

    protected $signature = 'import:india-districts {csv : Path to official LGD/Government of India CSV, e.g. storage/app/india_districts.csv}';

    protected $description = 'Import Indian States/UTs and districts from an official LGD/Government of India CSV.';

    public function handle(): int
    {
        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            $this->error('The states and districts tables must exist before importing. Run the provided manual PostgreSQL SQL first.');


            return self::FAILURE;
        }


        $beforeStates = State::query()->count();
        $beforeDistricts = District::query()->count();

        $this->info("Existing states: {$beforeStates}");
        $this->info("Existing districts: {$beforeDistricts}");

        try {
            $rows = $this->readRows($fullPath);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

        if (! Schema::hasColumn('districts', 'state_id')) {
            $this->error('The districts table must include state_id before importing. Run the provided manual PostgreSQL SQL first.');


            return self::FAILURE;
        }


        if ($rows === []) {
            $this->warn('No valid rows found in CSV.');

            return self::SUCCESS;
        }

        $stats = [
            'rows' => 0,
            'states_upserted' => 0,
            'districts_upserted' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($rows, &$stats): void {
            foreach ($rows as $row) {
                $stats['rows']++;

                $stateName = $this->cleanName($row['state_name'] ?? '');
                $districtName = $this->cleanName($row['district_name'] ?? '');

                if ($stateName === '' || $districtName === '') {
                    $stats['skipped']++;
                    continue;
                }

                $state = State::query()->updateOrCreate(
                    ['name' => $stateName],
                    ['status' => 'active']
                );
                $stats['states_upserted']++;

                District::query()->updateOrCreate(
                    [
                        'state_id' => $state->id,
                        'name' => $districtName,
                    ],
                    ['status' => 'active']
                );
                $stats['districts_upserted']++;
            }
        });

        $afterStates = State::query()->count();
        $afterDistricts = District::query()->count();

        $this->newLine();
        $this->info('India LGD district import completed.');
        $this->line('Rows processed: ' . $stats['rows']);
        $this->line('Rows skipped: ' . $stats['skipped']);
        $this->line('States upserted: ' . $stats['states_upserted']);
        $this->line('Districts upserted: ' . $stats['districts_upserted']);
        $this->line("States count: {$beforeStates} -> {$afterStates}");
        $this->line("Districts count: {$beforeDistricts} -> {$afterDistricts}");

        $path = $this->resolveCsvPath((string) $this->argument('csv'));
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV file is not readable: {$path}");

            return self::FAILURE;
        }

        $statesBefore = State::query()->count();
        $districtsBefore = District::query()->count();
        $this->info("Existing states: {$statesBefore}");
        $this->info("Existing districts: {$districtsBefore}");

        [$headers, $rows] = $this->readCsv($path);
        if (! $this->hasSupportedHeaders($headers)) {
            $this->error('CSV headers must be state_name,district_name or state_code,state_name,district_code,district_name.');

            return self::FAILURE;
        }

        $stateRows = [];
        $districtRows = [];
        foreach ($rows as $row) {
            $stateName = $this->cleanName($row['state_name'] ?? '');
            $districtName = $this->cleanName($row['district_name'] ?? '');

            if ($stateName === '' || $districtName === '') {
                continue;
            }

            $stateKey = Str::lower($stateName);
            $districtKey = $stateKey . '|' . Str::lower($districtName);
            $stateRows[$stateKey] = $stateName;
            $districtRows[$districtKey] = [
                'state_name' => $stateName,
                'district_name' => $districtName,
            ];
        }

        if ($stateRows === [] || $districtRows === []) {
            $this->error('No valid state/district rows were found in the CSV.');

            return self::FAILURE;
        }

        $createdStates = 0;
        $updatedStates = 0;
        $createdDistricts = 0;
        $updatedDistricts = 0;

        DB::transaction(function () use ($stateRows, $districtRows, &$createdStates, &$updatedStates, &$createdDistricts, &$updatedDistricts): void {
            $stateIdByName = [];

            foreach ($stateRows as $stateName) {
                $state = State::query()
                    ->whereRaw('LOWER(name) = ?', [Str::lower($stateName)])
                    ->first();

                if ($state) {
                    $state->forceFill([
                        'name' => $stateName,
                        'status' => 'active',
                    ])->save();
                    $updatedStates++;
                } else {
                    $state = State::query()->updateOrCreate(
                        ['name' => $stateName],
                        ['status' => 'active']
                    );
                    $createdStates++;
                }

                $stateIdByName[Str::lower($stateName)] = (string) $state->id;
            }

            foreach ($districtRows as $districtRow) {
                $stateId = $stateIdByName[Str::lower($districtRow['state_name'])] ?? null;
                if (! $stateId) {
                    continue;
                }

                $district = District::query()
                    ->where('state_id', $stateId)
                    ->whereRaw('LOWER(name) = ?', [Str::lower($districtRow['district_name'])])
                    ->first();

                if ($district) {
                    $district->forceFill([
                        'name' => $districtRow['district_name'],
                        'status' => 'active',
                    ])->save();
                    $updatedDistricts++;
                } else {
                    District::query()->updateOrCreate(
                        [
                            'state_id' => $stateId,
                            'name' => $districtRow['district_name'],
                        ],
                        ['status' => 'active']
                    );
                    $createdDistricts++;
                }
            }
        });

        $statesAfter = State::query()->count();
        $districtsAfter = District::query()->count();

        $this->info("Created states: {$createdStates}");
        $this->info("Updated states: {$updatedStates}");
        $this->info("Created districts: {$createdDistricts}");
        $this->info("Updated districts: {$updatedDistricts}");
        $this->info("States after import: {$statesAfter}");
        $this->info("Districts after import: {$districtsAfter}");


        return self::SUCCESS;
    }


    private function resolvePath(string $path): string

    private function resolveCsvPath(string $path): string

    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }


    /**
     * @return array<int, array<string, string|null>>
     */
    private function readRows(string $path): array

    private function readCsv(string $path): array

    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = [];
        $rows = [];


        foreach ($file as $lineNumber => $row) {
            if (! is_array($row) || $row === [null] || $row === false) {
                continue;
            }

            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if ($lineNumber === 0) {
                $headers = $this->normalizeHeaders($row);
                if (isset($headers[0])) {
                    $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
                }
                $this->validateHeaders($headers);
                continue;
            }

            if ($headers === []) {
                continue;
            }

            $rows[] = array_combine($headers, array_slice(array_pad($row, count($headers), null), 0, count($headers)));
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $normalized = Str::of((string) $header)
                ->trim()
                ->lower()
                ->replace([' ', '-', '/', '.'], '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->toString();

            return match ($normalized) {
                'state', 'state_or_ut', 'state_ut', 'state_name_english', 'statename' => 'state_name',
                'district', 'district_name_english', 'districtname' => 'district_name',
                'state_code', 'state_lgd_code', 'lgd_state_code' => 'state_code',
                'district_code', 'district_lgd_code', 'lgd_district_code' => 'district_code',
                default => $normalized,
            };
        }, $headers);
    }

    /**
     * @param array<int, string> $headers
     */
    private function validateHeaders(array $headers): void
    {
        if (! in_array('state_name', $headers, true) || ! in_array('district_name', $headers, true)) {
            throw new \InvalidArgumentException('CSV must contain state_name,district_name or state_code,state_name,district_code,district_name columns.');
        }
    }

    private function cleanName(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return $value;

        foreach ($file as $index => $columns) {
            if (! is_array($columns) || $columns === [null]) {
                continue;
            }

            if ($index === 0) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $columns);
                continue;
            }

            $row = [];
            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($columns[$columnIndex] ?? ''));
            }

            $rows[] = $row;
        }

        return [$headers, $rows];
    }

    private function hasSupportedHeaders(array $headers): bool
    {
        $headerSet = array_flip($headers);
        $hasSimpleFormat = isset($headerSet['state_name'], $headerSet['district_name']);
        $hasLgdFormat = isset($headerSet['state_code'], $headerSet['state_name'], $headerSet['district_code'], $headerSet['district_name']);

        return $hasSimpleFormat || $hasLgdFormat;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->trim()
            ->lower()
            ->replace([' ', '-', '/', '\\'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    private function cleanName(string $value): string
    {
        return trim((string) Str::of($value)->replaceMatches('/\s+/', ' '));

    }
}
