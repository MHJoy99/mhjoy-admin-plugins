# MHJoy Game Request System

A secure, API-backed game request and voting system for WordPress with Rawg.io integration.

## Features

- 🎮 **Game Search** - Search games via Rawg.io API with autocomplete
- 🗳️ **Dual Voting System** - Regular (1x) and Pro (5x weight) votes
- 🔒 **Security** - Cloudflare Turnstile + FingerprintJS anti-bot protection
- 📊 **Analytics** - Comprehensive tracking and dashboard
- ⭐ **License Codes** - Pro voter system with unique codes
- 🎯 **Admin Panel** - Full management interface
- 📱 **REST API** - Headless-ready for React frontend

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Game Requests → Settings** and configure:
   - Rawg.io API key (required)
   - Cloudflare Turnstile keys (optional but recommended)
   - FingerprintJS API key (optional)

## Configuration

### Required

- **Rawg.io API Key**: Get from [rawg.io/apidocs](https://rawg.io/apidocs)

### Recommended

- **Cloudflare Turnstile**: Free bot protection
  - Site Key (frontend)
  - Secret Key (backend)
  - Get from [Cloudflare Dashboard](https://dash.cloudflare.com/)

### Optional

- **FingerprintJS**: Enhanced fingerprinting (leave empty for open-source version)
- **Rate Limit**: Max votes per hour for guests (default: 10)
- **Cache Duration**: Rawg API cache time in seconds (default: 3600)

## Database Tables

The plugin creates 4 tables:

1. `wp_mhjoy_request_games` - Game data from Rawg
2. `wp_mhjoy_game_votes` - All vote records
3. `wp_mhjoy_game_analytics` - Analytics events
4. `wp_mhjoy_license_codes` - Pro voter licenses

## REST API Endpoints

### Public Endpoints

- `GET /wp-json/mhjoy/v1/games/search?q={query}` - Search games
- `GET /wp-json/mhjoy/v1/games/list` - Get all active requests
- `GET /wp-json/mhjoy/v1/games/completed` - Get fulfilled requests
- `GET /wp-json/mhjoy/v1/games/{id}` - Get single game details
- `POST /wp-json/mhjoy/v1/games/vote` - Submit a vote
- `POST /wp-json/mhjoy/v1/games/validate-license` - Validate license code
- `POST /wp-json/mhjoy/v1/analytics/track` - Track analytics event

### Admin Endpoints (require authentication)

- `POST /wp-json/mhjoy/v1/admin/games/fulfill` - Mark game as fulfilled
- `POST /wp-json/mhjoy/v1/admin/games/bulk-action` - Bulk actions
- `GET /wp-json/mhjoy/v1/admin/analytics/dashboard` - Get analytics stats
- `POST /wp-json/mhjoy/v1/admin/licenses/generate` - Generate license codes

## Vote Submission

```javascript
POST /wp-json/mhjoy/v1/games/vote

{
  "game_data": {
    "rawg_id": 3498,
    "name": "Grand Theft Auto V",
    "slug": "grand-theft-auto-v",
    "background_image": "https://...",
    "release_year": "2013"
  },
  "voter_name": "John Doe",
  "license_code": "MHJOY-XXXX-XXXX-XXXX", // Optional for Pro vote
  "fingerprint": "abc123...",
  "turnstile_token": "token..."
}
```

## Admin Panel

Access via **WordPress Admin → Game Requests**

### Dashboard
- Total votes, unique voters, conversion rate
- Votes timeline chart (last 30 days)
- Top voted games
- Most searched games

### All Requests
- View all active game requests
- Sort by votes, date, alphabetical
- Bulk actions (fulfill, delete)
- Individual game management

### Completed
- Archive of fulfilled requests
- Completion dates and vote counts

### License Codes
- Generate Pro voter codes
- View code status (active/used)
- Track code usage

### Settings
- Configure API keys
- Set rate limits
- Adjust cache duration

## Security Features

1. **Turnstile Validation** - Prevents bot voting
2. **Fingerprint Hashing** - Privacy-safe user tracking
3. **Rate Limiting** - Max 10 votes/hour for guests
4. **Duplicate Prevention** - One vote per game per user
5. **Input Sanitization** - All user input cleaned
6. **Nonce Validation** - CSRF protection for admin actions

## Voting Logic

### Regular Voter
- Must provide name
- Weight: 1x
- Rate limited (10 votes/hour)
- Tracked by fingerprint

### Pro Voter
- Must provide valid license code
- Weight: 5x
- No rate limit
- Code becomes single-use after voting

## Development

### File Structure

```
mhjoy-game-requests/
├── mhjoy-game-requests.php (Main plugin file)
├── includes/
│   ├── class-database.php (Schema & queries)
│   ├── class-rawg-api.php (Rawg integration)
│   ├── class-security.php (Turnstile, rate limiting)
│   ├── class-license-manager.php (Pro codes)
│   ├── class-vote-handler.php (Core voting logic)
│   ├── class-analytics.php (Tracking & stats)
│   ├── class-api.php (REST endpoints)
│   └── class-admin.php (WP Admin panel)
├── admin/
│   └── assets/
│       ├── admin.css
│       └── admin.js
└── README.md
```

### Hooks & Filters

```php
// Modify rate limit
add_filter('mhjoy_gr_rate_limit', function($limit) {
    return 20; // 20 votes per hour
});

// Custom vote validation
add_filter('mhjoy_gr_before_vote', function($vote_data) {
    // Custom logic
    return $vote_data;
});
```

## Troubleshooting

### Votes not recording
- Check Turnstile keys are correct
- Verify Rawg API key is valid
- Check browser console for errors

### Search not working
- Confirm Rawg API key in settings
- Check API rate limits (20k/month free tier)
- Clear cache: delete transients starting with `mhjoy_gr_`

### License codes not working
- Ensure code is active (not already used)
- Check code format: `MHJOY-XXXX-XXXX-XXXX`
- Verify in License Codes admin page

## Support

For issues or questions:
- Check WordPress error logs
- Enable WP_DEBUG for detailed errors
- Contact: support@mhjoygamershub.com

## Changelog

### 1.0.0 (2026-01-24)
- Initial release
- Game search via Rawg.io
- Dual voting system (Regular/Pro)
- Turnstile + Fingerprint security
- Analytics dashboard
- License code system
- REST API for headless frontend

## License

GPL v2 or later

## Credits

- Rawg.io for game data API
- Cloudflare for Turnstile
- FingerprintJS for user tracking
- Chart.js for analytics visualization
