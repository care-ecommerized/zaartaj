<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Reports\SalesReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin Customers console.
 *
 * ── Customer modeling choice: an email-keyed unified list ──────────────────────
 * The reference "Customers" are everyone who has ever ordered, guests included —
 * not just registered accounts. So a customer row here is a distinct
 * `orders.customer_email`, aggregated across every order placed with that email,
 * LEFT JOINed to `users` on the email. This surfaces both registered buyers and
 * pure guests (orders with a null user_id) in one list, each carrying an
 * `is_registered` flag. Registered accounts that have never ordered are out of
 * scope by design — the list is order-derived.
 *
 * Per email we aggregate in a single grouped query:
 *   - orders_count   — COUNT of their orders
 *   - delivered_count — orders in the Delivered state
 *   - total_spent    — SUM(total) over *realized* orders only (paid OR delivered,
 *                      never cancelled/failed) — the pinned predicate reused from
 *                      SalesReport::realizedExpression()
 *   - last_ordered   — MAX(placed_at)
 *   - name / phone   — from the user record if registered, else the latest order
 *   - countries / cities — the DISTINCT set they have shipped to
 *
 * Every metric is one grouped SQL query (plus a lightweight address-city lookup
 * for the current page), and the SQL is kept portable across MySQL (production)
 * and SQLite (tests): correlated subqueries for the latest name/phone, and plain
 * GROUP_CONCAT(DISTINCT …) — default comma separator — for the city/country sets.
 *
 * Note the country/city/date filters narrow at the order-row level, so a filtered
 * row reflects that customer's activity within the filter (e.g. filtering by a
 * country shows their spend/cities in that country). That is the intended
 * "customers active in X" behaviour.
 */
class CustomerController extends Controller
{
    /** The sort keys the list offers; anything else falls back to `newest`. */
    private const SORTS = ['newest', 'spent_desc', 'spent_asc', 'name'];

    public function __construct(private readonly SalesReport $report) {}

    /**
     * The paginated, filterable customers list.
     */
    public function index(Request $request): Response
    {
        $customers = $this->filtered($request)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $row): array => $this->present($row));

        $this->attachAddressCities($customers->getCollection());

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->query('search'),
                'city' => $request->query('city'),
                'country' => $request->query('country'),
                'sort' => $this->sort($request),
                'min_amount' => $request->query('min_amount'),
                'max_amount' => $request->query('max_amount'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ],
            'countries' => $this->countryOptions(),
            'sorts' => self::SORTS,
            'baseCurrency' => (string) config('payment.currency'),
        ]);
    }

    /**
     * Stream the filtered customers as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'customers-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Name', 'Email', 'Phone', 'City', 'Country', 'Last Ordered', 'Total Spent (AED)', 'Delivered']);

            foreach ($this->filtered($request)->get() as $row) {
                $customer = $this->present($row);

                fputcsv($out, [
                    $customer['name'],
                    $customer['email'],
                    $customer['phone'],
                    implode(' / ', $customer['cities']),
                    implode(' / ', $customer['countries']),
                    $customer['last_ordered'],
                    number_format($customer['total_spent'], 2, '.', ''),
                    $customer['delivered_count'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * The shared, filtered customers query behind the list and the CSV export —
     * one grouped aggregate over `orders`, so both stay in lock-step.
     */
    private function filtered(Request $request): Builder
    {
        // The pinned realized predicate, reused verbatim (never re-inlined) as a
        // CASE guard so total_spent counts paid-or-delivered orders only.
        $realized = $this->report->realizedExpression('orders');

        // Latest-order name/phone via a correlated subquery — portable across
        // MySQL and SQLite (both honour ORDER BY … LIMIT 1 in a scalar subquery).
        $latest = fn (string $column): string => "(select o2.{$column} from orders o2"
            .' where o2.customer_email = orders.customer_email'
            .' order by o2.placed_at desc, o2.id desc limit 1)';

        // The realized-spend aggregate, reused verbatim by the SELECT and the
        // amount-range HAVING so the two never drift (and so HAVING never leans
        // on a SELECT alias, which SQLite does not resolve reliably).
        $spent = "sum(case when {$realized} then orders.total else 0 end)";

        $query = DB::table('orders')
            ->leftJoin('users', 'users.email', '=', 'orders.customer_email')
            ->whereNotNull('orders.customer_email')
            ->where('orders.customer_email', '<>', '')
            ->groupBy('orders.customer_email')
            ->selectRaw('orders.customer_email as email')
            ->selectRaw('max(users.id) as user_id')
            ->selectRaw("coalesce(max(users.name), {$latest('customer_name')}) as name")
            ->selectRaw("{$latest('customer_phone')} as phone")
            ->selectRaw('count(*) as orders_count')
            ->selectRaw("sum(case when orders.status = '".OrderStatus::Delivered->value."' then 1 else 0 end) as delivered_count")
            ->selectRaw("{$spent} as total_spent")
            ->selectRaw('max(orders.placed_at) as last_ordered')
            ->selectRaw('group_concat(distinct orders.customer_country) as countries')
            ->selectRaw('group_concat(distinct orders.customer_district) as cities');

        $this->applyFilters($query, $request, $spent);
        $this->applySort($query, $request);

        return $query;
    }

    /**
     * Row-level (WHERE) and aggregate-level (HAVING) filters.
     */
    private function applyFilters(Builder $query, Request $request, string $spent): void
    {
        $query
            ->when($request->query('search'), function (Builder $q, $term): void {
                $like = '%'.$term.'%';
                $q->where(function (Builder $w) use ($like): void {
                    $w->where('orders.customer_name', 'like', $like)
                        ->orWhere('orders.customer_email', 'like', $like)
                        ->orWhere('orders.customer_phone', 'like', $like)
                        ->orWhere('users.name', 'like', $like);
                });
            })
            ->when($request->query('city'), fn (Builder $q, $city) => $q->where('orders.customer_district', 'like', '%'.$city.'%'))
            ->when($request->query('country'), fn (Builder $q, $country) => $q->where('orders.customer_country', $country))
            ->when($request->query('date_from'), fn (Builder $q, $from) => $q->whereDate('orders.placed_at', '>=', $from))
            ->when($request->query('date_to'), fn (Builder $q, $to) => $q->whereDate('orders.placed_at', '<=', $to));

        // Amount range applies to the aggregated realized spend, so it is a HAVING
        // over the same expression the SELECT computes. The bound values are
        // is_numeric-validated and cast to float, then inlined as numeric literals
        // — a bound `?` here binds as text under SQLite and, since SUM(CASE …)
        // carries no column affinity, would compare numerically-as-text and drop
        // every row. Inlining a cast float is injection-safe and compares numerically.
        if (is_numeric($request->query('min_amount'))) {
            $query->havingRaw("{$spent} >= ".(float) $request->query('min_amount'));
        }

        if (is_numeric($request->query('max_amount'))) {
            $query->havingRaw("{$spent} <= ".(float) $request->query('max_amount'));
        }
    }

    /**
     * Order the grouped result by the requested sort key.
     */
    private function applySort(Builder $query, Request $request): void
    {
        match ($this->sort($request)) {
            'spent_desc' => $query->orderByDesc('total_spent'),
            'spent_asc' => $query->orderBy('total_spent'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('last_ordered'),
        };

        // A stable tiebreaker so pagination never repeats or drops a row.
        $query->orderBy('orders.customer_email');
    }

    /**
     * Merge each registered customer's saved address cities into the page rows,
     * so the Cities chips reflect shipping addresses as well as order districts.
     * One lightweight query scoped to the visible page.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function attachAddressCities(Collection $rows): void
    {
        $userIds = $rows->pluck('user_id')->filter()->all();

        if ($userIds === []) {
            return;
        }

        $byUser = DB::table('addresses')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->get(['user_id', 'city'])
            ->groupBy('user_id');

        $rows->transform(function (array $row) use ($byUser): array {
            if ($row['user_id'] !== null && $byUser->has($row['user_id'])) {
                $cities = array_merge($row['cities'], $byUser[$row['user_id']]->pluck('city')->all());
                $row['cities'] = array_values(array_unique(array_filter(array_map('trim', $cities))));
            }

            return $row;
        });
    }

    /**
     * Shape one grouped DB row into the array the page/CSV consume.
     *
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $countryNames = config('countries');

        $countries = collect($this->split($row->countries))
            ->map(fn (string $code): string => $countryNames[$code] ?? $code)
            ->all();

        return [
            'email' => $row->email,
            'name' => $row->name ?: $row->email,
            'phone' => $row->phone,
            'is_registered' => $row->user_id !== null,
            'orders_count' => (int) $row->orders_count,
            'delivered_count' => (int) $row->delivered_count,
            'total_spent' => (float) $row->total_spent,
            'last_ordered' => $row->last_ordered,
            'cities' => $this->split($row->cities),
            'countries' => $countries,
        ];
    }

    /**
     * Split a GROUP_CONCAT comma list into a clean, de-duplicated array.
     *
     * @return list<string>
     */
    private function split(?string $concatenated): array
    {
        if ($concatenated === null || $concatenated === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $concatenated)))));
    }

    /**
     * The country picker options (code + display name), sorted by name.
     *
     * @return list<array{code: string, name: string}>
     */
    private function countryOptions(): array
    {
        return collect(config('countries'))
            ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * The requested sort key, coerced to a supported one.
     */
    private function sort(Request $request): string
    {
        $sort = (string) $request->query('sort', 'newest');

        return in_array($sort, self::SORTS, true) ? $sort : 'newest';
    }
}
