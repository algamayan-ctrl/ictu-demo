@php
    // Highlights lang; ang detailed graphs ay nasa submenus.
    $unitCount = (int) ($rankedUnits->first()['count'] ?? 0);
    $unitNames = $rankedUnits
        ->where('count', $unitCount)
        ->pluck('name')
        ->unique()
        ->implode(' / ');

    $divisionRows = collect($groups['groups'])
        ->sortByDesc('count')
        ->values();

    $divisionCount = (int) ($divisionRows->first()['count'] ?? 0);
    $divisionNames = $divisionRows
        ->where('count', $divisionCount)
        ->pluck('label')
        ->implode(' / ');

    $itemRows = collect($equipment['items']);
    $itemCount = (float) ($itemRows->first()['released'] ?? 0);
    $itemNames = $itemRows
        ->where('released', $itemCount)
        ->pluck('name')
        ->unique()
        ->implode(' / ');

    $periodRows = collect($overviewTrend['points'] ?? []);
    $peakCount = (int) ($periodRows->max('count') ?? 0);
    $peakNames = $periodRows
        ->where('count', $peakCount)
        ->pluck('label')
        ->implode(' / ');

    $demandLink = route(
        'analytics.index',
        $carry + ['section' => 'demand']
    );

    $leaders = [
        [
            'title' => 'Top Borrowing Unit',
            'name' => $unitCount > 0 ? $unitNames : 'No requests yet',
            'value' => $unitCount > 0
                ? number_format($unitCount).' requests'
                : '—',
            'note' => 'Highest filed request count',
            'icon' => 'requests',
        ],
        [
            'title' => 'Top Borrowing Division',
            'name' => $divisionCount > 0
                ? $divisionNames
                : 'No requests yet',
            'value' => $divisionCount > 0
                ? number_format($divisionCount).' requests'
                : '—',
            'note' => 'Highest filed request count',
            'icon' => 'analytics',
        ],
        [
            'title' => 'Most Borrowed Item',
            'name' => $itemCount > 0 ? $itemNames : 'No releases yet',
            'value' => $itemCount > 0
                ? number_format($itemCount).' units released'
                : '—',
            'note' => 'Based on actual quantity released',
            'icon' => 'inventory',
        ],
        [
            'title' => 'Busiest Period',
            'name' => $peakCount > 0 ? $peakNames : 'No activity yet',
            'value' => $peakCount > 0
                ? number_format($peakCount).' requests'
                : '—',
            'note' => 'Highest count in the selected period',
            'icon' => 'calendar-clock',
        ],
    ];
@endphp

<style>
.analytics-page .overview-leader-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    padding: 14px;
}

.analytics-page .overview-leader-card {
    display: flex;
    flex-direction: column;
    gap: 9px;
    min-width: 0;
    padding: 15px;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: var(--surface-elevated, #fff);
    color: var(--text-primary);
    text-decoration: none;
    transition: border-color .15s, box-shadow .15s;
}

.analytics-page .overview-leader-card:hover {
    border-color: #1677c8;
    box-shadow: 0 3px 10px rgba(22, 119, 200, .08);
}

.analytics-page .overview-leader-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 600;
}

.analytics-page .overview-leader-heading .analytics-card-mark {
    flex: 0 0 30px;
}

.analytics-page .overview-leader-name {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.analytics-page .overview-leader-value {
    margin-top: auto;
    padding-top: 7px;
    font-size: 20px;
    font-weight: 800;
    color: #1677c8;
}

.analytics-page .overview-leader-note {
    font-size: 10px;
    line-height: 1.4;
    color: var(--text-muted);
}

.analytics-page .overview-leader-scope {
    padding: 0 14px 12px;
    margin: 0;
    font-size: 10px;
    line-height: 1.5;
    color: var(--text-muted);
}

@media (max-width: 1100px) {
    .analytics-page .overview-leader-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 580px) {
    .analytics-page .overview-leader-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="analytics-card">
    <header class="analytics-card-head">
        <span class="analytics-card-mark" aria-hidden="true">
            <x-icon name="analytics" size="15" />
        </span>

        <div>
            <h2>Top Highlights</h2>
            <p>Leading borrowing activity for the selected period.</p>
        </div>
    </header>

    <div class="overview-leader-grid">
        @foreach($leaders as $leaderIndex => $leader)
            <a class="overview-leader-card" href="{{ $demandLink }}#{{ ['top-borrowing-units', 'demand-by-division', 'equipment-demand-usage', 'borrowing-demand-trend'][$leaderIndex] }}">
                <span class="overview-leader-heading">
                    <span class="analytics-card-mark" aria-hidden="true">
                        <x-icon :name="$leader['icon']" size="15" />
                    </span>
                    {{ $leader['title'] }}
                </span>

                <strong class="overview-leader-name">
                    {{ $leader['name'] }}
                </strong>

                <span class="overview-leader-value">
                    {{ $leader['value'] }}
                </span>

                <span class="overview-leader-note">
                    {{ $leader['note'] }}
                </span>
            </a>
        @endforeach
    </div>

    <p class="overview-leader-scope">
        Unit and division leaders use the selected period and organisation
        filters across borrowers. Item and busiest-period highlights also
        follow the borrower filter. Ties shown within the existing ranking results.
    </p>
</section>