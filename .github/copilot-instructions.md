# BattleLedger - AI Coding Instructions

## Project Overview
BattleLedger is a WordPress plugin for game tournament management with WooCommerce integration. It uses a modern stack: **PHP 8.0+ backend** with **React 18 + TypeScript + Vite** frontend.

## Architecture

### Directory Structure
- `battle-ledger.php` - Plugin bootstrap, constants (`BATTLE_LEDGER_*`), component initialization
- `includes/` - PHP backend organized by domain:
  - `Core/` - Assets, Installer, Cache, PageInstaller
  - `Api/` - REST controllers under namespace `battle-ledger/v1`
  - `Database/` - Schema definitions and QueryBuilder utility
  - `Admin/` - WordPress admin menu and settings
  - `Auth/` - OTP, Google OAuth, security features
  - `Frontend/` - Shortcodes and template loader
  - `WooCommerce/` - HPOS-compatible integration (conditional)
- `src/` - React/TypeScript frontend:
  - `main.tsx` - Admin SPA entry point (renders into `#battle-ledger-root`)
  - `frontend-auth-login/` - Frontend auth components (shortcode-driven)
  - `pages/` - Admin page components (Dashboard, Tournaments, Settings, etc.)
  - `components/` - Shared UI components
- `templates/` - PHP templates for frontend shortcodes

### Key Patterns

**Singleton Pattern** - Core PHP classes use `::instance()`:
```php
BattleLedger\Core\Assets::instance();
```

**REST API** - All endpoints registered under `battle-ledger/v1`:
```php
register_rest_route('battle-ledger/v1', '/tournaments', [...]);
```
Permission checks use `RestController::check_permissions()` → `manage_battle_ledger` capability.

**QueryBuilder** - Custom database abstraction for `bl_*` tables:
```php
$query = new QueryBuilder('bl_tournaments');
$results = $query->where('status', 'active')->orderBy('created_at', 'DESC')->get();
```

**Frontend Data Bridge** - PHP passes data via `wp_localize_script`:
```javascript
window.battleLedgerData = { restUrl, nonce, restNamespace, ... }
```
Configure `@wordpress/api-fetch` with this data in `main.tsx`.

## Development Workflow

### Commands
```bash
pnpm dev      # Start Vite dev server (port 5173) with HMR
pnpm build    # Production build → assets/ directory
```

### Vite + WordPress Integration
- Dev mode auto-detects via `fsockopen('localhost', 5173)` in `Assets.php`
- Production reads `assets/.vite/manifest.json` for hashed filenames
- Scripts need `type="module"` - handled by `add_module_type_to_scripts` filter

### Database Tables (prefix: `bl_`)
- `bl_tournaments` - Tournament definitions
- `bl_tournament_participants` - User registrations
- `bl_matches` - Match scheduling and results
- `bl_tournament_logs` - Activity logging

## Conventions

### PHP
- Namespace: `BattleLedger\{Domain}` (e.g., `BattleLedger\Api\TournamentController`)
- Use WordPress coding standards and functions (`wp_*`, `sanitize_*`, `esc_*`)
- Capabilities check: `current_user_can('manage_battle_ledger')`
- Text domain: `battle-ledger` for translations

### TypeScript/React
- Path alias: `@/` → `src/`
- Use `@wordpress/api-fetch` for REST calls (pre-configured with nonce)
- SCSS for styling (in `src/styles/`)
- Component icons: `lucide-react`
- Drag-and-drop: `@dnd-kit` library

### Shortcodes
- `[battle_ledger_tournaments]` - Tournament list
- `[battle_ledger_tournament id="X"]` - Single tournament
- `[battle_ledger_leaderboard]` - Leaderboard display
- `[battle_ledger_user_dashboard]` - User's tournament dashboard

## WooCommerce Integration
Conditional loading when WooCommerce active. Declares HPOS (High-Performance Order Storage) compatibility. Check with `class_exists('WooCommerce')`.
