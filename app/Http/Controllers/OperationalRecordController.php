<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FloodIncidentRecord;
use App\Services\OperationalFloodEnrichmentService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalRecordController extends Controller
{
    private const FLOOD_TABLE = 'flood_incident_records';

    private const REPORT_DIMENSIONS = [
        'barangay' => 'Barangay',
        'year' => 'Year',
        'month' => 'Month',
        'flood_code' => 'Flood Code',
        'status' => 'Status',
        'storm_signal' => 'Storm Signal',
        'nearest_waterway' => 'Nearest Waterway',
        'day_of_week' => 'Day of Week',
    ];

    private const REPORT_MEASURES = [
        'records' => 'Number of Records',
        'rainfall_24h_mm' => 'Rainfall 24h (mm)',
        'rainfall_3d_mm' => 'Rainfall 3d (mm)',
        'rainfall_7d_mm' => 'Rainfall 7d (mm)',
        'duration_hours' => 'Flood Duration (hours)',
        'elevation_m' => 'Elevation (m)',
        'distance_to_waterway_m' => 'Distance to Waterway (m)',
    ];

    public function index(Request $request): View
    {
        $datasets = $this->datasets();
        $datasetKey = $request->string('dataset', 'flood-records')->toString();
        $dataset = $this->resolveDataset($datasets, $datasetKey);
        $filters = $this->validatedFilters($request, $dataset);

        $records = $this->filteredQuery($dataset, $filters)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $record): object => $this->formatRecordDates($record, $dataset));

        $barangays = Schema::hasTable('barangays')
            ? Barangay::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $datasetCounts = collect($datasets)->mapWithKeys(
            fn (array $definition, string $key): array => [
                $key => Schema::hasTable($definition['table'])
                    ? $this->baseQuery($definition)->count()
                    : null,
            ]
        );

        return view('operational-records.index', compact(
            'datasets',
            'datasetKey',
            'dataset',
            'records',
            'filters',
            'barangays',
            'datasetCounts'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $datasets = $this->datasets();
        $datasetKey = $request->string('dataset', 'flood-records')->toString();
        $dataset = $this->resolveDataset($datasets, $datasetKey);
        $filters = $this->validatedFilters($request, $dataset);
        $fileName = $datasetKey . '-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($dataset, $filters): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values($dataset['columns']));

            foreach ($this->filteredQuery($dataset, $filters)->limit(100000)->cursor() as $record) {
                $record = $this->formatRecordDates($record, $dataset);
                $row = [];

                foreach (array_keys($dataset['columns']) as $column) {
                    $row[] = $this->csvValue(data_get($record, $column));
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportBuilder(Request $request): View
    {
        abort_unless(Schema::hasTable(self::FLOOD_TABLE), 404);

        $availableDimensions = collect(self::REPORT_DIMENSIONS)
            ->filter(fn (string $label, string $column): bool =>
                Schema::hasColumn(self::FLOOD_TABLE, $column)
            )
            ->all();
        $availableMeasures = collect(self::REPORT_MEASURES)
            ->filter(fn (string $label, string $column): bool =>
                $column === 'records' || Schema::hasColumn(self::FLOOD_TABLE, $column)
            )
            ->all();

        $validated = $request->validate([
            'row_primary' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'row_secondary' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'column' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'measure' => ['nullable', Rule::in(array_keys($availableMeasures))],
            'aggregation' => ['nullable', Rule::in(['count', 'avg', 'sum', 'min', 'max'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'flood_code' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $configuration = [
            'row_primary' => $validated['row_primary'] ?? 'barangay',
            'row_secondary' => $request->has('row_secondary')
                ? (string) ($validated['row_secondary'] ?? '')
                : 'year',
            'column' => $request->has('column')
                ? (string) ($validated['column'] ?? '')
                : 'flood_code',
            'measure' => $validated['measure'] ?? 'records',
            'aggregation' => $validated['aggregation'] ?? 'count',
            'date_from' => $validated['date_from'] ?? '',
            'date_to' => $validated['date_to'] ?? '',
            'barangay' => trim((string) ($validated['barangay'] ?? '')),
            'flood_code' => $validated['flood_code'] ?? '',
        ];

        if ($configuration['measure'] === 'records') {
            $configuration['aggregation'] = 'count';
        }

        $rowDimensions = array_values(array_unique(array_filter([
            $configuration['row_primary'],
            $configuration['row_secondary'],
        ])));
        $columnDimension = $configuration['column'];

        if (in_array($columnDimension, $rowDimensions, true)) {
            $columnDimension = '';
            $configuration['column'] = '';
        }

        $query = DB::table(self::FLOOD_TABLE);

        if (Schema::hasColumn(self::FLOOD_TABLE, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($configuration['date_from'] !== '' && Schema::hasColumn(self::FLOOD_TABLE, 'event_date')) {
            $query->whereDate('event_date', '>=', $configuration['date_from']);
        }

        if ($configuration['date_to'] !== '' && Schema::hasColumn(self::FLOOD_TABLE, 'event_date')) {
            $query->whereDate('event_date', '<=', $configuration['date_to']);
        }

        if ($configuration['barangay'] !== '') {
            $query->where('barangay', $configuration['barangay']);
        }

        if ($configuration['flood_code'] !== '') {
            $query->where('flood_code', $configuration['flood_code']);
        }

        $groupDimensions = array_values(array_unique(array_filter([
            ...$rowDimensions,
            $columnDimension,
        ])));
        $grammar = DB::connection()->getQueryGrammar();

        foreach ($groupDimensions as $index => $dimension) {
            $query->addSelect($dimension . ' as dimension_' . $index);
            $query->groupBy($dimension);
            $query->orderBy($dimension);
        }

        $measure = $configuration['measure'];
        $aggregation = strtoupper($configuration['aggregation']);
        $expression = $measure === 'records'
            ? 'COUNT(*)'
            : $aggregation . '(' . $grammar->wrap($measure) . ')';

        $results = $query->selectRaw($expression . ' as metric_value')
            ->limit(2500)
            ->get();

        $pivotRows = [];
        $columnKeys = [];
        $maximumValue = 0.0;

        foreach ($results as $result) {
            $dimensions = [];

            foreach ($groupDimensions as $index => $dimension) {
                $dimensions[$dimension] = data_get($result, 'dimension_' . $index);
            }

            $rowValues = collect($rowDimensions)
                ->mapWithKeys(fn (string $dimension): array => [
                    $dimension => $this->reportLabel($dimension, $dimensions[$dimension] ?? null),
                ])
                ->all();
            $rowKey = json_encode($rowValues, JSON_THROW_ON_ERROR);
            $columnKey = $columnDimension !== ''
                ? $this->reportLabel($columnDimension, $dimensions[$columnDimension] ?? null)
                : 'Value';
            $value = round((float) $result->metric_value, 2);

            $pivotRows[$rowKey] ??= ['dimensions' => $rowValues, 'values' => []];
            $pivotRows[$rowKey]['values'][$columnKey] = $value;
            $columnKeys[$columnKey] = true;
            $maximumValue = max($maximumValue, $value);
        }

        $columnKeys = array_keys($columnKeys);
        $barangays = DB::table(self::FLOOD_TABLE)
            ->whereNotNull('barangay')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        return view('operational-records.report-builder', [
            'availableDimensions' => $availableDimensions,
            'availableMeasures' => $availableMeasures,
            'configuration' => $configuration,
            'rowDimensions' => $rowDimensions,
            'columnDimension' => $columnDimension,
            'pivotRows' => array_values($pivotRows),
            'columnKeys' => $columnKeys,
            'maximumValue' => $maximumValue,
            'barangays' => $barangays,
        ]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $report = $this->reportBuilder($request)->getData();
        $configuration = $report['configuration'];
        $rowDimensions = $report['rowDimensions'];
        $columnKeys = $report['columnKeys'];
        $availableDimensions = $report['availableDimensions'];
        $availableMeasures = $report['availableMeasures'];
        $pivotRows = $report['pivotRows'];
        $columnCount = max(count($rowDimensions) + count($columnKeys), 4);
        $fileName = 'maps-flood-report-' . now()->format('Y-m-d-His') . '.xls';

        return response()->streamDownload(function () use (
            $availableDimensions,
            $availableMeasures,
            $columnCount,
            $columnKeys,
            $configuration,
            $pivotRows,
            $rowDimensions
        ): void {
            $escape = static fn (mixed $value): string => htmlspecialchars(
                (string) ($value ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            echo "\xEF\xBB\xBF";
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            echo '<style>';
            echo 'body{font-family:Arial,sans-serif;color:#172033}table{border-collapse:collapse}td,th{border:1px solid #d1d5db;padding:7px 10px;min-width:120px}';
            echo '.title{background:#14532d;color:#fff;font-size:18px;font-weight:bold;text-align:left}.label{background:#dcfce7;color:#14532d;font-weight:bold}.header{background:#0369a1;color:#fff;font-weight:bold;text-align:center}.number{text-align:right}';
            echo '</style></head><body><table>';
            echo '<tr><th colspan="' . $columnCount . '" class="title">M.A.P.S. Flood Report</th></tr>';
            echo '<tr><td class="label">Generated</td><td>' . $escape(now()->format('Y-m-d H:i:s')) . '</td></tr>';
            echo '<tr><td class="label">Measure</td><td>' . $escape($availableMeasures[$configuration['measure']]) . '</td><td class="label">Calculation</td><td>' . $escape(ucfirst($configuration['aggregation'])) . '</td></tr>';
            echo '<tr><td class="label">Barangay filter</td><td>' . $escape($configuration['barangay'] ?: 'All barangays') . '</td><td class="label">Flood code filter</td><td>' . $escape($configuration['flood_code'] ?: 'All flood codes') . '</td></tr>';
            echo '<tr><td class="label">Date range</td><td>' . $escape(($configuration['date_from'] ?: 'Beginning') . ' to ' . ($configuration['date_to'] ?: 'Latest')) . '</td></tr>';
            echo '<tr><td colspan="' . $columnCount . '"></td></tr>';
            echo '<tr>';
            foreach ($rowDimensions as $dimension) {
                echo '<th class="header">' . $escape($availableDimensions[$dimension]) . '</th>';
            }
            foreach ($columnKeys as $columnKey) {
                echo '<th class="header">' . $escape($columnKey) . '</th>';
            }
            echo '</tr>';

            foreach ($pivotRows as $pivotRow) {
                echo '<tr>';
                foreach ($rowDimensions as $dimension) {
                    echo '<td>' . $escape($pivotRow['dimensions'][$dimension] ?? '') . '</td>';
                }
                foreach ($columnKeys as $columnKey) {
                    $value = $pivotRow['values'][$columnKey] ?? null;
                    $displayValue = $value === null
                        ? ''
                        : number_format((float) $value, $configuration['aggregation'] === 'count' ? 0 : 2, '.', '');
                    echo '<td class="number">' . $escape($displayValue) . '</td>';
                }
                echo '</tr>';
            }

            if ($pivotRows === []) {
                echo '<tr><td colspan="' . $columnCount . '">No flood records match this report configuration.</td></tr>';
            }

            echo '</table></body></html>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function excelXmlRow(array $cells): string
    {
        $xml = '<Row>';

        foreach ($cells as [$value, $style]) {
            $styleAttribute = $style ? ' ss:StyleID="' . $style . '"' : '';
            $type = $value !== null && is_numeric($value) ? 'Number' : 'String';
            $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<Cell' . $styleAttribute . '><Data ss:Type="' . $type . '">' . $escaped . '</Data></Cell>';
        }

        return $xml . '</Row>';
    }

    private function xlsxRow(int $rowNumber, array $cells): string
    {
        return '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
    }

    private function xlsxCell(string $reference, mixed $value, int $style = 0): string
    {
        $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';

        if ($value !== null && is_numeric($value)) {
            return '<c r="' . $reference . '"' . $styleAttribute . '><v>' . (float) $value . '</v></c>';
        }

        $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="' . $reference . '" t="inlineStr"' . $styleAttribute . '><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
    }

    private function excelColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Flood Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts>'
            . '<fonts count="3"><font><sz val="11"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF14532D"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0369A1"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsxCoreProperties(): string
    {
        $timestamp = now()->utc()->format('Y-m-d\\TH:i:s\\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>M.A.P.S. Flood Report</dc:title><dc:creator>M.A.P.S.</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private function xlsxAppProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>M.A.P.S.</Application></Properties>';
    }

    private function reportLabel(string $dimension, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not specified';
        }

        if ($dimension === 'month' && is_numeric($value)) {
            return Carbon::create(null, (int) $value, 1)->format('F');
        }

        if ($dimension === 'wet_season') {
            return (bool) $value ? 'Wet season' : 'Dry season';
        }

        return (string) $value;
    }

    public function createFlood(): View
    {
        abort_unless(Schema::hasTable(self::FLOOD_TABLE), 404);

        return view('operational-records.flood-form', [
            'record' => null,
            'barangays' => Barangay::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeFlood(Request $request, OperationalFloodEnrichmentService $enrichment): RedirectResponse
    {
        $data = $this->validatedFloodRecord($request);
        $date = now('Asia/Manila');
        $record = FloodIncidentRecord::create(array_merge($data, [
            'event_id' => 'MAPS-'.Str::uuid(),
            'observation_datetime' => $date->format('Y-m-d H:i:s'),
            'flood_start_datetime' => $date->format('Y-m-d H:i:s'),
            'event_date' => $date->toDateString(), 'year' => $date->year,
            'month' => $date->month, 'day_of_week' => $date->format('l'),
            'status' => 'Active', 'duration_hours' => null, 'created_by' => $request->user()->id,
        ]));
        $enrichment->enrich($record);
        return $this->floodRedirect('Flood recorded and plotted.', $record);
    }

    public function editFlood(int $id): View
    {
        $record = FloodIncidentRecord::findOrFail($id);
        return view('operational-records.flood-form', [
            'record' => $record,
            'barangays' => Barangay::active()->orderBy('name')->get(),
        ]);
    }

    public function updateFlood(Request $request, int $id, OperationalFloodEnrichmentService $enrichment): RedirectResponse
    {
        $record = FloodIncidentRecord::findOrFail($id);
        $data = $this->validatedFloodRecord($request, $record);
        DB::transaction(function () use ($record, $data): void {
            $locked = FloodIncidentRecord::lockForUpdate()->findOrFail($record->id);
            if ($locked->status !== 'Active') {
                throw ValidationException::withMessages(['status' => 'Subsided floods are closed.']);
            }
            $locked->update($data);
        });
        $enrichment->enrich($record->fresh());
        return $this->floodRedirect('Flood location updated.');
    }

    public function raiseFlood(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['flood_code' => ['required', Rule::in(['A', 'B', 'C', 'D'])]]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = FloodIncidentRecord::lockForUpdate()->findOrFail($id);
            if ($record->status !== 'Active' || strcmp($validated['flood_code'], $record->flood_code) <= 0) {
                throw ValidationException::withMessages(['flood_code' => 'Choose a higher code for an active flood.']);
            }
            $this->logFloodUpdate($record, $request, 'Raised', $validated['flood_code']);
            $record->update(['flood_code' => $validated['flood_code']]);
        });
        return $this->floodRedirect('Flood code raised.');
    }

    public function subsideFlood(Request $request, int $id): RedirectResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $record = FloodIncidentRecord::lockForUpdate()->findOrFail($id);
            if ($record->status !== 'Active') {
                throw ValidationException::withMessages(['status' => 'This flood has already subsided.']);
            }
            $end = now('Asia/Manila');
            $start = Carbon::parse($record->flood_start_datetime, 'Asia/Manila');
            $this->logFloodUpdate($record, $request, 'Subsided', $record->flood_code);
            $record->update(['status' => 'Subsided', 'flood_subsided_datetime' => $end->format('Y-m-d H:i:s'),
                'duration_hours' => round(max(0, $start->diffInSeconds($end, false)) / 3600, 4)]);
        });
        return $this->floodRedirect('Flood marked subsided and removed from active maps.');
    }

    public function enrichFlood(int $id, OperationalFloodEnrichmentService $enrichment): RedirectResponse
    {
        $record = $enrichment->enrich(FloodIncidentRecord::findOrFail($id));
        return $this->floodRedirect($record->enrichment_status === 'Complete'
            ? 'Automatic data filled.' : 'Available data saved. Some attributes are still unavailable.');
    }

    public function destroyFlood(int $id): RedirectResponse
    {
        FloodIncidentRecord::findOrFail($id)->delete();
        return $this->floodRedirect('Flood incident record removed.');
    }

    private function floodRedirect(string $message, ?FloodIncidentRecord $record = null): RedirectResponse
    {
        $user = request()->user();
        if ($user->hasPermission('records.view')) {
            return redirect()->route('operational-records.index', ['dataset' => 'flood-records'])->with('success', $message);
        }
        if ($user->hasPermission('gis.view')) {
            return redirect()->route('gis.index', ['hazard' => 'flood'])->with('success', $message);
        }
        $id = $record?->id ?? request()->route('id');
        if ($id && $user->hasPermission('flood.edit')) {
            return redirect()->route('operational-records.flood.edit', $id)->with('success', $message);
        }
        return redirect()->back()->with('success', $message);
    }

    private function logFloodUpdate(FloodIncidentRecord $record, Request $request, string $action, string $code): void
    {
        DB::table('flood_incident_updates')->insert(['flood_incident_record_id' => $record->id,
            'user_id' => $request->user()->id, 'action' => $action,
            'previous_code' => $record->flood_code, 'flood_code' => $code, 'created_at' => now()]);
    }

    private function validatedFloodRecord(Request $request, ?FloodIncidentRecord $record = null): array
    {
        if (is_string($request->input('geometry_geojson'))) {
            $request->merge(['geometry_geojson' => json_decode($request->input('geometry_geojson'), true)]);
        }
        $validated = $request->validate([
            'event_id' => ['prohibited'], 'observation_datetime' => ['prohibited'],
            'flood_start_datetime' => ['prohibited'], 'flood_subsided_datetime' => ['prohibited'],
            'status' => ['prohibited'],
            'barangay' => ['required', Rule::exists('barangays', 'name')->where('is_active', true)],
            'flood_code' => $record ? ['prohibited'] : ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'geometry_geojson' => ['required', 'array:type,coordinates'],
            'geometry_geojson.type' => ['required', Rule::in(['LineString'])],
            'geometry_geojson.coordinates' => ['required', 'array', 'list', 'min:2', 'max:500'],
            'geometry_geojson.coordinates.*' => ['required', 'array', 'list', 'size:2'],
            'geometry_geojson.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'geometry_geojson.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
        ]);
        $points = array_map(fn (array $point): array => [(float) $point[0], (float) $point[1]], $validated['geometry_geojson']['coordinates']);
        $length = 0;
        for ($i = 1; $i < count($points); $i++) {
            [$lon1, $lat1] = $points[$i - 1]; [$lon2, $lat2] = $points[$i];
            $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
                + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;
            $length += 6371000 * 2 * atan2(sqrt(min(1, $a)), sqrt(max(0, 1 - $a)));
        }
        if ($length <= 0) throw ValidationException::withMessages(['geometry_geojson' => 'Draw a line along the flooded stretch.']);
        $validated['geometry_geojson']['coordinates'] = $points;
        $validated['extent_length_m'] = round($length, 2);
        $validated['latitude'] = (min(array_column($points, 1)) + max(array_column($points, 1))) / 2;
        $validated['longitude'] = (min(array_column($points, 0)) + max(array_column($points, 0))) / 2;
        return $validated;
    }

    private function baseQuery(array $dataset, string $classification = 'Current'): Builder
    {
        $table = $dataset['table'];
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }
        if ($table === 'fire_incidents') {
            if ($classification === 'Current') {
                $query->whereIn('fire_incidents.record_classification', ['Reported', 'Dataset']);
            } else {
                $query->where('fire_incidents.record_classification', $classification);
            }
        }
        if ($dataset['public_only'] ?? false) {
            $query->whereNotNull("{$table}.user_id");
        }
        return $query;
    }

    private function filteredQuery(array $dataset, array $filters): Builder
    {
        $table = $dataset['table'];
        $query = $this->baseQuery($dataset, $filters['record_classification']);

        if (($dataset['joins_barangays'] ?? false) && Schema::hasTable('barangays')) {
            $query->leftJoin('barangays', "{$table}.barangay_id", '=', 'barangays.id');
        }

        $selects = [];

        foreach (array_keys($dataset['columns']) as $column) {
            if ($column === 'remarks_count') {
                $selects[$column] = DB::table('prediction_remarks')->selectRaw('COUNT(*)')
                    ->whereColumn('prediction_execution_id', 'prediction_executions.id');
            } else {
                $selects[] = $column === 'barangay_name'
                    ? ($table === 'fire_incidents' ? DB::raw("COALESCE(barangays.name, fire_incidents.source_barangay, 'Unspecified') as barangay_name") : 'barangays.name as barangay_name')
                    : "{$table}.{$column}";
            }
        }

        $query->select($selects);

        if ($filters['search'] !== '' && $dataset['search_columns'] !== []) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($dataset, $table, $search): void {
                foreach ($dataset['search_columns'] as $index => $column) {
                    $qualified = $column === 'barangay_name'
                        ? 'barangays.name'
                        : "{$table}.{$column}";

                    $index === 0
                        ? $builder->where($qualified, 'like', "%{$search}%")
                        : $builder->orWhere($qualified, 'like', "%{$search}%");
                }
            });
        }

        if ($filters['date_from'] !== '' && $dataset['date_column'] !== null) {
            $date = Carbon::parse($filters['date_from'], 'Asia/Manila')->startOfDay();
            $query->where("{$table}.{$dataset['date_column']}", '>=',
                ($dataset['local_dates'] ?? false) ? $date->format('Y-m-d H:i:s') : $date->utc()->format('Y-m-d H:i:s'));
        }

        if ($filters['date_to'] !== '' && $dataset['date_column'] !== null) {
            $date = Carbon::parse($filters['date_to'], 'Asia/Manila')->addDay()->startOfDay();
            $query->where("{$table}.{$dataset['date_column']}", '<',
                ($dataset['local_dates'] ?? false) ? $date->format('Y-m-d H:i:s') : $date->utc()->format('Y-m-d H:i:s'));
        }

        if ($filters['status'] !== '' && $dataset['status_column'] !== null) {
            $query->where("{$table}.{$dataset['status_column']}", $filters['status']);
        }

        if ($table === self::FLOOD_TABLE && $filters['flood_code'] !== '') {
            $query->where('flood_code', $filters['flood_code']);
        }
        if ($table === 'prediction_executions' && $filters['forecast_hours'] !== '') {
            $query->where('forecast_hours', $filters['forecast_hours']);
        }

        if ($filters['barangay_id'] !== null) {
            if (($dataset['joins_barangays'] ?? false)) {
                $query->where("{$table}.barangay_id", $filters['barangay_id']);
            } elseif (($dataset['barangay_text_column'] ?? null) !== null) {
                $barangayName = Barangay::query()->whereKey($filters['barangay_id'])->value('name');

                if ($barangayName !== null) {
                    $column = $dataset['barangay_text_column'];
                    $names = DB::table($table)->distinct()->pluck($column)
                        ->filter(fn ($name): bool => $this->barangayKey((string) $name) === $this->barangayKey($barangayName))->all();
                    $query->whereIn("{$table}.{$column}", $names);
                }
            }
        }

        return $query->orderByDesc("{$table}.{$dataset['order_column']}")->orderByDesc("{$table}.id");
    }

    private function barangayKey(string $name): string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
        return match ($key) {
            'hagdangbatoitaas' => 'hagdanbatoitaas',
            'hagdangbatolibis' => 'hagdanbatolibis',
            'wackwackgreenhillseast' => 'wackwackgreenhills',
            default => $key,
        };
    }

    private function validatedFilters(Request $request, array $dataset): array
    {
        $validated = $request->validate([
            'record_classification' => ['nullable', Rule::in(['Current', 'Superseded'])],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in($dataset['statuses'])],
            'flood_code' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'forecast_hours' => ['nullable', Rule::in([24, 48, 72])],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ]);

        return [
            'record_classification' => $validated['record_classification'] ?? 'Current',
            'search' => trim((string) ($validated['search'] ?? '')),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'flood_code' => (string) ($validated['flood_code'] ?? ''),
            'forecast_hours' => (string) ($validated['forecast_hours'] ?? ''),
            'barangay_id' => isset($validated['barangay_id'])
                ? (int) $validated['barangay_id']
                : null,
        ];
    }

    private function resolveDataset(array $datasets, string $datasetKey): array
    {
        abort_unless(array_key_exists($datasetKey, $datasets), 404, 'Unknown operational dataset.');
        $dataset = $datasets[$datasetKey];
        abort_unless(Schema::hasTable($dataset['table']), 404, 'This operational dataset is not available.');

        $table = $dataset['table'];
        $canJoinBarangays = ($dataset['joins_barangays'] ?? false)
            && Schema::hasTable('barangays')
            && Schema::hasColumn($table, 'barangay_id');

        $dataset['joins_barangays'] = $canJoinBarangays;
        $dataset['columns'] = collect($dataset['columns'])
            ->filter(fn (string $heading, string $column): bool =>
                $column === 'barangay_name'
                    ? $canJoinBarangays
                    : ($column === 'remarks_count' || Schema::hasColumn($table, $column))
            )
            ->all();
        foreach (['requested_at', 'completed_at', 'reported_at', 'occurred_at', 'fire_out_at', 'sent_at', 'observed_at', 'created_at'] as $column) {
            if (isset($dataset['columns'][$column])) {
                $dataset['columns'][$column] .= ' (PHT)';
            }
        }
        $dataset['search_columns'] = collect($dataset['search_columns'])
            ->filter(fn (string $column): bool =>
                $column === 'barangay_name'
                    ? $canJoinBarangays
                    : ($column === 'remarks_count' || Schema::hasColumn($table, $column))
            )
            ->values()
            ->all();
        $dataset['date_column'] = $dataset['date_column'] !== null
            && Schema::hasColumn($table, $dataset['date_column'])
                ? $dataset['date_column']
                : null;
        $dataset['status_column'] = $dataset['status_column'] !== null
            && Schema::hasColumn($table, $dataset['status_column'])
                ? $dataset['status_column']
                : null;
        $dataset['order_column'] = Schema::hasColumn($table, $dataset['order_column'])
            ? $dataset['order_column']
            : 'id';

        return $dataset;
    }

    private function formatRecordDates(object $record, array $dataset): object
    {
        if (! ($dataset['local_dates'] ?? false)) {
            foreach (['requested_at', 'completed_at', 'reported_at', 'occurred_at', 'fire_out_at', 'sent_at', 'observed_at', 'created_at'] as $column) {
                if (! empty($record->{$column})) {
                    $record->{$column} = Carbon::parse($record->{$column}, 'UTC')->setTimezone('Asia/Manila')->format('Y-m-d H:i:s');
                }
            }
        }
        return $record;
    }

    private function csvValue(mixed $value): string|int|float|null
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }

    private function datasets(): array
    {
        return [
            'flood-records' => [
                'label' => 'Flood Incident Records', 'table' => self::FLOOD_TABLE,
                'date_column' => 'observation_datetime', 'status_column' => 'status', 'local_dates' => true,
                'barangay_text_column' => 'barangay', 'order_column' => 'flood_start_datetime',
                'search_columns' => ['event_id', 'barangay', 'nearest_waterway', 'flood_code', 'status'],
                'statuses' => ['Active', 'Subsided'], 'crud_route' => null,
                'columns' => ['id' => 'Record ID', 'event_id' => 'Event ID', 'observation_datetime' => 'Observation (PHT)', 'flood_start_datetime' => 'Flood Start (PHT)', 'flood_subsided_datetime' => 'Flood Subsided (PHT)', 'duration_hours' => 'Duration (hours)', 'status' => 'Status', 'barangay' => 'Barangay', 'flood_code' => 'Flood Code', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'nearest_waterway' => 'Nearest Waterway', 'elevation_m' => 'Elevation (m)', 'distance_to_waterway_m' => 'Distance to Waterway (m)', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'rainfall_3d_mm' => 'Rainfall 3d (mm)', 'rainfall_7d_mm' => 'Rainfall 7d (mm)', 'temperature_c' => 'Temperature (°C)', 'temp_max_c' => 'Maximum Temp (°C)', 'temp_min_c' => 'Minimum Temp (°C)', 'wind_speed_kph' => 'Wind Speed (km/h)', 'wind_direction_deg' => 'Wind Direction (°)', 'storm_signal' => 'Storm Signal', 'year' => 'Year'],
            ],
            'fire-incidents' => [
                'label' => 'Fire Incidents', 'table' => 'fire_incidents',
                'date_column' => 'occurred_at', 'status_column' => 'status',
                'joins_barangays' => true, 'order_column' => 'occurred_at',
                'search_columns' => ['incident_number', 'incident_type', 'location', 'barangay_name', 'source_barangay', 'alarm_level', 'cause'],
                'statuses' => ['Reported', 'Responding', 'Controlled', 'Resolved'], 'crud_route' => 'fire-incidents.index', 'crud_permission' => 'fire.view',
                'columns' => ['id' => 'ID', 'incident_number' => 'Incident Number', 'record_classification' => 'Record Classification', 'source_origin' => 'Source Origin', 'occurred_at' => 'Time Occurred', 'fire_out_at' => 'Fire Out', 'duration_minutes' => 'Duration (minutes)', 'barangay_name' => 'Barangay', 'location' => 'Street / Location', 'individuals_affected' => 'Individuals Affected', 'houses_destroyed' => 'Houses Destroyed', 'alarm_level' => 'Alarm (reported)', 'cause' => 'Cause (confirmed)', 'alarm_reference' => 'Alarm (unconfirmed reference)', 'cause_reference' => 'Cause (unconfirmed reference)', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'coordinate_accuracy' => 'Coordinate Accuracy', 'severity' => 'Severity', 'status' => 'Status', 'data_source' => 'Data Source'],
            ],
            'fire-hydrants' => [
                'label' => 'Fire Hydrants', 'table' => 'fire_hydrants',
                'date_column' => 'created_at', 'status_column' => 'status',
                'joins_barangays' => true, 'order_column' => 'id',
                'search_columns' => ['hydrant_code', 'location', 'barangay_name'],
                'statuses' => ['Active', 'Inactive', 'Maintenance'], 'crud_route' => 'fire-hydrants.index', 'crud_permission' => 'hydrant.view',
                'columns' => ['id' => 'ID', 'hydrant_code' => 'Hydrant Code', 'barangay_name' => 'Barangay', 'location' => 'Location', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'status' => 'Status', 'last_inspection_date' => 'Last Inspection'],
            ],
            'prediction-results' => [
                'label' => 'Prediction Results', 'table' => 'prediction_executions',
                'date_column' => 'requested_at', 'status_column' => 'status',
                'order_column' => 'requested_at', 'search_columns' => ['requested_by_name', 'kind', 'status', 'error_message'],
                'statuses' => ['Running', 'Completed', 'Failed'], 'crud_route' => 'prediction.history.index', 'crud_permission' => 'prediction.view',
                'columns' => ['id' => 'Run ID', 'requested_at' => 'Run At', 'completed_at' => 'Completed At', 'requested_by_name' => 'Run By', 'kind' => 'Type', 'forecast_hours' => 'Forecast (hours)', 'status' => 'Status', 'result_snapshot' => 'Saved Results', 'remarks_count' => 'Remarks', 'error_message' => 'Error'],
            ],
            'sms-logs' => [
                'label' => 'SMS Delivery Logs', 'table' => 'sms_logs',
                'date_column' => 'created_at', 'status_column' => 'status',
                'order_column' => 'id', 'search_columns' => ['recipient_name', 'phone_number', 'source', 'failure_reason', 'message'],
                'statuses' => ['pending', 'sent', 'failed'], 'crud_route' => 'sms.index', 'crud_permission' => 'sms.view',
                'columns' => ['id' => 'ID', 'created_at' => 'Logged At', 'sent_at' => 'Sent At', 'recipient_name' => 'Recipient', 'phone_number' => 'Phone Number', 'source' => 'Source', 'message' => 'Message', 'status' => 'Status', 'http_status' => 'HTTP Status', 'failure_reason' => 'Failure Reason'],
            ],
            'public-recipients' => [
                'label' => 'Total Public Recipients', 'table' => 'sms_recipients', 'public_only' => true,
                'date_column' => 'created_at', 'status_column' => 'is_active',
                'joins_barangays' => true, 'order_column' => 'id', 'search_columns' => ['full_name', 'phone_number', 'office_or_barangay', 'barangay_name'],
                'statuses' => ['1', '0'], 'status_labels' => ['1' => 'Active', '0' => 'Inactive'], 'crud_route' => 'sms.index', 'crud_permission' => 'sms.view',
                'columns' => ['id' => 'ID', 'created_at' => 'Registered At', 'full_name' => 'Name', 'phone_number' => 'Phone Number', 'barangay_name' => 'Barangay', 'office_or_barangay' => 'Office/Barangay', 'receive_flood_alerts' => 'Flood Alerts', 'receive_fire_alerts' => 'Fire Alerts', 'is_active' => 'Active'],
            ],
            'weather-observations' => [
                'label' => 'Weather Observations', 'table' => 'weather_observations',
                'date_column' => 'observed_at', 'status_column' => null,
                'joins_barangays' => true, 'order_column' => 'observed_at',
                'search_columns' => ['station_name', 'source', 'weather_condition', 'barangay_name'],
                'statuses' => [], 'crud_route' => null,
                'columns' => ['id' => 'ID', 'observed_at' => 'Observed At', 'barangay_name' => 'Barangay', 'station_name' => 'Station / Location', 'source' => 'Source', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'rainfall_3d_mm' => 'Rainfall 3d (mm)', 'rainfall_7d_mm' => 'Rainfall 7d (mm)', 'temperature_c' => 'Temperature (°C)', 'relative_humidity_pct' => 'Humidity (%)', 'wind_speed_kph' => 'Wind Speed (km/h)', 'wind_direction_deg' => 'Wind Direction (°)', 'weather_condition' => 'Condition'],
            ],
        ];
    }
}
