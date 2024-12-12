<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# bump v1

# Laravel Word Search Application

A specialized web application for searching and analyzing words based on patterns and frequency of usage. Built with Laravel and modern frontend tooling.

## Features

- **Pattern Search**: Search through two specialized word lists:
  - **Omnigrams List**: Contains words that are 8+ letters long, contain exactly 8 unique letters, and include the letter 'S'
  - **Word-Checker Dictionary**: Contains words that are 5+ letters long, contain the letter 'S', and have a maximum of 8 unique letters

- **Frequency Search**: Search for words based on their frequency of usage in language
  - Default threshold set to 0.0000009
  - Returns up to 200 matching words
  - Shows total count of matches found

## Technical Stack

- Laravel 11
- Tailwind CSS for styling
- Vite for asset bundling
- AWS S3 for word list storage
- MySQL/PostgreSQL for database

## Requirements

- PHP >= 8.2
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

## Required S3 Assets

The application expects the following files to be present in your S3 bucket:
- `assets/list1.txt`: Omnigrams word list
- `assets/list2.txt`: Word-checker dictionary
- `assets/processed_frequencies.csv`: Word frequency data

## Deployment

The application includes GitHub Actions workflow for automated deployment to AWS Lightsail. Configure the following secrets in your GitHub repository:
- `LIGHTSAIL_AWS_ACCESS_KEY_ID`
- `LIGHTSAIL_AWS_SECRET_ACCESS_KEY`
- `LIGHTSAIL_SSH_PRIVATE_KEY`

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
