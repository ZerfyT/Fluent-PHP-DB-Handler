# Fluent PHP Database Handler

A simple, modern, and fluent database query builder for PHP, inspired by Laravel's `DB` facade. This class provides a clean, secure, and chainable interface for interacting with your MySQL database using PDO, with built-in support for read/write connection splitting to improve performance in large-scale applications.

---

## Features

* **Fluent, Chainable Interface**: Write clean and readable database queries.
* **Read/Write Splitting**: Automatically routes `SELECT` queries to a read replica and write operations (`INSERT`, `UPDATE`, `DELETE`) to the primary database.
* **Secure by Default**: Uses PDO prepared statements to prevent SQL injection vulnerabilities.
* **Advanced Query Building**: Full support for `JOINs`, `WHERE` clauses, `ORDER BY`, `GROUP BY`, `LIMIT`, and `OFFSET`.
* **Aggregates**: Simple methods for `count()`, `sum()`, `avg()`, `min()`, and `max()`.
* **Transactions**: Easy-to-use static methods for database transactions (`beginTransaction`, `commit`, `rollBack`).
* **High-Performance Cursors**: Use `cursor()` to iterate over large datasets with minimal memory usage.
* **Zero Dependencies**: A single, self-contained file that can be dropped into any project.

---

## Requirements

* PHP 8.1 or higher
* PDO MySQL Extension (`pdo_mysql`)

---

## Installation

1. **Download the File**: Place the `DB.php` file into your project's directory (e.g., inside a `lib/` or `app/` folder).

2. **Include the Class**: Include the file in your PHP script where you need to perform database operations.

    ```php
    require_once 'path/to/DB.php';
    ```

3. **Database Setup**: Ensure you have your primary database and, optionally, a read replica database set up and accessible.

---

## Configuration

The `DB` class is initialized by calling the static `connect()` method once, typically in your application's bootstrap file or at the beginning of your script.

### Example: Read/Write Configuration

This is the recommended setup for production environments to distribute the database load.

```php
// config.php

$config = [
    // Primary database for all INSERT, UPDATE, DELETE operations
    'write' => [
        'host'     => 'your-primary-db-host',
        'dbname'   => 'your_database',
        'user'     => 'your_user',
        'password' => 'your_password',
        'charset'  => 'utf8mb4'
    ],
    // Replica database for all SELECT operations
    'read' => [
        'host'     => 'your-replica-db-host',
        'dbname'   => 'your_database',
        'user'     => 'your_user',
        'password' => 'your_password',
        'charset'  => 'utf8mb4'
    ]
];

DB::connect($config);
```

## Usage

All queries start with the static method `DB::table('table_name')`.

**Selecting Data (Read Operations)**
These queries are automatically sent to the read connection.

**Get all records from a table:**

```php
$users = DB::table('users')->get();
```

**Get the first record:**

```php
$user = DB::table('users')->where('status', 'active')->first();
```

**Find a record by its primary key (id):**

```php
$user = DB::table('users')->find(1);
```

**Select specific columns:**

```php
$users = DB::table('users')->select(['name', 'email'])->get();
```

**Add `WHERE` clauses:**

```php
$user = DB::table('users')
            ->where('votes', '>', 100)
            ->orWhere('name', '=', 'John')
            ->first();
```

**Use `WHERE IN`:**

```php
$users = DB::table('users')->whereIn('id', [1, 2, 3])->get();
```

**Ordering, Grouping, and Limiting:**

```php
$users = DB::table('users')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->groupBy('account_id')
            ->limit(10)
            ->offset(5)
            ->get();
```

**Joins:**

```php
$users = DB::table('users')
            ->select(['users.name', 'profiles.photo'])
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->get();
```

**Aggregates**

```php
$userCount = DB::table('users')->where('status', 'active')->count();
$totalSales = DB::table('orders')->where('status', 'completed')->sum('price');
```

**Inserting Data (Write Operation)**

This query is automatically sent to the write connection.

```php
$newUserId = DB::table('users')->insert([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
```

**Updating Data (Write Operation)**

```php
$affectedRows = DB::table('users')
                    ->where('id', '=', 1)
                    ->update(['status' => 'banned']);
```

**Deleting Data (Write Operation)**

```php
$deletedRows = DB::table('posts')->where('is_spam', '=', 1)->delete();
```

**Transactions**

Transactions ensure that a series of operations are executed safely. They always use the write connection.

```php
try {
    DB::beginTransaction();

    DB::table('users')->where('id', 1)->update(['balance' => 50]);
    DB::table('users')->where('id', 2)->update(['balance' => 150]);

    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    // Handle the error
}
```

**Handling Replication Lag**

Sometimes, you need to read data immediately after writing it. Since the read replica might have a slight delay (replication lag), you can force a SELECT query to use the primary write connection.

```php
// Insert a new user (uses WRITE connection)
$newUserId = DB::table('users')->insert(['name' => 'Temp User']);

// Immediately read that user from the primary DB to bypass lag
$newUser = DB::table('users')->useWritePdo()->find($newUserId);
```

## Processing Large Datasets

To process a large number of records without running out of memory, use the `cursor()` method. It returns a PHP Generator, which fetches one record at a time.

```php
foreach (DB::table('logs')->cursor() as $log) {
    // Process each $log object one by one
    process_log($log);
}
```

## Contributing
Contributions are welcome! Please feel free to submit a pull request or open an issue for any bugs or feature requests.

## License
This project is open-source software licensed under the [MIT License]([LICENSE](https://www.google.com/search?q=LICENSE)).
