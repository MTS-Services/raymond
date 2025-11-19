<?php
require_once 'config.php';

$cardId = $_GET['id'] ?? '';

if (empty($cardId)) {
    http_response_code(404);
    die('Card not found');
}

// Debug: Log the card ID being searched
error_log("Looking for card ID: " . $cardId);

try {
    // Fetch card data
    $stmt = $pdo->prepare("
        SELECT c.*, cf.field_name, cf.field_value 
        FROM cards c 
        LEFT JOIN card_fields cf ON c.id = cf.card_id 
        WHERE c.unique_id = ?
    ");
    $stmt->execute([$cardId]);
    
    $cardData = null;
    $fields = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$cardData) {
            $cardData = $row;
        }
        if ($row['field_name']) {
            $fields[$row['field_name']] = $row['field_value'];
        }
    }
    
    if (!$cardData) {
        // Debug: Show what cards are available
        $debugStmt = $pdo->query("SELECT unique_id FROM cards ORDER BY created_at DESC LIMIT 10");
        $availableCards = $debugStmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Available card IDs: " . implode(', ', $availableCards));
        
        http_response_code(404);
        die('Card not found. Available cards: ' . implode(', ', $availableCards));
    }
    
    $cardInfo = json_decode($cardData['card_data'], true);
    
    // Check if this is a combo card and find the related card
    $comboCards = [];
    $isComboCard = false;
    
    // Check if this is a combo card by finding all cards with the same unique_id
    $currentUniqueId = $cardData['unique_id'];
    
    // Find all cards with the same unique_id, ordered by card type priority
    $allCardsStmt = $pdo->prepare("
        SELECT c.*, cf.field_name, cf.field_value 
        FROM cards c 
        LEFT JOIN card_fields cf ON c.id = cf.card_id 
        WHERE c.unique_id = ?
        ORDER BY 
            CASE c.card_type 
                WHEN 'blue_dog' THEN 1
                WHEN 'red_dog' THEN 1
                WHEN 'emotional_dog' THEN 1
                WHEN 'blue_cat' THEN 1
                WHEN 'service_dog_handler' THEN 2
                WHEN 'service_dog_handler_red' THEN 2
                WHEN 'emotional_support_dog' THEN 2
                WHEN 'emotional_cat_handler' THEN 2
                ELSE 3
            END,
            c.id
    ");
    $allCardsStmt->execute([$currentUniqueId]);
    
    $allCards = [];
    $cardFields = [];
    
    while ($row = $allCardsStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($allCards[$row['id']])) {
            $allCards[$row['id']] = $row;
            $cardFields[$row['id']] = [];
        }
        if ($row['field_name']) {
            $cardFields[$row['id']][$row['field_name']] = $row['field_value'];
        }
    }
    
    
    // If there are multiple cards with the same unique_id, it's a combo
    if (count($allCards) > 1) {
        $isComboCard = true;
        $comboCards = [];
        
        foreach ($allCards as $cardId => $cardInfo) {
            $comboCards[] = [
                'data' => $cardInfo,
                'fields' => $cardFields[$cardId]
            ];
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error retrieving card: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual ID Card - <?php echo htmlspecialchars($cardData['unique_id']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            padding-top: 20px;
        }
        
        .card-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
        }
        
        .card-header {
            background: #2c5aa0;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .card-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card-content {
            padding: 22px 40px 40px 40px;
        }
        
        .card-images {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .card-image {
            flex: 1;
            min-width: 300px;
            text-align: center;
        }
        
        .card-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .card-image h3 {
            margin: 15px 0 10px;
            color: #333;
            font-size: 1.2rem;
        }
        
        .card-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
        }
        
        .card-details h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.8rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .field-group {
            margin-bottom: 20px;
        }
        
        .field-label {
            font-weight: 600;
            color: #555;
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: block;
        }
        
        .field-value {
            color: #333;
            font-size: 1rem;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .card-info {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .card-info h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px solid #bbdefb;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .card-images {
                flex-direction: column;
            }
            
            .card-image {
                min-width: auto;
            }
            
            .card-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="card-container">
        <div class="card-header">
            <h1><i class="fas fa-id-card"></i> Virtual ID Card</h1>
            <p>Digital Identity Verification</p>
        </div>
        
        <div class="card-content">
            <?php if ($isComboCard && !empty($comboCards)): ?>
                <!-- Combo Card Display -->
                <div class="combo-notice" style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin: 12px 0 20px 0; text-align: center;">
                    <h3 style="color: #1976d2; margin-bottom: 10px;">📋 Combo Card Set</h3>
                </div>
                
                <?php foreach ($comboCards as $cardKey => $cardInfo): ?>
                    <div class="combo-card-section" style="margin: 30px 0; border: 2px solid #e9ecef; border-radius: 15px; overflow: hidden;">
                        <div style="padding: 30px;">
            <div class="card-images">
                <div class="card-image">
                    <h3>Front Side</h3>
                                    <?php 
                                    $frontPath = $cardInfo['data']['front_image_path'];
                                    $backPath = $cardInfo['data']['back_image_path'];
                                    
                                    // Debug: Log the image paths
                                    error_log("Front image path: " . $frontPath);
                                    error_log("Back image path: " . $backPath);
                                    
                                    // Check if file exists
                                    $frontExists = file_exists($frontPath);
                                    $backExists = file_exists($backPath);
                                    
                                    error_log("Front image exists: " . ($frontExists ? 'YES' : 'NO'));
                                    error_log("Back image exists: " . ($backExists ? 'YES' : 'NO'));
                                    ?>
                                    
                                    <?php if ($frontExists): ?>
                                        <img src="<?php echo htmlspecialchars($frontPath); ?>" alt="Card Front" style="max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d; display: none;">
                                            <p>Front image failed to load</p>
                                            <small>Path: <?php echo htmlspecialchars($frontPath); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d;">
                                            <p>Front image not found</p>
                                            <small>Path: <?php echo htmlspecialchars($frontPath); ?></small>
                                        </div>
                                    <?php endif; ?>
                </div>
                <div class="card-image">
                    <h3>Back Side</h3>
                                    <?php if ($backExists): ?>
                                        <img src="<?php echo htmlspecialchars($backPath); ?>" alt="Card Back" style="max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d; display: none;">
                                            <p>Back image failed to load</p>
                                            <small>Path: <?php echo htmlspecialchars($backPath); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d;">
                                            <p>Back image not found</p>
                                            <small>Path: <?php echo htmlspecialchars($backPath); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <!-- Single Card Display -->
                <div class="card-images">
                    <div class="card-image">
                        <h3>Front Side</h3>
                        <?php 
                        $frontPath = $cardData['front_image_path'];
                        $backPath = $cardData['back_image_path'];
                        
                        // Debug: Log the image paths
                        error_log("Single card - Front image path: " . $frontPath);
                        error_log("Single card - Back image path: " . $backPath);
                        
                        // Check if file exists
                        $frontExists = file_exists($frontPath);
                        $backExists = file_exists($backPath);
                        
                        error_log("Single card - Front image exists: " . ($frontExists ? 'YES' : 'NO'));
                        error_log("Single card - Back image exists: " . ($backExists ? 'YES' : 'NO'));
                        ?>
                        
                        <?php if ($frontExists): ?>
                            <img src="<?php echo htmlspecialchars($frontPath); ?>" alt="Card Front" style="max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d; display: none;">
                                <p>Front image failed to load</p>
                                <small>Path: <?php echo htmlspecialchars($frontPath); ?></small>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d;">
                                <p>Front image not found</p>
                                <small>Path: <?php echo htmlspecialchars($frontPath); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-image">
                        <h3>Back Side</h3>
                        <?php if ($backExists): ?>
                            <img src="<?php echo htmlspecialchars($backPath); ?>" alt="Card Back" style="max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d; display: none;">
                                <p>Back image failed to load</p>
                                <small>Path: <?php echo htmlspecialchars($backPath); ?></small>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; color: #6c757d;">
                                <p>Back image not found</p>
                                <small>Path: <?php echo htmlspecialchars($backPath); ?></small>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-info">
                <h3>Card Details</h3>
                <div class="info-item">
                    <span class="info-label">Card Type:</span>
                    <span class="info-value"><?php 
                        $cardType = $cardData['card_type'];
                        
                        // Map card types to dropdown names
                        $cardNameMap = [
                            'blue_dog' => 'Service Dog (Blue)',
                            'red_dog' => 'Service Dog (Red)',
                            'emotional_dog' => 'Emotional Dog',
                            'blue_cat' => 'Emotional Support Cat',
                            'service_dog_handler' => 'Service Dog Handler',
                            'service_dog_handler_red' => 'Service Dog Handler',
                            'emotional_support_dog' => 'Emotional Support Dog',
                            'emotional_cat_handler' => 'Cat Handler',
                            'child_identification' => 'Child Identification Card (Blue)',
                            'child_identification_red' => 'Child Identification Card (Red)',
                            'autism_card_infinity' => 'Autism Card (Infinity Sign)',
                            'autism_card_puzzle' => 'Autism Card (Puzzle Piece)',
                            'emergency_id_card' => 'Emergency ID Card'
                        ];
                        
                        $displayName = isset($cardNameMap[$cardType]) ? $cardNameMap[$cardType] : ucwords(str_replace('_', ' ', $cardType));
                        echo htmlspecialchars($displayName);
                    ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Unique ID:</span>
                    <span class="info-value"><?php 
                        $displayId = $cardData['unique_id'];
                        // Remove suffixes like _blue, _handler, _red
                        $displayId = preg_replace('/_(blue|handler|red)$/', '', $displayId);
                        echo htmlspecialchars($displayId);
                    ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created:</span>
                    <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($cardData['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">Active</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

