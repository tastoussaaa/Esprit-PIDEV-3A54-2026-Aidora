<?php
$mysqli = new mysqli('localhost', 'root', '', 'pi_dev');
if ($mysqli->connect_error) {
    die('Connexion échouée: ' . $mysqli->connect_error);
}

// Check which columns exist
$result = $mysqli->query("DESCRIBE ordonnance");
$existingColumns = [];
while ($row = $result->fetch_assoc()) {
    $existingColumns[] = $row['Field'];
}

// Add columns if they don't exist
if (!in_array('medicament', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN medicament VARCHAR(255) DEFAULT NULL');
    echo "✓ medicament added\n";
}

if (!in_array('dosage', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN dosage VARCHAR(255) DEFAULT NULL');
    echo "✓ dosage added\n";
}

if (!in_array('duree', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN duree VARCHAR(255) DEFAULT NULL');
    echo "✓ duree added\n";
}

if (!in_array('instructions', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN instructions LONGTEXT DEFAULT NULL');
    echo "✓ instructions added\n";
}

if (!in_array('created_at', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
    echo "✓ created_at added\n";
}

if (!in_array('consultation_id', $existingColumns)) {
    $mysqli->query('ALTER TABLE ordonnance ADD COLUMN consultation_id INT DEFAULT NULL');
    echo "✓ consultation_id added\n";
    
    // Add foreign key constraint if not exists
    $fkResult = $mysqli->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME='ordonnance' AND COLUMN_NAME='consultation_id' AND CONSTRAINT_NAME LIKE '%FOREIGN%'");
    
    if ($fkResult->num_rows === 0) {
        $mysqli->query('ALTER TABLE ordonnance ADD CONSTRAINT FK_ORDONNANCE_CONSULTATION 
            FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        echo "✓ Foreign key constraint added\n";
    }
}

echo "\nAll missing columns have been added successfully!\n";
$mysqli->close();
