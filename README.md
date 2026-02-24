# Dental Charting Application

A standalone PHP application for dental practices to manage patient dental charts with an interactive tooth diagram.

## Features

- **Interactive Tooth Chart**: Click on any tooth to record conditions
- **Dual Notation Support**: Toggle between Universal (1-32) and Palmer notation
- **Patient Management**: Add, search, and edit patient records
- **Condition Tracking**: Record cavities, fillings, crowns, root canals, missing teeth, implants, and more
- **Surface Selection**: Mark specific tooth surfaces (Mesial, Occlusal, Distal, Buccal, Lingual)
- **Notes**: Add notes to individual teeth
- **Treatment Planning**: Create treatment plans with procedures and costs (in database, UI can be extended)
- **History Tracking**: Audit log of all changes
- **Print Ready**: Clean print styles for patient charts

## Installation

### 1. Database Setup

Create the database and tables by running the SQL file:

```bash
mysql -u your_username -p < database.sql
```

Or import `database.sql` via phpMyAdmin.

### 2. Configure Database Connection

Edit `config.php` and update with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_chart');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Upload Files

Upload the following files to your web server:
- `index.php`
- `config.php`

### 4. Access the Application

Navigate to `https://yourdomain.com/dental-chart/` (or wherever you uploaded the files)

## Usage

### Adding a Patient

1. Click "New Patient" button in the header
2. Fill in patient information
3. Click "Save Patient"

### Recording Tooth Conditions

1. Search for and select a patient
2. Click on any tooth in the chart
3. Select a condition from the grid (Healthy, Cavity, Filling, Crown, etc.)
4. Optionally select affected surfaces (M, O, D, B, L)
5. Add any notes
6. Click "Save Changes"

### Switching Notation Systems

Click "Universal" or "Palmer" buttons above the chart to switch between numbering systems.

## Tooth Numbering Reference

### Universal System (Default)
- Upper teeth: 1-16 (right to left, from patient's perspective)
- Lower teeth: 17-32 (left to right, from patient's perspective)

### Palmer Notation
- Each quadrant numbered 1-8 from center
- Quadrant indicated by bracket position (⌐ ⌐ L L)

## Extending the Application

### Adding New Conditions

Insert into the `conditions` table:

```sql
INSERT INTO conditions (name, code, color, description, is_treatment) 
VALUES ('New Condition', 'NC', '#HexColor', 'Description', FALSE);
```

### Adding Procedures

Insert into the `procedures` table:

```sql
INSERT INTO procedures (code, name, category, default_cost, description) 
VALUES ('D0000', 'Procedure Name', 'Category', 100.00, 'Description');
```

## File Structure

```
dental-chart/
├── index.php          # Main application
├── config.php         # Database configuration
├── database.sql       # Database schema
└── README.md          # This file
```

## Requirements

- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Security Notes

- Update `config.php` with strong database credentials
- Consider adding authentication before deploying to production
- Use HTTPS in production

## License

Free for use by dental practices.
