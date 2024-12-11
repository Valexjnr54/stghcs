
# St. Genevieve Health Care Services - API

This Laravel-based API is developed for **St. Genevieve Health Care Services** to monitor support workers' clock-in and clock-out times, track their location during clock-in, and manage billing processes. The application specifically supports operations in **Shreveport** and **Alexandria**, Louisiana, USA.

---

## Features

- **Clock-In/Clock-Out Monitoring**:
  - Track the exact time support workers start and finish their shifts.
  - Ensure accurate work hour logs.

- **Location Tracking**:
  - Validate clock-in locations (restricted to Shreveport and Alexandria, Louisiana).

- **Billing Management**:
  - Automatically calculate billing based on logged hours and rates.
  - Generate detailed invoices.

- **User Management**:
  - Admin interface to manage support workers and their schedules.
  - Role-based authentication and authorization.

---

## Prerequisites

Before running the application, ensure you have the following installed:

- PHP >= 8.1
- Composer
- Laravel 10
- MySQL
- Node.js (for front-end assets)

---

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/st-genevieve-healthcare.git
   ```

2. Navigate to the project directory:
   ```bash
   cd st-genevieve-healthcare
   ```

3. Install dependencies:
   ```bash
   composer install
   npm install
   ```

4. Create a `.env` file:
   ```bash
   cp .env.example .env
   ```

5. Configure the `.env` file with your database and other environment settings.

6. Generate the application key:
   ```bash
   php artisan key:generate
   ```

7. Run migrations and seed the database:
   ```bash
   php artisan migrate --seed
   ```

8. Start the development server:
   ```bash
   php artisan serve
   ```

---

## Technologies Used

- **Backend**: Laravel 10
- **Database**: MySQL
- **Authentication**: Json Web Token(JWT)
- **Frontend**: Ionic

---

## Contribution Guidelines

1. Fork the repository.
2. Create a new branch: `git checkout -b feature-branch-name`.
3. Commit your changes: `git commit -m 'Add feature'`.
4. Push to the branch: `git push origin feature-branch-name`.
5. Open a Pull Request.

---

## License

This project is licensed under the MIT License. See the `LICENSE` file for more details.

---

## Contact

For questions or support, please contact:

- **Project Owner**: St. Genevieve Health Care Services
- **Developer**: Noko Chukwuebuka Valentine James
- **Email**: jimvalex54@gmail.com
