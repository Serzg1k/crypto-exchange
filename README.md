# Crypto Exchange Console Commands

This module adds Artisan console commands for:
- finding the **MIN/MAX last price** for a selected trading pair across exchanges (Binance / Bybit / Poloniex / WhiteBIT)
- building a list of common pairs with **profit %** (buy at min price, sell at max price), only for pairs that **exist on ALL exchanges**
- running **tests** (no real HTTP requests — fakes are used)

**Pair format:** `BASE/QUOTE` (e.g. `BTC/USDT`). `BASE_QUOTE` is also accepted (it will be normalized).

---

## Console Commands

### 1) Show min/max price for a pair

Run:
```
php artisan crypto:range BTC/USDT
```
#### Disable markets intersection cache (useful for debugging):
```
php artisan crypto:range BTC/USDT --no-cache
```
### 2) Arbitrage list (top N by profit %)
Run:
```
php artisan crypto:arbitrage
```
#### Limit output rows:
```
php artisan crypto:arbitrage --limit=50
```
#### Disable markets intersection cache:
```
php artisan crypto:arbitrage --no-cache
```
#### Filter by minimum profit percent:
```
php artisan crypto:arbitrage --limit=50 --min-profit=0.05 --no-cache
```
### 3) Tests

Run:
```
php artisan test
```
