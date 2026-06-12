<?php

namespace App\Http\Controllers\Admin;

use App\Constants\DonationOptions;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Support\AdminDonationFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        abort_unless($request->user()->hasPermission('donations.view'), 403);

        $filters = $this->validatedFilters($request);
        $baseQuery = $this->filteredQuery($filters);
        $paidQuery = (clone $baseQuery)->where('status', DonationOptions::STATUS_PAID);
        $summary = (clone $baseQuery)
            ->selectRaw('count(*) as total_donations')
            ->selectRaw('coalesce(sum(case when status = ? then coalesce(gateway_net_amount, amount) else 0 end), 0) as total_paid_amount', [DonationOptions::STATUS_PAID])
            ->selectRaw('avg(case when status = ? then coalesce(gateway_net_amount, amount) else null end) as average_paid_amount', [DonationOptions::STATUS_PAID])
            ->selectRaw('sum(case when giving_method = ? and status = ? then 1 else 0 end) as pending_bank_transfers', [
                DonationOptions::METHOD_BANK_TRANSFER,
                DonationOptions::STATUS_UNDER_REVIEW,
            ])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_donations', [DonationOptions::STATUS_REJECTED])
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as failed_or_cancelled_donations', [
                DonationOptions::STATUS_FAILED,
                DonationOptions::STATUS_CANCELLED,
            ])
            ->first();

        return response()->json([
            'data' => [
                'filters' => $filters,
                'total_donations' => (int) $summary->total_donations,
                'total_paid_amount' => (float) $summary->total_paid_amount,
                'average_paid_amount' => round((float) $summary->average_paid_amount, 2),
                'pending_bank_transfers' => (int) $summary->pending_bank_transfers,
                'rejected_donations' => (int) $summary->rejected_donations,
                'failed_or_cancelled_donations' => (int) $summary->failed_or_cancelled_donations,
                'totals_by_category' => $this->totalsBy($paidQuery, 'category'),
                'totals_by_giving_method' => $this->totalsBy($paidQuery, 'giving_method'),
                'totals_by_status' => $this->totalsBy($baseQuery, 'status'),
                'monthly_totals' => $this->dateTotals($paidQuery, 'month'),
                'daily_totals' => $this->dateTotals($paidQuery, 'day'),
                'anonymous_totals' => $this->anonymousTotals($paidQuery),
                'recent_largest_donations' => (clone $paidQuery)
                    ->orderByRaw('coalesce(gateway_net_amount, amount) desc')
                    ->orderByDesc('paid_at')
                    ->limit(10)
                    ->get(['uuid', 'donor_name', 'donor_email', 'is_anonymous', 'amount', 'gateway_fee_amount', 'gateway_tax_amount', 'gateway_net_amount', 'category', 'giving_method', 'status', 'gateway_reference', 'paid_at', 'created_at'])
                    ->map(fn (Donation $donation) => $this->donationRow($donation))
                    ->values(),
            ],
        ]);
    }

    public function export(Request $request, string $format): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('donations.view'), 403);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $filters = $this->validatedFilters($request);
        $donations = $this->filteredQuery($filters)
            ->with(['verifier:id,name,email', 'rejecter:id,name,email'])
            ->orderByDesc('id')
            ->get();
        $rows = $this->exportRows($donations);
        $timestamp = now()->format('Ymd-His');

        if ($format === 'xlsx') {
            $content = $this->buildXlsx($rows);

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, "sabc-donation-report-{$timestamp}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, "sabc-donation-report-{$timestamp}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'in:'.implode(',', DonationOptions::STATUSES)],
            'category' => ['nullable', 'in:'.implode(',', DonationOptions::CATEGORIES)],
            'giving_method' => ['nullable', 'in:'.implode(',', DonationOptions::METHODS)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'timezone' => ['nullable', 'timezone'],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        return AdminDonationFilters::apply(Donation::query(), $filters);
    }

    private function totalsBy(Builder $query, string $column): Collection
    {
        return (clone $query)
            ->select($column, DB::raw('sum(coalesce(gateway_net_amount, amount)) as total'), DB::raw('count(*) as count'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                $column => $row->{$column},
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->values();
    }

    private function dateTotals(Builder $query, string $granularity): Collection
    {
        $driver = DB::connection()->getDriverName();
        $column = $granularity === 'month' ? 'month' : 'day';
        $expression = match ([$driver, $granularity]) {
            ['pgsql', 'month'] => "to_char(paid_at, 'YYYY-MM')",
            ['pgsql', 'day'] => "to_char(paid_at, 'YYYY-MM-DD')",
            ['mysql', 'month'], ['mariadb', 'month'] => "date_format(paid_at, '%Y-%m')",
            ['mysql', 'day'], ['mariadb', 'day'] => "date_format(paid_at, '%Y-%m-%d')",
            default => $granularity === 'month' ? "strftime('%Y-%m', paid_at)" : "strftime('%Y-%m-%d', paid_at)",
        };

        return (clone $query)
            ->whereNotNull('paid_at')
            ->select(DB::raw($expression.' as '.$column), DB::raw('sum(coalesce(gateway_net_amount, amount)) as total'), DB::raw('count(*) as count'))
            ->groupByRaw($expression)
            ->orderBy($column)
            ->get()
            ->map(fn ($row) => [
                $column => $row->{$column},
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->values();
    }

    private function anonymousTotals(Builder $query): Collection
    {
        return (clone $query)
            ->select('is_anonymous', DB::raw('sum(coalesce(gateway_net_amount, amount)) as total'), DB::raw('count(*) as count'))
            ->groupBy('is_anonymous')
            ->orderBy('is_anonymous')
            ->get()
            ->map(fn ($row) => [
                'is_anonymous' => (bool) $row->is_anonymous,
                'label' => $row->is_anonymous ? 'Anonymous' : 'Identified',
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->values();
    }

    private function donationRow(Donation $donation): array
    {
        return [
            'uuid' => $donation->uuid,
            'donor_display_name' => $donation->donorDisplayName(),
            'donor_email' => $donation->is_anonymous ? 'Contact retained privately' : $donation->donor_email,
            'amount' => (float) $donation->netAmount(),
            'gross_amount' => (float) $donation->amount,
            'gateway_fee_amount' => $donation->gateway_fee_amount !== null ? (float) $donation->gateway_fee_amount : null,
            'gateway_tax_amount' => $donation->gateway_tax_amount !== null ? (float) $donation->gateway_tax_amount : null,
            'gateway_net_amount' => $donation->gateway_net_amount !== null ? (float) $donation->gateway_net_amount : null,
            'category' => $donation->category,
            'giving_method' => $donation->giving_method,
            'status' => $donation->status,
            'gateway_reference' => $donation->gateway_reference,
            'paid_at' => $donation->paid_at?->toISOString(),
            'created_at' => $donation->created_at?->toISOString(),
        ];
    }

    private function exportRows(Collection $donations): array
    {
        $rows = [[
            'Donation UUID',
            'Donor Display Name',
            'Donor Email',
            'Net Amount',
            'Gross Amount',
            'Gateway Fee',
            'Gateway Tax',
            'Category',
            'Giving Method',
            'Status',
            'Gateway Reference',
            'Paid At',
            'Created At',
            'Verified By',
            'Rejected By',
            'Rejection Reason',
        ]];

        foreach ($donations as $donation) {
            $rows[] = [
                $donation->uuid,
                $donation->donorDisplayName(),
                $donation->is_anonymous ? 'Contact retained privately' : $donation->donor_email,
                (string) $donation->netAmount(),
                (string) $donation->amount,
                (string) $donation->gateway_fee_amount,
                (string) $donation->gateway_tax_amount,
                $donation->category,
                $donation->giving_method,
                $donation->status,
                $donation->gateway_reference,
                $donation->paid_at?->toDateTimeString(),
                $donation->created_at?->toDateTimeString(),
                $donation->verifier?->name,
                $donation->rejecter?->name,
                $donation->rejected_reason,
            ];
        }

        return $rows;
    }

    private function buildXlsx(array $rows): string
    {
        return $this->buildZip([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml($rows),
        ]);
    }

    private function buildZip(array $files): string
    {
        $localFiles = '';
        $centralDirectory = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($content);
            $size = strlen($content);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0);
            $localFiles .= $localHeader.$name.$content;
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                32,
                $offset
            ).$name;
            $offset += strlen($localHeader) + strlen($name) + $size;
        }

        return $localFiles
            .$centralDirectory
            .pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($centralDirectory), strlen($localFiles), 0);
    }

    private function sheetXml(array $rows): string
    {
        $sheetRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->cellReference($columnIndex + 1, $rowIndex + 1);
                $text = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells[] = '<c r="'.$cell.'" t="inlineStr"'.$style.'><is><t>'.$text.'</t></is></c>';
            }
            $sheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'</worksheet>';
    }

    private function cellReference(int $column, int $row): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)).$letters;
            $column = intdiv($column, 26);
        }

        return $letters.$row;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Donations" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }
}
