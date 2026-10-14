{{-- KPI report. Averages are never shown without their sample size (FR-REP-003). --}}
<x-filament-panels::page>
    <form method="GET" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">From</span>
                <input type="date" name="from" value="{{ $activeFilters['from'] ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">To</span>
                <input type="date" name="to" value="{{ $activeFilters['to'] ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">Province</span>
                <select name="province" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($provinces as $value => $label)
                        <option value="{{ $value }}" @selected(($activeFilters['province'] ?? '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">Supplier</span>
                <select name="supplier_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}" @selected(($activeFilters['supplier_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">Site</span>
                <select name="site_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($sites as $id => $code)
                        <option value="{{ $id }}" @selected(($activeFilters['site_id'] ?? '') === (string) $id)>{{ $code }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700">Attribution</span>
                <select name="attribution" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($attributions as $value => $label)
                        <option value="{{ $value }}" @selected(($activeFilters['attribution'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button>
            <a href="{{ url()->current() }}" class="text-sm text-gray-600 underline">Reset</a>
        </div>
    </form>

    {{-- Headline figures. --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Total responses' => $totals['responses'] ?? 0,
            'Average rating' => $totals['average_rating'] ?? null,
            'Positive responses' => $totals['positive_rate'] !== null ? $totals['positive_rate'].'%' : null,
            'Connection issue rate' => $totals['issue_rate'] !== null ? $totals['issue_rate'].'%' : null,
        ] as $label => $value)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $value ?? '—' }}</p>
                @if ($label === 'Average rating' || $label === 'Positive responses')
                    <p class="mt-1 text-xs text-gray-500">
                        @if (($totals['rated'] ?? 0) === 0)
                            No rated responses yet
                        @else
                            Based on {{ $totals['rated'] }} rated response{{ ($totals['rated'] ?? 0) === 1 ? '' : 's' }}
                        @endif
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Verified portal responses</p>
            <p class="mt-1 text-2xl font-bold">{{ $totals['verified'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Unverified (link / QR)</p>
            <p class="mt-1 text-2xl font-bold">{{ $totals['unverified'] ?? 0 }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">By site</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="py-2">Site</th>
                        <th class="py-2">Responses</th>
                        <th class="py-2">Avg rating (n)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bySite as $row)
                        <tr>
                            <td class="py-2 font-medium">{{ $row['label'] }}</td>
                            <td class="py-2">{{ $row['responses'] }}</td>
                            <td class="py-2">
                                {{ $row['average_rating'] ?? '—' }}
                                <span class="text-xs text-gray-500">({{ $row['rated'] }})</span>
                                @unless ($row['significant'])
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">
                                        small sample (&lt; {{ $minimumSampleSize }})
                                    </span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-gray-500">No responses for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">By supplier</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="py-2">Supplier</th>
                        <th class="py-2">Responses</th>
                        <th class="py-2">Avg rating (n)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bySupplier as $row)
                        <tr>
                            <td class="py-2 font-medium">{{ $row['label'] }}</td>
                            <td class="py-2">{{ $row['responses'] }}</td>
                            <td class="py-2">
                                {{ $row['average_rating'] ?? '—' }}
                                <span class="text-xs text-gray-500">({{ $row['rated'] }})</span>
                                @unless ($row['significant'])
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">small sample</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-gray-500">No responses for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Issues reported</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($byIssue as $row)
                    <li class="flex justify-between">
                        <span class="capitalize">{{ str_replace('_', ' ', $row['issue_category']) }}</span>
                        <span class="font-medium">{{ $row['total'] }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">No responses for these filters.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Daily responses</h2>
            <ul class="mt-3 max-h-72 space-y-1 overflow-auto text-sm">
                @forelse (array_reverse($daily) as $row)
                    <li class="flex justify-between">
                        <span class="text-gray-600">{{ $row['day'] }}</span>
                        <span>
                            {{ $row['responses'] }}
                            <span class="text-xs text-gray-500">(avg {{ $row['average_rating'] ?? '—' }})</span>
                        </span>
                    </li>
                @empty
                    <li class="text-gray-500">No responses for these filters.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <p class="mt-6 text-xs text-gray-500">
        Positive = rating of {{ $positiveThreshold }} or more. Averages are shown with the number of rated
        responses they came from; figures below {{ $minimumSampleSize }} rated responses are flagged, because
        a small sample is not evidence of a trend.
    </p>
</x-filament-panels::page>