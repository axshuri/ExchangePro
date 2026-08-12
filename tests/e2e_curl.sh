#!/usr/bin/env bash
# End-to-end workflow test (spec §78) against a running server.
# Usage: bash tests/e2e_curl.sh [base_url]
set -u
BASE="${1:-http://127.0.0.1:8090}"
CJ=$(mktemp)

csrf() {
  curl -s -b "$CJ" -c "$CJ" "$1" | grep -oP 'name="_csrf" value="\K[a-f0-9]+' | head -1
}

echo "== E2E workflow test against $BASE =="

# --- Login ---
CSRF=$(csrf "$BASE/login")
curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&username=admin&password=Admin@12345" "$BASE/login" -o /dev/null -w 'login: %{http_code} -> %{redirect_url}\n'

# --- Collect IDs (currency id 2 = USD, 3 = EUR; account 1 = DESK-1, 2 = VAULT-1) ---
USD=2; EUR=3; DESK=1; VAULT=2

# --- BUY 500 USD @ 1.3600 ---
CSRF=$(csrf "$BASE/transactions/buy")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&currency_id=$USD&foreign_amount=500&rate=1.3600&account_id=$VAULT&source_account_id=$DESK&payment_method=cash&fee_amount=0&discount_amount=0&large_confirmed=0" "$BASE/transactions/buy" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "buy: $BODY"

# --- SELL 300 USD @ 1.3800 ---
CSRF=$(csrf "$BASE/transactions/sell")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&currency_id=$USD&foreign_amount=300&rate=1.3800&account_id=$VAULT&destination_account_id=$DESK&payment_method=cash&fee_amount=0&discount_amount=0&large_confirmed=0" "$BASE/transactions/sell" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "sell: $BODY"

# --- EXCHANGE 200 USD -> EUR ---
CSRF=$(csrf "$BASE/transactions/exchange")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&source_currency_id=$USD&target_currency_id=$EUR&source_amount=200&source_account_id=$VAULT&destination_account_id=$VAULT&payment_method=cash&fee_amount=0&discount_amount=0&large_confirmed=0" "$BASE/transactions/exchange" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "exchange: $BODY"

# --- EXPENSE 250 CAD ---
CSRF=$(csrf "$BASE/expenses/create")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&category=rent&amount=250&currency_id=1&account_id=$DESK&expense_date=2026-08-11&description=e2e" "$BASE/expenses" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "expense: $BODY"

# --- TRANSFER 1000 USD vault -> desk ---
CSRF=$(csrf "$BASE/transfers/create")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&source_account_id=$VAULT&destination_account_id=$DESK&currency_id=$USD&amount=1000&transfer_date=2026-08-11&note=e2e" "$BASE/transfers" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "transfer: $BODY"

# --- RECONCILIATION: physical USD 500 less than system ---
CSRF=$(csrf "$BASE/reconciliation")
# fetch current vault USD system balance from inventory page
SYS=$(curl -s -b "$CJ" "$BASE/inventory" | grep -oP 'VAULT-1[^<]*</td>.*?</tr>' | head -1)
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&account_id=$VAULT&currency_id=$USD&physical_balance=0&reason=e2e reconciliation" "$BASE/reconciliation" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "reconciliation: $BODY"

# --- Customer create ---
CSRF=$(csrf "$BASE/customers/create")
BODY=$(curl -s -b "$CJ" -c "$CJ" -d "_csrf=$CSRF&full_name=E2E Customer&phone=%2B15551234567&email=e2e%40test.com" "$BASE/customers" -o /dev/null -w '%{http_code} %{redirect_url}')
echo "customer: $BODY"

# --- Check pages render ---
for p in / /transactions /customers /rates /inventory /ledger /reports/daily /accounting/pnl /accounting/balance-sheet /closing; do
  CODE=$(curl -s -b "$CJ" -o /dev/null -w '%{http_code}' "$BASE$p")
  echo "page $p -> $CODE"
done

# --- Latest transaction detail + receipt ---
TXID=$(curl -s -b "$CJ" "$BASE/transactions" | grep -oP '/transactions/\K[0-9]+' | head -1)
echo "latest tx id: $TXID"
if [ -n "$TXID" ]; then
  CODE=$(curl -s -b "$CJ" -o /dev/null -w '%{http_code}' "$BASE/transactions/$TXID")
  echo "tx detail -> $CODE"
  CODE=$(curl -s -b "$CJ" -o /dev/null -w '%{http_code}' "$BASE/transactions/$TXID/receipt")
  echo "receipt -> $CODE"
fi

# --- CSV export ---
CODE=$(curl -s -b "$CJ" -o /dev/null -w '%{http_code}' "$BASE/export/transactions?format=csv")
echo "csv export -> $CODE"

rm -f "$CJ"
echo "== E2E done =="
