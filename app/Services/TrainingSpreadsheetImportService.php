<?php

namespace App\Services;

use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\TrainingType;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class TrainingSpreadsheetImportService
{
    /**
     * @return array{message: string, errors: list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $groups = Crew::groupOptions();
        $rows = null;
        $headerIndex = null;
        $nameColumn = null;
        $firstDataIndex = null;
        foreach ($this->readWorksheets($file) as $worksheet) {
            $worksheetRows = $worksheet['rows'];
            $candidateHeaderIndex = $this->findHeaderIndex($worksheetRows);
            if ($candidateHeaderIndex === null) {
                continue;
            }

            $headerNameColumn = $this->findNameColumn($worksheetRows[$candidateHeaderIndex]['cells']);
            $firstData = $this->findFirstDataIndex(
                $worksheetRows,
                $candidateHeaderIndex,
                $this->nameColumnCandidates($headerNameColumn, $worksheet['merged_cells']),
            );
            if ($firstData === null) {
                continue;
            }

            $rows = $worksheetRows;
            $headerIndex = $candidateHeaderIndex;
            $nameColumn = $firstData['column'];
            $firstDataIndex = $firstData['index'];
            break;
        }
        if ($rows === null || $headerIndex === null || $nameColumn === null || $firstDataIndex === null) {
            throw new InvalidArgumentException(
                'Header nama crew atau data crew tidak ditemukan pada worksheet mana pun. Pastikan kolom nama berisi NAMA, NAME, atau CREW NAME.',
            );
        }

        $headers = $this->buildHeaders($rows, $headerIndex, $firstDataIndex);
        $personalColumns = $this->personalColumns($headers);
        $excludedColumns = array_fill_keys([...array_values($personalColumns), $nameColumn], true);
        $trainingColumns = array_filter(
            array_diff_key($headers, $excludedColumns),
            fn (string $header): bool => ! in_array($this->normalize($header), ['', 'nr', 'no', 'number'], true),
        );
        $types = TrainingType::query()
            ->orderBy('sort_order')
            ->get();

        if ($trainingColumns === [] || $types->isEmpty()) {
            throw new InvalidArgumentException('Kolom training tidak dikenali pada master.');
        }

        $crewNames = Crew::query()
            ->with('trainingAircrafts')
            ->get()
            ->groupBy(fn (Crew $crew): string => $this->normalize($crew->name));
        $nextSortOrder = ((int) Crew::max('sort_order')) + 1;
        $processed = 0;
        $errors = [];

        foreach (array_slice($rows, $firstDataIndex) as $row) {
            $name = trim((string) ($row['cells'][$nameColumn] ?? ''));
            if ($name === '') {
                continue;
            }

            try {
                $importedCrew = DB::transaction(function () use (
                    $row,
                    $name,
                    $groups,
                    $personalColumns,
                    $trainingColumns,
                    $types,
                    $crewNames,
                    &$nextSortOrder,
                ): Crew {
                    $crew = $this->findCrew($crewNames, $name, $row['number']);
                    $attributes = $this->personalAttributes(
                        $row['cells'],
                        $personalColumns,
                        $row['number'],
                    );
                    $position = $attributes['position'] ?? null;
                    $pilotRole = $this->pilotRole($position);

                    if ($crew === null) {
                        $crew = Crew::create([
                            'name' => $name,
                            'group' => null,
                            'category' => 'Pilot',
                            'fleet' => 'Belum ditetapkan',
                            'position' => $position ?? '',
                            'pilot_role' => $pilotRole,
                            'sort_order' => $nextSortOrder++,
                            ...$attributes,
                        ]);
                    } elseif ($attributes !== []) {
                        if ($pilotRole !== null) {
                            $attributes['pilot_role'] = $pilotRole;
                        }
                        $crew->update($attributes);
                    }

                    $crew->loadMissing('trainingAircrafts');

                    foreach ($trainingColumns as $column => $header) {
                        $value = trim((string) ($row['cells'][$column] ?? ''));
                        if ($value === '') {
                            continue;
                        }

                        $type = $this->findTrainingType($types, $header, $groups, $row['number']);

                        $lastCheck = $this->parseDate($value, $row['number']);
                        CrewTraining::updateOrCreate(
                            ['crew_id' => $crew->id, 'training_type_id' => $type->id],
                            ['last_check' => $lastCheck],
                        );
                    }

                    return $crew;
                });
                $nameKey = $this->normalize($name);
                /** @var EloquentCollection<int, Crew> $matches */
                $matches = $crewNames->get($nameKey, new EloquentCollection);
                if (! $matches->contains('id', $importedCrew->id)) {
                    $matches->push($importedCrew);
                    $crewNames->put($nameKey, $matches);
                }

                $processed++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $errorCount = count($errors);

        return [
            'message' => "Impor selesai: {$processed} baris crew diproses".($errorCount > 0 ? ", {$errorCount} dilewati." : '.'),
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * @return list<array{
     *     rows: list<array{number: int, cells: array<int, string>}>,
     *     merged_cells: list<string>
     * }>
     */
    private function readWorksheets(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZIP diperlukan untuk membaca file XLSX.');
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException('File Excel tidak dapat dibaca.');
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new InvalidArgumentException('File Excel tidak valid atau tidak dapat dibuka.');
        }

        try {
            if ($archive->numFiles > 1000) {
                throw new InvalidArgumentException('File Excel berisi terlalu banyak bagian.');
            }

            $uncompressedSize = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $fileStat = $archive->statIndex($index);
                if ($fileStat === false) {
                    throw new InvalidArgumentException('Struktur arsip Excel tidak valid.');
                }
                $uncompressedSize += $fileStat['size'];
                if ($uncompressedSize > 25 * 1024 * 1024) {
                    throw new InvalidArgumentException('Ukuran isi Excel melebihi batas 25 MB.');
                }
            }

            $workbook = $this->readXml($archive, 'xl/workbook.xml');
            $relationships = $this->readXml($archive, 'xl/_rels/workbook.xml.rels');
            $sheets = $workbook->sheets->sheet ?? [];
            if (count($sheets) === 0) {
                throw new InvalidArgumentException('File Excel tidak memiliki worksheet.');
            }

            $targets = [];
            foreach ($relationships->Relationship as $relationship) {
                $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
            }

            $sharedStrings = $this->readSharedStrings($archive);
            $worksheets = [];
            foreach ($sheets as $sheet) {
                $relationshipId = (string) $sheet->attributes(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                )['id'];
                $target = $targets[$relationshipId] ?? null;
                if ($target === null || str_contains($target, '..')) {
                    continue;
                }

                $worksheetPath = ltrim($target, '/');
                if (! str_starts_with($worksheetPath, 'xl/')) {
                    $worksheetPath = 'xl/'.$worksheetPath;
                }

                $worksheet = $this->readXml($archive, $worksheetPath);
                $rows = [];

                foreach ($worksheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $xmlRow) {
                    $cells = [];
                    foreach ($xmlRow->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                        $reference = (string) $cell['r'];
                        if (preg_match('/^([A-Z]+)/i', $reference, $matches) !== 1) {
                            continue;
                        }

                        $column = $this->columnIndex(Str::upper($matches[1]));
                        $type = (string) $cell['t'];
                        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                        $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';

                        if ($type === 's') {
                            $value = $sharedStrings[(int) $value] ?? '';
                        } elseif ($type === 'inlineStr') {
                            $inlineNodes = $cell->xpath('./*[local-name()="is"]') ?: [];
                            $value = isset($inlineNodes[0]) ? $this->inlineString($inlineNodes[0]) : '';
                        } elseif ($type === 'b') {
                            $value = $value === '1' ? '1' : '';
                        }

                        $cells[$column] = $value;
                    }

                    $rows[] = [
                        'number' => (int) $xmlRow['r'],
                        'cells' => $cells,
                    ];
                }

                $mergedCells = [];
                foreach ($worksheet->xpath('//*[local-name()="mergeCell"]') ?: [] as $mergedCell) {
                    $mergedCells[] = (string) $mergedCell['ref'];
                }

                $worksheets[] = [
                    'rows' => $rows,
                    'merged_cells' => $mergedCells,
                ];
            }

            return $worksheets;
        } finally {
            $archive->close();
        }
    }

    private function readXml(ZipArchive $archive, string $path): SimpleXMLElement
    {
        $contents = $archive->getFromName($path);
        if ($contents === false) {
            throw new InvalidArgumentException('File Excel tidak lengkap atau worksheet tidak ditemukan.');
        }

        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if (! $xml instanceof SimpleXMLElement) {
                throw new InvalidArgumentException('Struktur XML pada file Excel tidak valid.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        return $xml;
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $archive): array
    {
        if ($archive->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];
        foreach ($this->readXml($archive, 'xl/sharedStrings.xml')->si as $item) {
            $parts = [];
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function inlineString(?SimpleXMLElement $inline): string
    {
        if (! $inline) {
            return '';
        }

        $parts = [];
        foreach ($inline->xpath('.//*[local-name()="t"]') ?: [] as $text) {
            $parts[] = (string) $text;
        }

        return implode('', $parts);
    }

    /**
     * @param  list<array{number: int, cells: array<int, string>}>  $rows
     */
    private function findHeaderIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($row['cells'] as $value) {
                if ($this->isNameHeader($value)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function findNameColumn(array $cells): int
    {
        foreach ($cells as $column => $value) {
            if ($this->isNameHeader($value)) {
                return $column;
            }
        }

        throw new InvalidArgumentException('Kolom nama crew tidak ditemukan pada header Excel.');
    }

    /**
     * @param  list<array{number: int, cells: array<int, string>}>  $rows
     */
    /**
     * @param  list<array{number: int, cells: array<int, string>}>  $rows
     * @param  list<int>  $nameColumns
     * @return array{index: int, column: int}|null
     */
    private function findFirstDataIndex(array $rows, int $headerIndex, array $nameColumns): ?array
    {
        $counts = array_fill_keys($nameColumns, 0);
        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }

            foreach ($nameColumns as $column) {
                $value = trim((string) ($row['cells'][$column] ?? ''));
                if ($value !== '' && ! is_numeric($value)) {
                    $counts[$column]++;
                }
            }
        }

        arsort($counts);
        $nameColumn = array_key_first($counts);
        if ($nameColumn === null || $counts[$nameColumn] === 0) {
            return null;
        }

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }

            $name = trim((string) ($row['cells'][$nameColumn] ?? ''));
            if ($name !== '' && ! is_numeric($name)) {
                return ['index' => $index, 'column' => $nameColumn];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $mergedCells
     * @return list<int>
     */
    private function nameColumnCandidates(int $headerColumn, array $mergedCells): array
    {
        foreach ($mergedCells as $range) {
            if (preg_match('/^([A-Z]+)\d+:([A-Z]+)\d+$/i', $range, $matches) !== 1) {
                continue;
            }

            $start = $this->columnIndex(Str::upper($matches[1]));
            $end = $this->columnIndex(Str::upper($matches[2]));
            if ($headerColumn < $start || $headerColumn > $end) {
                continue;
            }

            return range($start, $end);
        }

        return [$headerColumn];
    }

    /**
     * @param  list<array{number: int, cells: array<int, string>}>  $rows
     * @return array<int, string>
     */
    private function buildHeaders(array $rows, int $headerIndex, int $firstDataIndex): array
    {
        $headerRows = array_slice($rows, $headerIndex, $firstDataIndex - $headerIndex);
        $columnKeys = array_merge(...array_map(
            fn (array $row): array => array_keys($row['cells']),
            $headerRows,
        ));
        if ($columnKeys === []) {
            throw new InvalidArgumentException('Header Excel tidak memiliki kolom.');
        }

        $lastColumn = max($columnKeys);
        $personalHeaderNames = [
            'name', 'dob', 'date of birth', 'address and phone number', 'address',
            'passport expiry date', 'passport expiry', 'passport no', 'passport number',
            'nationality', 'joined iat', 'licence', 'license', 'rating', 'position',
            'remarks for row', 'remarks',
        ];
        $trainingStart = $lastColumn + 1;

        foreach ($headerRows as $row) {
            foreach ($row['cells'] as $column => $value) {
                if (in_array($this->normalize($value), $personalHeaderNames, true)) {
                    $trainingStart = min($trainingStart, $column + 1);
                }
            }
        }

        $filledRows = [];
        foreach ($headerRows as $row) {
            $cells = $row['cells'];
            $carry = '';
            for ($column = $trainingStart; $column <= $lastColumn; $column++) {
                $value = trim((string) ($cells[$column] ?? ''));
                if ($value !== '') {
                    $carry = $value;
                } elseif ($carry !== '') {
                    $cells[$column] = $carry;
                }
            }
            $filledRows[] = $cells;
        }

        $headers = [];
        for ($column = 0; $column <= $lastColumn; $column++) {
            $parts = [];
            foreach ($filledRows as $cells) {
                $value = trim((string) ($cells[$column] ?? ''));
                if ($value !== '' && ($parts === [] || end($parts) !== $value)) {
                    $parts[] = $value;
                }
            }
            $headers[$column] = implode(' ', $parts);
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function personalColumns(array $headers): array
    {
        $columns = [];
        foreach ($headers as $column => $header) {
            $normalized = $this->normalize($header);
            $field = match (true) {
                $this->isNameHeader($header) => 'name',
                $normalized === 'dob', $normalized === 'date of birth' => 'dob',
                str_contains($normalized, 'address') => 'address',
                str_contains($normalized, 'passport') && str_contains($normalized, 'expiry') => 'passport_expiry',
                in_array($normalized, ['passport no', 'passport number'], true) => 'passport_no',
                $normalized === 'nationality' => 'nationality',
                str_contains($normalized, 'joined iat') => 'joined_iat',
                in_array($normalized, ['licence', 'license'], true) => 'licence',
                $normalized === 'rating' => 'rating',
                $normalized === 'position' => 'position',
                str_contains($normalized, 'remarks') => 'remarks',
                default => null,
            };
            if ($field !== null) {
                $columns[$field] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<int, string>  $cells
     * @return array<string, string>
     */
    private function personalAttributes(array $cells, array $columns, int $rowNumber): array
    {
        $attributes = [];
        foreach ([
            'dob', 'address', 'passport_no', 'passport_expiry', 'nationality',
            'joined_iat', 'licence', 'rating', 'position', 'remarks',
        ] as $field) {
            if (! isset($columns[$field])) {
                continue;
            }

            $value = trim((string) ($cells[$columns[$field]] ?? ''));
            if ($value === '') {
                continue;
            }

            $attributes[$field] = in_array($field, ['dob', 'passport_expiry', 'joined_iat'], true)
                ? $this->parseDate($value, $rowNumber)
                : $value;
        }

        return $attributes;
    }

    /**
     * @param  Collection<string, EloquentCollection<int, Crew>>  $crewNames
     */
    private function findCrew(Collection $crewNames, string $name, int $rowNumber): ?Crew
    {
        /** @var EloquentCollection<int, Crew> $matches */
        $matches = $crewNames->get($this->normalize($name), new EloquentCollection);
        if ($matches->count() > 1) {
            throw new InvalidArgumentException(
                "baris {$rowNumber}: nama crew {$name} cocok dengan lebih dari satu record.",
            );
        }

        return $matches->first();
    }

    /**
     * @param  EloquentCollection<int, TrainingType>  $types
     * @param  array<string, array{label: string, category: string}>  $groups
     */
    private function findTrainingType(
        EloquentCollection $types,
        string $header,
        array $groups,
        int $rowNumber,
    ): TrainingType {
        $normalizedHeader = $this->normalize($header);
        $matches = $types->filter(
            fn (TrainingType $type): bool => str_contains(
                $normalizedHeader,
                $this->normalize($type->name),
            ),
        );

        if ($matches->count() > 1) {
            $sectionMatches = $matches->filter(
                fn (TrainingType $type): bool => str_contains(
                    $normalizedHeader,
                    $this->normalize($type->section),
                ),
            );
            if ($sectionMatches->isNotEmpty()) {
                $matches = $sectionMatches;
            }
        }

        if ($matches->count() > 1) {
            $groupMatches = $matches->filter(function (TrainingType $type) use ($groups, $normalizedHeader): bool {
                $label = $groups[$type->group]['label'] ?? '';

                return $label !== '' && str_contains($normalizedHeader, $this->normalize($label));
            });
            if ($groupMatches->isNotEmpty()) {
                $matches = $groupMatches;
            }
        }

        if ($matches->count() !== 1) {
            $message = $matches->isEmpty()
                ? "kolom training '{$header}' tidak ditemukan pada master"
                : 'nama kolom training cocok dengan lebih dari satu item pada master';
            throw new InvalidArgumentException("baris {$rowNumber}: {$message}.");
        }

        return $matches->first();
    }

    private function parseDate(string $value, int $rowNumber): string
    {
        $value = trim($value);
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial <= 2958465) {
                return (new DateTimeImmutable('1899-12-30'))
                    ->modify('+'.(int) floor($serial).' days')
                    ->format('Y-m-d');
            }
        }

        foreach (['!d/m/Y', '!d-m-Y', '!d.m.Y', '!Y-m-d', '!Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $issues = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("baris {$rowNumber}: format tanggal tidak dikenali.");
        }
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))) ?? '');
    }

    private function isNameHeader(string $value): bool
    {
        $normalized = $this->normalize($value);
        $compact = str_replace(' ', '', $normalized);

        return in_array($normalized, [
            'name',
            'crew name',
            'name crew',
            'name of crew',
            'full name',
            'crew full name',
        ], true) || in_array($compact, ['name', 'crewname', 'nameofcrew', 'fullname', 'crewfullname'], true);
    }

    private function pilotRole(?string $position): ?string
    {
        $role = Str::upper(trim((string) $position));

        return in_array($role, ['PIC', 'SIC'], true) ? $role : null;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - ord('A') + 1;
        }

        return $index - 1;
    }
}
