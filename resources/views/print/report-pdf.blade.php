<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* dompdf renders a limited CSS subset: tables and absolute
           positioning only, no flexbox and no custom properties. */
        @page { margin: 96px 34px 66px 34px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            color: #1e293b;
            margin: 0;
        }

        header {
            position: fixed;
            top: -74px; left: 0; right: 0;
            height: 64px;
        }

        footer {
            position: fixed;
            bottom: -44px; left: 0; right: 0;
            height: 34px;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }

        .brand { font-size: 14pt; font-weight: bold; color: #0f172a; }
        .brand-sub { font-size: 7.5pt; color: #64748b; }

        .doc-title { font-size: 13pt; font-weight: bold; color: #0f172a; }
        .doc-meta { font-size: 8pt; color: #64748b; }

        .accent { height: 3px; background: #4f46e5; margin-top: 7px; }

        table { width: 100%; border-collapse: collapse; }

        .summary { margin-bottom: 14px; }
        .summary td {
            border: 1px solid #e2e8f0;
            padding: 7px 9px;
            width: 20%;
        }
        .summary .label {
            font-size: 6.8pt; color: #64748b;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .summary .value { font-size: 10.5pt; font-weight: bold; color: #0f172a; padding-top: 2px; }

        .data thead th {
            background: #f1f5f9;
            border-bottom: 1.5px solid #cbd5e1;
            padding: 6px 7px;
            font-size: 7.4pt;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #475569;
            text-align: left;
        }
        .data tbody td {
            padding: 5.5px 7px;
            border-bottom: 1px solid #eef2f7;
            font-size: 8.2pt;
        }
        .data tbody tr:nth-child(even) td { background: #fafbfd; }
        .num { text-align: right; }

        .empty { text-align: center; padding: 34px; color: #94a3b8; font-size: 9pt; }

        /* dompdf resolves these counters per rendered page. */
        .pagenum:before { content: counter(page); }
        .pagetotal:before { content: counter(pages); }
    </style>
</head>
<body>

<header>
    <table>
        <tr>
            <td style="vertical-align:top">
                <div class="brand">{{ $tenant?->name ?? config('app.name') }}</div>
                <div class="brand-sub">
                    {{ $tenant?->address }}{{ $tenant?->city ? ', '.$tenant->city : '' }}
                    @if ($tenant?->phone) · {{ $tenant->phone }} @endif
                </div>
            </td>
            <td style="vertical-align:top;text-align:right">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">
                    {{-- Says plainly which branch the figures cover, so a
                         printed report can never be misread as chain-wide. --}}
                    <b>{{ outlet_label() }}</b><br>
                    Periode {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}
                    – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
                </div>
            </td>
        </tr>
    </table>
    <div class="accent"></div>
</header>

<footer>
    <table>
        <tr>
            <td>
                Dicetak {{ $generatedAt->translatedFormat('d F Y H:i') }} oleh {{ $generatedBy }}
                · {{ $tenant?->name }} · {{ outlet_label() }}
            </td>
            <td style="text-align:right">
                Halaman <span class="pagenum"></span> / <span class="pagetotal"></span>
            </td>
        </tr>
    </table>
</footer>

<main>
    <table class="summary">
        <tr>
            <td>
                <div class="label">Omzet</div>
                <div class="value">{{ money($summary['revenue']) }}</div>
            </td>
            <td>
                <div class="label">Transaksi</div>
                <div class="value">{{ number_format($summary['transactions'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Rata-rata Belanja</div>
                <div class="value">{{ money($summary['average_basket']) }}</div>
            </td>
            <td>
                <div class="label">Laba Kotor</div>
                <div class="value">{{ money($summary['profit']) }}</div>
            </td>
            <td>
                <div class="label">Margin</div>
                <div class="value">{{ percent_label($summary['margin_percent']) }}</div>
            </td>
        </tr>
    </table>

    @if (count($rows))
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $index => $column)
                        <th class="{{ $index > 0 ? 'num' : '' }}">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            <td class="{{ $index > 0 ? 'num' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Tidak ada data pada periode yang dipilih.</div>
    @endif
</main>

</body>
</html>
