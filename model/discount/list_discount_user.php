<?php
// File: ./model/discount/list_discount_user.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../model/Discount_user.php';
include_once __DIR__ . '/../../utils/helpers.php';

// Extract user_id from URL
$request_uri = $_SERVER['REQUEST_URI'];
if (preg_match("/\/discount\/user\/(\w+)$/", $request_uri, $matches)) {
    $user_id = $matches[1];
} else {
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'message' => 'Invalid URL format. Expected: /discount/user/{user_id}',
        'code' => 400
    ]);
    http_response_code(400);
    exit;
}

$discount_user = new DiscountUser($conn);

try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 40;
    
    // Get discounts list for specific user
    $result = $discount_user->read($page, $limit, $user_id);
    $total_discounts = $discount_user->getTotalCount($user_id);
    $discounts_arr = [];
    
    if (!$result) {
        throw new Exception("No discounts found for user ID: $user_id", 404);
    }
    
    while ($row = $result->fetch_assoc()) {
        // Format dates
        $valid_from = new DateTime($row['valid_from']);
        $valid_to = new DateTime($row['valid_to']);
        
        $discount_item = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'code' => $row['code'],
            'description' => $row['description'],
            'discount_percent' => (float)$row['discount_percent'],
            'valid_from' => $valid_from->format('Y-m-d'),
            'valid_to' => $valid_to->format('Y-m-d'),
            'status' => $row['status'],
            'minimum_price' => (float)$row['minimum_price'],
            'type' => $row['type'],
            'days_remaining' => $row['status'] === 'active' ? 
                (new DateTime())->diff($valid_to)->days : 0
        ];
        
        $discounts_arr[] = $discount_item;
    }
    
    $response = [
        'ok' => true,
        'status' => 'success',
        'message' => 'User discounts retrieved successfully',
        'code' => 200,
        'data' => [
            'user_id' => $user_id,
            'discounts' => $discounts_arr,
            'pagination' => [
                'total' => (int)$total_discounts,
                'count' => count($discounts_arr),
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => ceil($total_discounts / $limit)
            ]
        ]
    ];
    http_response_code(200);
} catch (Exception $e) {
    $response = [
        'ok' => false,
        'status' => 'error',
        'code' => $e->getCode() ?: 400,
        'message' => $e->getMessage()
    ];
    http_response_code($e->getCode() ?: 400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);