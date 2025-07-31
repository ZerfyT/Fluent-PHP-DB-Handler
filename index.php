<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use DB;

// --- DATABASE CONFIGURATION (READ/WRITE SPLIT) ---
$config = [
    // Primary database for all INSERT, UPDATE, DELETE operations
    'write' => [
        'host'     => '127.0.0.1',
        'dbname'   => 'test_db',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4'
    ],
    // Replica database for all SELECT operations
    'read' => [
        'host'     => '127.0.0.1',
        'dbname'   => 'test_db',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4'
    ]
];


try {
    // Establish the connection(s) once
    DB::connect($config);
    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Read/Write connections are configured.</p>";
    echo "<hr>";

    // --- 1. INSERT EXAMPLE (uses WRITE connection) ---
    echo "<h2>1. Inserting a new user...</h2>";
    $newUserName = 'Alice-' . time();
    $newUserEmail = 'alice.' . time() . '@example.com';
    $newUserId = DB::table('users')->insert([
        'name' => $newUserName,
        'email' => $newUserEmail,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    echo "New user '{$newUserName}' inserted with ID: " . htmlspecialchars($newUserId);
    echo "<hr>";


    // --- 2. SELECT EXAMPLE (uses READ connection) ---
    echo "<h2>2. Getting all users...</h2>";
    $allUsers = DB::table('users')->orderBy('id', 'desc')->get();
    echo "<pre>" . print_r($allUsers, true) . "</pre>";
    echo "<hr>";


    // --- 3. FORCING A READ FROM THE WRITE CONNECTION ---
    echo "<h2>3. Reading new user from WRITE connection...</h2>";
    echo "<p>This is useful to bypass potential replication lag.</p>";
    $aliceFromWrite = DB::table('users')
        ->useWritePdo() // Force use of the write connection for this query
        ->find($newUserId);
    echo "Found user using useWritePdo(): <pre>" . print_r($aliceFromWrite, true) . "</pre>";
    echo "<hr>";


    // --- 4. UPDATE EXAMPLE (uses WRITE connection) ---
    echo "<h2>4. Updating Alice's name...</h2>";
    $affectedRows = DB::table('users')
        ->where('id', '=', $newUserId)
        ->update([
            'name' => 'Alice Wonder'
        ]);
    echo "Updated {$affectedRows} row(s).";

    // Verify the update (uses READ connection)
    $updatedAlice = DB::table('users')->find($newUserId);
    echo "<p>Verified Name: " . htmlspecialchars($updatedAlice->name) . "</p>";
    echo "<hr>";


    // --- 5. DELETE EXAMPLE (uses WRITE connection) ---
    echo "<h2>5. Deleting the user we just created...</h2>";
    $deletedRows = DB::table('users')->where('id', '=', $newUserId)->delete();
    echo "Deleted {$deletedRows} row(s).";
    echo "<hr>";

    // --- 6. AGGREGATE EXAMPLE (uses READ connection) ---
    echo "<h2>6. Counting remaining users...</h2>";
    $userCount = DB::table('users')->count();
    echo "<p>There are currently {$userCount} users in the database.</p>";
} catch (PDOException | RuntimeException $e) {
    // If the connection or a query fails, this will catch the error.
    echo "<h2>An Error Occurred!</h2>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "</p>";
}
