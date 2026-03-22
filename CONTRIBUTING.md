# Contributing

Thank you for considering contributing to this project! We appreciate your interest in making this SaaS starter kit even better.

## Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold this code.

## How Can I Contribute?

### Reporting Bugs

If you discover a bug, please:

1. Search existing issues to see if it has already been reported
2. Open a new issue with a clear description, steps to reproduce, expected vs actual behavior, and environment details (PHP version, OS, database)

### Suggesting Features

Feature suggestions are welcome! Please open an issue describing:

- The feature and why it would be useful
- Examples of how it would work
- Any alternative solutions you have considered

### Pull Requests

1. Fork the repository
2. Create a new branch from `main` (`git checkout -b feature/my-feature`)
3. Make your changes
4. Write or update tests as needed
5. Ensure tests pass (`./vendor/bin/pest`)
6. Ensure code follows style guidelines (`./vendor/bin/pint --test`)
7. Commit your changes with clear, descriptive messages
8. Push to your fork and submit a pull request to `main`

## Development Setup

### Prerequisites

- PHP 8.4 or higher
- Composer
- Node.js 18+ and npm
- SQLite, MySQL, or PostgreSQL

### Installation

```bash
git clone https://github.com/YOUR-USERNAME/saas-starter-kit.git
cd saas-starter-kit
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite  # For SQLite
php artisan migrate --seed
composer run dev
```

## Coding Standards

### PHP

This project follows the Laravel coding style using [Laravel Pint](https://laravel.com/docs/pint).

- **Check code style**: `./vendor/bin/pint --test`
- **Fix code style**: `./vendor/bin/pint`

### Frontend

- Use Tailwind CSS utility classes
- Keep JavaScript minimal; prefer Livewire and Alpine.js for interactivity
- Use Blade components where appropriate

## Commit Messages

Use conventional commit prefixes:

- `feat:` -- New feature
- `fix:` -- Bug fix
- `docs:` -- Documentation changes
- `refactor:` -- Code refactoring
- `test:` -- Adding or updating tests
- `chore:` -- Maintenance tasks

## Testing

This project uses [Pest PHP](https://pestphp.com/) for testing.

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
