# MHJoy Admin Plugins 🛡️

Core WordPress plugins powering the **MHJoyGamersHub** ecosystem. These plugins handle the wallet system, premium spins, automated payments, and headless API integrations.

## 📦 Bundled Plugins

### 1. MHJoy Wallet System (`plugins/mhjoy-wallet-system`)
The heart of the user loyalty and credit system.
- **Spin & Win**: Daily free and premium logic with optimized house edge.
- **Tier System**: Bronze, Silver, Gold, and Platinum loyalty tiers based on spending.
- **Audit Ledger**: Comprehensive tracking of all credits, debits, and gifts.
- **Referral Engine**: Advanced referral commission and milestone tracking.
- **Fraud Detection**: Device fingerprinting and velocity-based security.

### 2. MHJoy Headless API (`plugins/mhjoy-headless-api`)
The bridge between WordPress/WooCommerce and the React frontend.
- **Custom Endpoints**: High-performance REST API for orders, products, and user data.
- **UddoktaPay Integration**: Secure payment initialization and verification.
- **OTP Verification**: Email-based security for guest checkouts.
- **Wallet Integration**: Handles balance deductions and credit-backs during order processing.

## 🛠️ Installation

1. Upload the plugin folders to your WordPress installation's `wp-content/plugins/` directory.
2. Activate the plugins through the 'Plugins' menu in WordPress.
3. Ensure **WooCommerce** is installed and active, as these plugins extend its functionality.

## 🚀 Recent Updates (Jan 2026)
- **Optimized ROI**: Refined SpinWheel logic to increase house profit while maintaining 100% win rate for users.
- **Gift support**: New 'gift' transaction type for rewards and bonuses.
- **Enhanced Security**: Improved row-locking during wallet transactions to prevent race conditions.
- **Print Analytics**: CSS optimizations for professional invoice generation.

---
© 2026 MHJoyGamersHub. Confidentially developed for internal use.
