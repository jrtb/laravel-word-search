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
  - Session-based player tracking
  - RESTful endpoints for submitting and retrieving longest words

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
       "submitted_word": "extraordinary"
   }
   ```
   - `is_longest`: Indicates if the submitted word became the new longest word
   - `submitted_word`: The word that was submitted
   - Note: Words are tracked per session, so each player maintains their own longest word record

2. **Get Current Longest Word**
   ```http
   GET /api/v1/longest-word
   ```
   
   **Response**
   ```json
   {
       "success": true,
       "longest_word": "extraordinary",
       "length": 13
   }
   ```
   - `longest_word`: The longest word found in the current session (null if no words submitted)
   - `length`: The length of the longest word (calculated from word length, 0 if no words submitted)
   - Note: Results are session-specific, each player sees their own longest word

#### Security Configuration
- External APIs use Laravel 11's API middleware group (configured in `bootstrap/app.php`)
- CSRF protection is explicitly excluded for API routes
- Rate limiting is enabled (60 requests per minute per IP)
- Session handling is maintained for player tracking
- Cross-domain requests are supported through CORS configuration
- Response includes rate limit headers (`X-RateLimit-Remaining`, `X-RateLimit-Limit`)

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

1. Import the collection:
   - Open Postman
   - Click "Import" in the top left
   - Select the file: `storage/postman/Word_Search_API.postman_collection.json`

2. Configure the environment:
   - Create a new environment in Postman
   - Add a variable named `base_url`
   - Set its value to your API base URL (default: `http://localhost:8000`)

3. Using the collection:
   - All endpoints are pre-configured with appropriate headers
   - Request bodies are pre-filled with example data
   - Environment variables are used for easy switching between environments

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

## Application Structure

Laravel 11 introduces a simplified application structure:

- `bootstrap/app.php`: Contains core application configuration including:
  - Service provider registration
  - Middleware groups (web and api)
  - Route configurations
  - Exception handling
- `app/Providers/RateLimitingServiceProvider.php`: Handles API rate limiting configuration
  - Configures rate limits for API endpoints (60 requests per minute per IP)
  - Uses Laravel's RateLimiter facade
  - Registered in bootstrap/app.php using ->withProviders()
- No traditional `Kernel.php` files (removed in Laravel 11)
- Streamlined middleware configuration
- Modern routing approach

### Rate Limiting

The application implements rate limiting through a dedicated service provider:

- Rate limits are configured in `RateLimitingServiceProvider`
- Default limit: 60 requests per minute per IP address
- Rate limit headers included in API responses:
  - `X-RateLimit-Limit`: Maximum requests per minute
  - `X-RateLimit-Remaining`: Remaining requests in current window
- Rate limiting is applied to all API endpoints under `/api/v1/`
- Configuration can be easily modified in the service provider

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

The application includes GitHub Actions workflow for automated deployment to AWS Lightsail. Configure the following secrets in your GitHub repository:
- `LIGHTSAIL_AWS_ACCESS_KEY_ID`
- `LIGHTSAIL_AWS_SECRET_ACCESS_KEY`
- `LIGHTSAIL_SSH_PRIVATE_KEY`

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
