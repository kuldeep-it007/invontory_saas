# Demo logins

Created by `php artisan demo:seed --fresh --domain=localhost` (local data only; never use these on a real server).
Password for every login: `password123`

## Tenant `acme`: Acme Tiles & Sanitaryware (http://acme.localhost)

| Email | Name | Role | Distributor | Dealer |
|---|---|---|---|---|
| admin@acme.test | Demo Admin | Company_Admin | n/a | n/a |
| staff@acme.test | Sam Staff | Company_Staff | n/a | n/a |
| readonly@acme.test | Rita Reader | Read-only | n/a | n/a |
| distributor.north@acme.test | Nina North | Distributor | North Traders | n/a |
| distributor.south@acme.test | Sunil South | Distributor | South Supplies | n/a |
| distributor.east@acme.test | Esha East | Distributor | East Wholesale | n/a |
| distributor.west@acme.test | Wasim West | Distributor | West Agencies | n/a |
| dealer.north_shop@acme.test | Dev Dealer | Dealer | North Traders | North Tile Shop |
| dealer.north_mart@acme.test | Maya Mart | Dealer | North Traders | North Hardware Mart |
| dealer.south_store@acme.test | Sara Store | Dealer | South Supplies | South Home Store |
| dealer.west_depot@acme.test | Will Depot | Dealer | West Agencies | West Depot |

The "Approved Stores" dealer under South Supplies comes from an approved prospect and has no login.

## Other tenants

| Tenant | URL | Email | Company | Notes |
|---|---|---|---|---|
| starter | http://starter.localhost | owner@starter.test | Starter Co (at its quota limits) | Company_Admin. 5 distributors (Quota Distributor 1 to 5) and 3 dealers under Quota Distributor 1, none with logins. Subscription: trial. |
| lapsed | http://lapsed.localhost | owner@lapsed.test | Lapsed Co (subscription past due) | Company_Admin only. Subscription: past due. |

## Platform admin (separate login)

Run `php artisan platform:admin`. Defaults: `superadmin@platform.test` / `password123`.

## Payment terms worth knowing (acme)

| Party | Credit limit | Overdue limit | Overdue after | Type |
|---|---|---|---|---|
| North Traders | 200000 | 50000 | 30 days | credit |
| South Supplies | 100000 | 10000 | 15 days | credit |
| East Wholesale | none | none | none | cash |
| West Agencies | 5000 | 1000 | 7 days | advance (has an overdue invoice, so new orders are blocked) |
| North Tile Shop | 50000 | none | 30 days | credit |
| North Hardware Mart | 9000 | none | none | credit |
| South Home Store | none | none | none | cash |
| West Depot | 2000 | 500 | 7 days | none |
