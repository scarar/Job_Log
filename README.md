# Job Application Log

A simple web application to track your job applications and their status.

## Features

- Add new job applications with detailed information
- Track application status (Applied, Interviewing, Offer, Rejected, Accepted)
- View recent applications in a table format
- Store job posting URLs and follow-up dates
- Add notes and job descriptions
- Responsive design for mobile and desktop

## Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache, Nginx, etc.)

## Installation

1. Clone or download this repository to your web server's document root
2. Create a MySQL database named `job_log`
3. Import the database schema from `config/schema.sql`
4. Configure your database connection in `config/database.php`
5. Access the application through your web browser

## Usage

1. Open the application in your web browser
2. Fill out the form to add a new job application
3. Required fields are marked with validation
4. View your recent applications in the table on the right
5. Track your application status and follow-up dates

## Database Configuration

Edit `config/database.php` with your MySQL credentials:

```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'job_log');
```

## Security Notes

- This is a basic application and should be enhanced with proper security measures for production use
- Consider adding user authentication
- Implement proper input validation and sanitization
- Use prepared statements for all database queries

## License

This project is open-source and available under the MIT License. 