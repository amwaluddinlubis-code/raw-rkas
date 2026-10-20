            @php
                $ledgerWidths = match ($presentation) {
                    'bku_ledger', 'cash_ledger' => ['8%', '10%', '13%', '7%', '30%', '10%', '10%', '12%'],
                    'bank_ledger' => ['4%', '8%', '8%', '22%', '16%', '12%', '10%', '10%', '10%'],
                    'tax' => ['4%', '8%', '8%', '22%', '6%', '8%', '8%', '8%', '8%', '8%', '6%', '6%'],
                    'transaction_recap' => ['4%', '8%', '8%', '16%', '14%', '22%', '10%', '8%', '10%'],
                    'account_summary' => ['5%', '14%', '31%', '10%', '14%', '12%', '14%'],
                    default => array_fill(
                        0,
                        count($columns),
                        number_format(100 / max(1, count($columns)), 2, '.', '').'%'
                    ),
                };
            @endphp
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $ledgerWidths])
