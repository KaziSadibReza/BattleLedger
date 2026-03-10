# BattleLedger

**Game Tournament Manager** — A comprehensive WordPress plugin for managing gaming tournaments with WooCommerce integration.

## Features

- **Tournament Management** — Create, manage, and finalize tournaments with full CRUD support
- **Wallet System** — Built-in digital wallet with deposits, withdrawals, and transaction history
- **WooCommerce Integration** — Accept payments via WooCommerce for wallet top-ups and tournament entries
- **User Dashboard** — Frontend dashboard for players to view tournaments, wallet, and stats
- **Game Rules Engine** — Configurable game rule presets (Free Fire, PUBG Mobile, and custom)
- **Authentication System** — OTP login, Google OAuth, and email/password registration
- **Push Notifications** — Web Push (VAPID) notifications without external dependencies
- **In-app Notifications** — Real-time notification system for tournament events, wallet activity, and more
- **Leaderboard** — Track player rankings and scores
- **Live Tournaments** — Real-time tournament listings with capacity tracking
- **Admin Panel** — Full React-based admin interface with settings, diagnostics, and wallet management

## Requirements

- **PHP** 8.0+
- **WordPress** 6.0+
- **MySQL** 5.7+ / MariaDB 10.3+
- **PHP Extensions:** OpenSSL, JSON, Mbstring
- **WooCommerce** (optional — required for payment processing)

## Installation

1. Upload the `BattleLedger` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. The plugin will automatically check requirements and show any issues
4. Navigate to **BattleLedger** in the admin menu to configure

## Development

```bash
# Install dependencies
pnpm install

# Start dev server
pnpm dev

# Build for production
pnpm build
```

## License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## Author

[Kazi Sadib Reza](https://github.com/KaziSadibReza)
