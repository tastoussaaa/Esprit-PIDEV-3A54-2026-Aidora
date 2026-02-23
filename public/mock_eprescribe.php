<?php
header('Content-Type: application/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>\n';
echo '<PrescribeResponse>\n';
echo '  <status>accepted</status>\n';
echo '  <prescriptionId>MOCK-'.uniqid().'</prescriptionId>\n';
echo '</PrescribeResponse>';
