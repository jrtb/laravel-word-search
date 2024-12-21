<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# bump v1

# Laravel Word Search Application

A specialized web application for searching and analyzing words based on patterns and frequency of usage. Built with Laravel 11 and modern frontend tooling.

## Features

- **Pattern Search**: Search through two specialized word lists:
  - **Omnigrams List**: Contains words that are 8+ letters long, contain exactly 8 unique letters, and include the letter 'S'
  - **Word-Checker Dictionary**: Contains words that are 5+ letters long, contain the letter 'S', and have a maximum of 8 unique letters

- **Frequency Search**: Search for words based on their frequency of usage in language
  - Default threshold set to 0.0000009
  - Returns up to 200 matching words
  - Shows total count of matches found

- **Longest Word API**: Track the longest word found by each player
  - Cross-domain support for multi-subdomain deployments
  - Browser fingerprint-based player identification
  - RESTful endpoints for submitting and retrieving longest words
  - Player identity persistence across sessions

- **Session Tracking**: Track player activity with 24-hour sessions
  - Automatic session management based on activity
  - Sessions remain active for 24 hours from last activity
  - New sessions created after 24 hours of inactivity
  - Player identity maintained across sessions
  - Independent streak tracking for daily activity

- **Game Word Count API**: Track words found in each game session
  - Records number of words found per game
  - Maintains highest word count records per player
  - Automatic record updates when exceeded
  - Player-specific tracking using fingerprinting
  - RESTful endpoints for record management

- **Update Game Word Count**
   ```http
   POST /api/v1/game-words/update
   Content-Type: application/json

   {
       "word_count": 15
   }
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "word_count": 15,
       "highest_word_count": 42,
       "is_new_record": false,
       "player_id": "8f7d9c2e"
   }
   ```
   - `word_count`: The number of words found in the current game
   - `highest_word_count`: Player's highest word count across all games
   - `is_new_record`: Whether this submission set a new record
   - `player_id`: SHA-256 hash of browser fingerprint
   - Note: Automatically updates highest count if current count exceeds it

- **Get Current Play Session**
   ```http
   GET /api/v1/play-session/current
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "session_id": 123,
       "omnigram": "STARLIGHT",
       "started_at": "2024-03-20T00:00:00Z",
       "time_remaining": 43200,
       "words": [
           {
               "word": "STAR"
           }
       ]
   }
   ```
   - `session_id`: Unique identifier for the current play session
   - `omnigram`: The current word puzzle to solve
   - `started_at`: When the session started (ISO 8601 format)
   - `time_remaining`: Seconds remaining in the current session
   - `words`: Array of words found in this session
   - Note: Creates a new session if none exists or if previous session expired

- **Submit Word to Play Session**
   ```http
   POST /api/v1/play-session/submit-word
   Content-Type: application/json

   {
       "word": "STAR"
   }
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "word": "STAR"
   }
   ```
   - `word`: The submitted word if valid
   - Note: Validates word against current session's omnigram
   - Note: Automatically creates new session if needed
   - Note: Adds word to current session's word list if valid

## Player Identity System

The application uses a sophisticated browser fingerprint-based player identification system:

1. **PlayerIdentityService**
   - Handles all player identification logic
   - Uses request fingerprinting to recognize returning players
   - Maintains player identity across sessions
   - Generates consistent SHA-256 hash from request attributes:
     - IP address
     - User agent
     - Accept-Language header
   - Handles edge cases:
     - Browser changes (new fingerprint)
     - Session expiration (maintains identity)
     - Multiple tabs/windows (same identity)

2. **Request Fingerprinting**
   - Combines multiple request attributes
   - Generates consistent fingerprints for returning players
   - Handles changes in browser or device
   - Maintains privacy (no personal data stored)

3. **Session Management**
   - Associates fingerprints with session IDs
   - Updates session associations when players return
   - Maintains player records across sessions
   - Handles concurrent sessions gracefully

4. **Testing Support**
   - Comprehensive test suite for identity system
   - Tests for fingerprint generation
   - Tests for cross-session identity maintenance
   - Tests for different browser scenarios

## API Documentation

The application provides two types of APIs:

### Internal APIs (Website Only)

These endpoints are for internal use by the website only and are protected by CSRF tokens. The CSRF protection is configured in `bootstrap/app.php` using Laravel 11's new middleware configuration:

1. **Pattern Search**
   ```http
   POST /search
   Content-Type: application/json
   X-CSRF-TOKEN: {csrf-token}

   {
       "query": "example",
       "list": "both"  // Options: "omnigrams", "wordchecker", "both"
   }
   ```

2. **Frequency Search**
   ```http
   POST /search-frequency
   Content-Type: application/json
   X-CSRF-TOKEN: {csrf-token}

   {
       "frequency": 0.0000009
   }
   ```

Note: These internal APIs use Laravel 11's web middleware group which includes CSRF protection.

### External APIs (Public)

These endpoints are publicly accessible and explicitly exclude CSRF token requirements through Laravel 11's API middleware configuration:

1. **Submit a Word**
   ```http
   POST /api/v1/longest-word
   Content-Type: application/json

   {
       "word": "extraordinary"
   }
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "is_longest": true,
       "submitted_word": "extraordinary",
       "player_id": "8f7d9c2e"
   }
   ```
   - `is_longest`: Indicates if the submitted word became the new longest word
   - `submitted_word`: The word that was submitted
   - `player_id`: SHA-256 hash of browser fingerprint
   - Note: Words are tracked per player, with identity maintained across sessions
   - Sessions remain active for 24 hours from last activity
   - New sessions are created after 24 hours of inactivity

2. **Get Current Longest Word**
   ```http
   GET /api/v1/longest-word
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "longest_word": "extraordinary",
       "length": 13,
       "player_id": "8f7d9c2e"
   }
   ```
   - `longest_word`: The longest word found by this player (null if no words submitted)
   - `length`: The length of the longest word (0 if no words submitted)
   - `player_id`: SHA-256 hash of browser fingerprint
   - Note: Results are player-specific, with identity maintained across sessions

3. **Get Top 10 Longest Words**
   ```http
   GET /api/v1/longest-word/top
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "words": [
           {
               "word": "supercalifragilistic",
               "player_id": "8f7d9c2e",
               "length": 20,
               "submitted_at": "2024-03-15T10:30:00Z"
           }
       ]
   }
   ```
   - `words`: Array of longest words across all players, ordered by length
   - `player_id`: SHA-256 hash of submitter's browser fingerprint
   - `length`: Word length
   - `submitted_at`: Timestamp of submission

4. **Record Player Session**
   ```http
   POST /api/v1/session
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "current_streak": 3,
       "highest_streak": 5,
       "last_session_date": "2024-03-19"
   }
   ```
   - `current_streak`: Number of consecutive days played
   - `highest_streak`: Highest streak achieved by the player
   - `last_session_date`: Date of the most recent session
   - Note: Multiple sessions in the same day count as one day for streak purposes

5. **Get Player Streak Info**
   ```http
   GET /api/v1/session/streak
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "current_streak": 3,
       "highest_streak": 5,
       "last_session_date": "2024-03-19"
   }
   ```
   - `current_streak`: Current consecutive days streak (0 if broken)
   - `highest_streak`: Highest streak ever achieved
   - `last_session_date`: Date of the last recorded session

4. **Get Player's Highest Word Count**
   ```http
   GET /api/v1/game-words/highest
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "highest_word_count": 42,
       "player_id": "8f7d9c2e"
   }
   ```
   - `highest_word_count`: The highest number of words found in a single game (0 if no games played)
   - `player_id`: SHA-256 hash of browser fingerprint
   - Note: Results are player-specific, with identity maintained across sessions

5. **Update Game Word Count**
   ```http
   POST /api/v1/game-words/update
   Content-Type: application/json

   {
       "word_count": 15
   }
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "word_count": 15,
       "highest_word_count": 42,
       "is_new_record": false,
       "player_id": "8f7d9c2e"
   }
   ```
   - `word_count`: The number of words found in the current game
   - `highest_word_count`: Player's highest word count across all games
   - `is_new_record`: Whether this submission set a new record
   - `player_id`: SHA-256 hash of browser fingerprint
   - Note: Automatically updates highest count if current count exceeds it

6. **Get Current Play Session**
   ```http
   GET /api/v1/play-session/current
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "session_id": 123,
       "omnigram": "STARLIGHT",
       "started_at": "2024-03-20T00:00:00Z",
       "time_remaining": 43200,
       "words": [
           {
               "word": "STAR"
           }
       ]
   }
   ```
   - `session_id`: Unique identifier for the current play session
   - `omnigram`: The current word puzzle to solve
   - `started_at`: When the session started (ISO 8601 format)
   - `time_remaining`: Seconds remaining in the current session
   - `words`: Array of words found in this session
   - Note: Creates a new session if none exists or if previous session expired

7. **Submit Word to Play Session**
   ```http
   POST /api/v1/play-session/submit-word
   Content-Type: application/json

   {
       "word": "STAR"
   }
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "word": "STAR"
   }
   ```
   - `word`: The submitted word if valid
   - Note: Validates word against current session's omnigram
   - Note: Automatically creates new session if needed
   - Note: Adds word to current session's word list if valid

#### Security Configuration
- External APIs use Laravel 11's API middleware group (configured in `bootstrap/app.php`)
- CSRF protection is explicitly excluded for API routes
- Session handling is maintained for player tracking:
  - Each player is identified by browser fingerprinting
  - Player IDs are SHA-256 hashes of request attributes
  - Session isolation ensures privacy of word records
- Cross-domain requests are supported through CORS configuration

### Interactive API Documentation

#### Swagger UI
The API documentation is available through Swagger UI, which provides an interactive interface to explore and test the API endpoints.

1. Start the Laravel development server:
   ```bash
   php artisan serve
   ```

2. Visit the Swagger UI documentation:
   ```
   http://localhost:8000/api/documentation
   ```

3. You can:
   - View detailed endpoint specifications
   - Try out API calls directly from the browser
   - See request/response schemas and examples
   - View available parameters and response codes

#### Postman Collection
A Postman collection is available for testing the API endpoints in Postman.

1. Import the collection and environments:
   - Open Postman
   - Click "Import" in the top left
   - Select these files:
     - Collection: `storage/postman/Word_Search_API.postman_collection.json`
     - Development Environment: `storage/postman/Word_Search_API.postman_environment.json`
     - Production Environment: `storage/postman/Word_Search_API.production.postman_environment.json`

2. The environments are pre-configured with the following base URLs:
   - Development: `http://localhost:8000`
   - Production: `https://wordlists.fairladymedia.com`

3. Using the collection:
   - All endpoints are pre-configured with appropriate headers
   - Request bodies are pre-filled with example data
   - Switch environments using the environment dropdown in the top-right corner of Postman
   - Environment variables are automatically applied to requests

## Technical Stack

- Laravel 11 (with new middleware configuration)
- Tailwind CSS for styling
- Vite for asset bundling
- AWS S3 for word list storage
- MySQL/PostgreSQL for database

## Development Guidelines

### AI Assistant Conversation

The project includes a conversation prompt file (`resources/prompts/conversation.txt`) that provides context and guidelines for AI-assisted development. This file helps maintain consistency in development practices by:

- Defining the application's core purpose and features
- Establishing technical context (Laravel, Tailwind CSS)
- Setting standards for code quality and security
- Ensuring adherence to project conventions

The prompt file can be referenced at the start of AI assistant conversations to maintain consistent and productive development sessions.

## Requirements

- PHP >= 8.2
- Laravel 11
- Composer
- Node.js & NPM
- Database (MySQL, PostgreSQL, etc.)
- AWS S3 bucket access

## Installation

1. Clone the repository:
```bash
git clone [repository-url]
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install NPM dependencies:
```bash
npm install
```

4. Create environment file:
```bash
cp .env.example .env
```

5. Configure your environment variables:
   - Database credentials
   - AWS credentials (S3 bucket access)
   - Application key

6. Generate application key:
```bash
php artisan key:generate
```

7. Run migrations:
```bash
php artisan migrate
```

8. Start the development server:
```bash
php artisan serve
```

9. In a separate terminal, start Vite:
```bash
npm run dev
```

## Database Management

### Development Environment

The application uses SQLite by default for development:
- Database file location: `database/database.sqlite`
- Configuration in `.env`: `DB_CONNECTION=sqlite`

#### Database Reset Scenarios
The database may be reset (clearing all player data) in these cases:
1. Running `php artisan migrate:fresh` - Rebuilds database from scratch
2. Running `php artisan migrate:reset` - Rolls back all migrations
3. Manually recreating database: `rm database/database.sqlite && touch database/database.sqlite`
4. Running tests with `RefreshDatabase` trait

#### Preserving Data
To preserve player data during development:
- Use `php artisan migrate` instead of `migrate:fresh` when adding new migrations
- Take database backups before major migration changes
- Consider using database seeders for test data

### Production Environment

For production deployments:
- Use a robust database system (MySQL/PostgreSQL)
- Implement proper backup strategies
- Never run `migrate:fresh` or `migrate:reset` on production
- Use only `php artisan migrate` for schema updates

## Application Structure

Laravel 11 introduces a simplified application structure:

- `bootstrap/app.php`: Contains core application configuration including:
  - Service provider registration
  - Middleware groups (web and api)
  - Route configurations:
    - API routes loaded from routes/api.php for better organization of versioned endpoints
    - Web routes loaded from routes/web.php for web interface endpoints
  - Exception handling
- No traditional `Kernel.php` files (removed in Laravel 11)
- Streamlined middleware configuration
- Modern routing approach

## Required S3 Assets

The application expects the following files to be present in your S3 bucket:
- `assets/list1.txt`: Omnigrams word list
- `assets/list2.txt`: Word-checker dictionary
- `assets/processed_frequencies.csv`: Word frequency data

## Caching

The application uses Laravel's file cache driver to improve performance when loading word lists. The cache configuration is as follows:

- Word lists are cached for 24 hours
- Cache files are stored in `storage/framework/cache/data`
- Cache keys used:
  - `list1_words`: For the Omnigrams list
  - `list2_words`: For the Word-checker dictionary

### Clearing the Cache

You may need to clear the cache in the following situations:
- After updating the word lists in S3
- If you encounter stale or incorrect data
- During troubleshooting

To clear the cache, run one of these commands:

```bash
# Clear all application cache
php artisan cache:clear

# Clear specific cache keys
php artisan tinker
>>> Cache::forget('list1_words');
>>> Cache::forget('list2_words');
```

## Deployment

### AWS Lightsail with Bitnami

If deploying to AWS Lightsail using Bitnami's Laravel stack:

1. Application Path:
   - Root Directory: `/home/bitnami/stack/apache2/htdocs/laravel-word-search`
   - Web Root: `/home/bitnami/stack/apache2/htdocs/laravel-word-search/public`

2. Database Permissions (SQLite):
   ```bash
   # Navigate to application directory
   cd /home/bitnami/stack/apache2/htdocs/laravel-word-search
   
   # Create database directory if it doesn't exist
   sudo mkdir -p database
   
   # Set proper ownership and permissions for database directory
   sudo chown -R daemon:daemon database/
   sudo chmod -R 775 database/
   
   # Ensure SQLite database file is writable
   sudo touch database/database.sqlite
   sudo chown daemon:daemon database/database.sqlite
   sudo chmod 664 database/database.sqlite
   ```

3. Storage Permissions:
   ```bash
   # Set proper ownership for Bitnami environment
   sudo chown -R daemon:daemon storage/
   sudo chmod -R 775 storage/
   
   # Ensure cache directory has correct permissions
   sudo mkdir -p storage/framework/cache/data
   sudo chown -R daemon:daemon storage/framework/cache/
   sudo chmod -R 775 storage/framework/cache/
   ```

4. Cache Configuration:
   - Ensure `.env` has `CACHE_DRIVER=file`
   - Verify storage/framework/cache/data directory exists and is writable
   - Clear cache after deployment:
     ```bash
     # Clear application cache as bitnami user
     sudo -u daemon php artisan cache:clear
     
     # If needed, also clear config cache
     sudo -u daemon php artisan config:clear
     ```

5. Running Laravel Commands:
   ```bash
   # General format for running artisan commands in Bitnami
   sudo -u daemon php artisan <command>
   
   # Examples:
   sudo -u daemon php artisan migrate
   sudo -u daemon php artisan config:clear
   sudo -u daemon php artisan route:clear
   ```

6. Bitnami Service Restart (if needed):
   ```bash
   sudo /opt/bitnami/ctlscript.sh restart apache
   ```

Note: Bitnami uses `daemon` as the web server user instead of the traditional `www-data`.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Development

### Local Environment Setup

1. Using PHP's Built-in Server:
   ```bash
   # Instead of php artisan serve, use:
   php -S localhost:8000 -t public/
   
   # Or to avoid the "broken pipe" warning, use:
   php artisan serve --port=8000
   ```

   Note: If you encounter a "broken pipe" warning when using `php artisan serve`, this is typically caused by the browser making favicon requests. This warning is harmless and can be:
   - Ignored (it doesn't affect functionality)
   - Resolved by adding a favicon.ico to your public directory
   - Avoided by using the `php artisan serve` command instead of the built-in server directly

2. Using Vite for Assets:
   ```bash
   # In a separate terminal
   npm run dev
   ```

## Security and Error Handling

### CSRF Protection
- All internal web routes are protected by CSRF tokens
- CSRF tokens are automatically included in the blade template via meta tag
- JavaScript requests include CSRF token in `X-CSRF-TOKEN` header
- Enhanced error handling for CSRF token mismatches
- Proper session handling across all routes

### Error Handling
- Comprehensive client-side error handling
- Detailed error logging in browser console
- User-friendly error messages
- Graceful handling of:
  - CSRF token mismatches
  - Network errors
  - Invalid responses
  - Server errors
- Loading states and indicators
- Fallback content for failed requests

## Troubleshooting

### Common Issues

1. CSRF Token Errors
   - Clear browser cache
   - Perform a hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
   - Check browser console for detailed error messages
   - Verify CSRF token is present in meta tag

2. Session Issues
   - Clear Laravel cache: `php artisan cache:clear`
   - Clear config cache: `php artisan config:clear`
   - Clear route cache: `php artisan route:clear`
   - Clear view cache: `php artisan view:clear`

3. API Response Issues
   - Check browser's developer tools Network tab
   - Verify proper headers are being sent
   - Ensure proper error handling in frontend code
   - Check server logs for detailed error messages

## Session Management

### 24-Hour Session System

The application implements a sophisticated 24-hour session management system:

1. **Session Creation**
   - New sessions are created for first-time players
   - Sessions are also created after 24 hours of inactivity
   - Session IDs are unique per player and time period

2. **Session Duration**
   - Sessions remain active for 24 hours from last activity
   - Any word submission within 24 hours reuses the same session
   - After 24 hours of inactivity, a new session is created

3. **Player Identity**
   - Player identity is maintained independently of sessions
   - Same player can have multiple sessions over time
   - Browser fingerprinting ensures consistent player identification

4. **Session Tracking**
   - Each word submission is associated with a session
   - Session timestamps track player activity
   - Session data helps analyze player engagement patterns

5. **Implementation Details**
   - Sessions are managed automatically by the application
   - No manual session management required from players
   - Seamless experience across multiple play sessions
   - Maintains data consistency across session changes
