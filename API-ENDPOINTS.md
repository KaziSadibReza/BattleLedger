# BattleLedger API Endpoints

## ✅ Available Wallet Endpoints (For Frontend Users)

### 1. Get Current User's Wallet
**Endpoint:** `GET /wp-json/battle-ledger/v1/wallet/my-wallet`  
**Permission:** Logged-in users only  
**Returns:**
```json
{
  "id": 1,
  "user_id": 1,
  "balance": "100.00",
  "currency": "USD",
  "status": "active",
  "created_at": "2024-01-01 00:00:00",
  "updated_at": "2024-01-01 00:00:00",
  "has_wallet": true
}
```

### 2. Get Current User's Transactions (Paginated)
**Endpoint:** `GET /wp-json/battle-ledger/v1/wallet/my-transactions`  
**Permission:** Logged-in users only  
**Parameters:**
- `page` (default: 1)
- `per_page` (default: 10)
- `limit` (fallback, default: 10)

**Returns:**
```json
{
  "transactions": [
    {
      "id": 1,
      "type": "credit",
      "amount": "50.00",
      "description": "Deposit",
      "created_at": "2024-01-01 00:00:00",
      "balance_after": "150.00",
      "reference_type": null,
      "reference_id": null,
      "created_by": 1
    }
  ],
  "total": 25
}
```

### 3. Get User's Wallet Stats
**Endpoint:** `GET /wp-json/battle-ledger/v1/wallet/user/{user_id}/stats`  
**Permission:** User can access own stats or admin  
**Returns:**
```json
{
  "total_credits": 500.00,
  "total_debits": 200.00,
  "total_transactions": 15,
  "pending_transactions": 2
}
```

### 4. Get Dashboard Stats
**Endpoint:** `GET /wp-json/battle-ledger/v1/user/dashboard-stats`  
**Permission:** Logged-in users only  
**Returns:**
```json
{
  "wallet_balance": 100.00,
  "currency": "USD",
  "total_tournaments": 5,
  "active_tournaments": 2,
  "total_matches": 10,
  "upcoming_matches": 3,
  "recent_transactions": 5
}
```

### 5. Get Payment Gateways
**Endpoint:** `GET /wp-json/battle-ledger/v1/wallet/payment-gateways`  
**Permission:** Logged-in users only  
**Returns:**
```json
{
  "gateways": [
    {
      "id": "stripe",
      "title": "Credit Card (Stripe)",
      "description": "Pay securely with credit card",
      "enabled": true,
      "supports": ["products", "refunds"]
    }
  ]
}
```

### 6. Process Deposit
**Endpoint:** `POST /wp-json/battle-ledger/v1/wallet/deposit`  
**Permission:** Logged-in users only  
**Body:**
```json
{
  "amount": 100.00,
  "payment_method": "stripe"
}
```

### 7. Process Withdrawal
**Endpoint:** `POST /wp-json/battle-ledger/v1/wallet/withdraw`  
**Permission:** Logged-in users only  
**Body:**
```json
{
  "amount": 50.00,
  "method": "bank_transfer",
  "bank_details": {
    "account_name": "John Doe",
    "account_number": "1234567890",
    "bank_name": "Bank Name"
  }
}
```

## ❌ Removed/Invalid Endpoints

The following endpoints DO NOT exist and should not be used:
- ~~`/wallet/user/1`~~ → Use `/wallet/my-wallet` instead
- ~~`/wallet/user/1/transactions`~~ → Use `/wallet/my-transactions` instead

## Frontend Implementation

### UserWallet.tsx - Correct API Calls:
```typescript
// ✅ Fetch wallet
const wallet = await apiFetch({
  path: '/battle-ledger/v1/wallet/my-wallet'
});

// ✅ Fetch transactions with pagination
const txData = await apiFetch({
  path: `/battle-ledger/v1/wallet/my-transactions?page=${page}&per_page=${perPage}`
});

// ✅ Fetch wallet stats
const stats = await apiFetch({
  path: `/battle-ledger/v1/wallet/user/${userId}/stats`
});
```

### Dashboard.tsx - Correct API Calls:
```typescript
// ✅ Fetch dashboard stats
const stats = await apiFetch({
  path: '/battle-ledger/v1/user/dashboard-stats'
});
```

## Controllers Registered

All routes are registered in `battle-ledger.php`:
1. ✅ `BattleLedger\Wallet\WalletController::register_routes`
2. ✅ `BattleLedger\Api\WalletPaymentController::register_routes`
3. ✅ `BattleLedger\Api\DashboardController::register_routes`
4. ✅ `BattleLedger\Auth\AuthController::register_routes`
5. ✅ `BattleLedger\Api\UserController::register_routes`
