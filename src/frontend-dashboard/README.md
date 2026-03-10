# Frontend Dashboard

User-facing dashboard for BattleLedger plugin, designed to fit seamlessly within WordPress page content.

## Features

- **Responsive Layout** - Adapts to mobile, tablet, and desktop
- **User Wallet** - Complete wallet management with transaction history
- **Notifications System** - Toast notifications for user feedback
- **Loading States** - Skeleton loaders and spinners
- **Contained Design** - Fits within page content (not full-screen)

## Components

### DashboardLayout
Main layout component that handles navigation and page structure.

### Sidebar
Navigation sidebar with collapsible support.

### Header
Top header with user menu and notifications.

### UserWallet
Complete wallet interface showing balance, transaction history, and quick stats.

### Notifications
Global notification system using custom events.

### Loader
Loading spinner with size variants (small, medium, large).

## Usage

The dashboard auto-initializes on any page with the class `battleledger-dashboard-container`:

```html
<div class="battleledger-dashboard-container" data-props='{"apiUrl":"...","nonce":"...","currentUser":{...},"logoutRedirect":"..."}'></div>
```

## Styling

All styles are contained in `dashboard.scss` and include:
- Responsive breakpoints (1024px, 768px)
- Dark sidebar theme
- Card-based content layout
- Smooth transitions and animations

## Navigation Tabs

- **Wallet** - User's wallet and transaction history
- **Tournaments** - User's tournament registrations  
- **Matches** - User's upcoming and past matches
- **Profile** - User profile settings

## API Integration

Uses `@wordpress/api-fetch` configured with:
- REST API nonce for authentication
- Base URL from WordPress REST API
- Error handling with notifications

## Adding New Pages

1. Create page component in `pages/` folder
2. Add route case in `DashboardLayout.tsx` `renderContent()` method
3. Add navigation button in `Sidebar.tsx`
4. Update TypeScript Tab type

## Mobile Responsive

- Desktop: Standard sidebar layout
- Tablet (1024px): Horizontal sidebar navigation
- Mobile (768px): Simplified single-column layout
