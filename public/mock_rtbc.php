<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$resp = [
  'formulary' => 'preferred',
  'copayEstimate' => 9.99,
  'priorAuthRequired' => false,
  'medication' => $data['medication'] ?? null,
];
echo json_encode($resp);
