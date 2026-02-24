<?php
/**
 * Dental Charting Application
 * Interactive tooth chart with patient management
 */

require_once 'config.php';

$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_patients':
            $search = $_POST['search'] ?? '';
            $stmt = $db->prepare("SELECT id, first_name, last_name, date_of_birth, phone 
                                  FROM patients 
                                  WHERE first_name LIKE ? OR last_name LIKE ? 
                                  ORDER BY last_name, first_name LIMIT 50");
            $searchTerm = "%$search%";
            $stmt->execute([$searchTerm, $searchTerm]);
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_patient':
            $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$_POST['patient_id']]);
            echo json_encode($stmt->fetch());
            exit;
            
        case 'save_patient':
            if (empty($_POST['patient_id'])) {
                $stmt = $db->prepare("INSERT INTO patients (first_name, last_name, date_of_birth, phone, email, insurance_provider, insurance_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['date_of_birth'] ?: null,
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['insurance_provider'],
                    $_POST['insurance_id']
                ]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            } else {
                $stmt = $db->prepare("UPDATE patients SET first_name=?, last_name=?, date_of_birth=?, phone=?, email=?, insurance_provider=?, insurance_id=? WHERE id=?");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['date_of_birth'] ?: null,
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['insurance_provider'],
                    $_POST['insurance_id'],
                    $_POST['patient_id']
                ]);
                echo json_encode(['success' => true]);
            }
            exit;
            
        case 'get_tooth_records':
            $stmt = $db->prepare("SELECT tr.*, c.name as condition_name, c.color, c.code 
                                  FROM tooth_records tr 
                                  LEFT JOIN conditions c ON tr.condition_id = c.id 
                                  WHERE tr.patient_id = ? 
                                  ORDER BY tr.tooth_number, tr.recorded_at DESC");
            $stmt->execute([$_POST['patient_id']]);
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'save_tooth_condition':
            // Check if record exists
            $stmt = $db->prepare("SELECT id FROM tooth_records WHERE patient_id = ? AND tooth_number = ?");
            $stmt->execute([$_POST['patient_id'], $_POST['tooth_number']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $db->prepare("UPDATE tooth_records SET condition_id = ?, surface = ?, notes = ?, recorded_by = ?, recorded_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $_POST['condition_id'],
                    $_POST['surface'] ?? null,
                    $_POST['notes'] ?? null,
                    $_POST['recorded_by'] ?? 'Staff',
                    $existing['id']
                ]);
            } else {
                $stmt = $db->prepare("INSERT INTO tooth_records (patient_id, tooth_number, condition_id, surface, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['patient_id'],
                    $_POST['tooth_number'],
                    $_POST['condition_id'],
                    $_POST['surface'] ?? null,
                    $_POST['notes'] ?? null,
                    $_POST['recorded_by'] ?? 'Staff'
                ]);
            }
            
            // Log to history
            $stmt = $db->prepare("INSERT INTO tooth_history (patient_id, tooth_number, action, new_value, performed_by) VALUES (?, ?, 'condition_updated', ?, ?)");
            $stmt->execute([
                $_POST['patient_id'],
                $_POST['tooth_number'],
                $_POST['condition_id'],
                $_POST['recorded_by'] ?? 'Staff'
            ]);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_conditions':
            $stmt = $db->query("SELECT * FROM conditions ORDER BY name");
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_procedures':
            $stmt = $db->query("SELECT * FROM procedures ORDER BY category, name");
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_treatment_plans':
            $stmt = $db->prepare("SELECT tp.*, 
                                  (SELECT COUNT(*) FROM treatment_plan_items WHERE treatment_plan_id = tp.id) as item_count
                                  FROM treatment_plans tp 
                                  WHERE tp.patient_id = ? 
                                  ORDER BY tp.created_at DESC");
            $stmt->execute([$_POST['patient_id']]);
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'save_treatment_plan':
            $stmt = $db->prepare("INSERT INTO treatment_plans (patient_id, name, status, notes) VALUES (?, ?, 'proposed', ?)");
            $stmt->execute([
                $_POST['patient_id'],
                $_POST['name'],
                $_POST['notes'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
            
        case 'add_treatment_item':
            $stmt = $db->prepare("INSERT INTO treatment_plan_items (treatment_plan_id, tooth_number, procedure_id, surface, cost, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['treatment_plan_id'],
                $_POST['tooth_number'] ?: null,
                $_POST['procedure_id'],
                $_POST['surface'] ?? null,
                $_POST['cost'],
                $_POST['notes'] ?? null
            ]);
            
            // Update total cost
            $stmt = $db->prepare("UPDATE treatment_plans SET total_cost = (SELECT SUM(cost) FROM treatment_plan_items WHERE treatment_plan_id = ?) WHERE id = ?");
            $stmt->execute([$_POST['treatment_plan_id'], $_POST['treatment_plan_id']]);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_treatment_items':
            $stmt = $db->prepare("SELECT tpi.*, p.name as procedure_name, p.code as procedure_code 
                                  FROM treatment_plan_items tpi 
                                  LEFT JOIN procedures p ON tpi.procedure_id = p.id 
                                  WHERE tpi.treatment_plan_id = ?");
            $stmt->execute([$_POST['treatment_plan_id']]);
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_tooth_history':
            $stmt = $db->prepare("SELECT * FROM tooth_history WHERE patient_id = ? AND tooth_number = ? ORDER BY performed_at DESC LIMIT 20");
            $stmt->execute([$_POST['patient_id'], $_POST['tooth_number']]);
            echo json_encode($stmt->fetchAll());
            exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Get conditions for initial load
$conditions = $db->query("SELECT * FROM conditions ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Chart - Patient Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-lg);
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header h1 svg {
            width: 32px;
            height: 32px;
        }
        
        .patient-selector {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .patient-selector input {
            padding: 10px 16px;
            border: none;
            border-radius: var(--radius);
            font-size: 0.95rem;
            width: 280px;
            background: rgba(255,255,255,0.95);
        }
        
        .patient-selector input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: white;
            color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--gray-100);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Main Layout */
        .main-container {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            padding: 24px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* Chart Section */
        .chart-section {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .chart-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chart-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .notation-toggle {
            display: flex;
            background: var(--gray-100);
            border-radius: var(--radius);
            padding: 4px;
        }
        
        .notation-toggle button {
            padding: 6px 14px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            color: var(--gray-600);
            transition: all 0.2s;
        }
        
        .notation-toggle button.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow);
        }
        
        .chart-body {
            padding: 30px;
        }
        
        /* Tooth Chart */
        .tooth-chart {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        
        .arch {
            text-align: center;
        }
        
        .arch-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }
        
        .teeth-row {
            display: flex;
            justify-content: center;
            gap: 4px;
        }
        
        .tooth {
            width: 44px;
            height: 54px;
            border: 2px solid var(--gray-300);
            border-radius: 8px 8px 12px 12px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
        }
        
        .tooth.lower {
            border-radius: 12px 12px 8px 8px;
        }
        
        .tooth:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .tooth.selected {
            border-color: var(--primary);
            border-width: 3px;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        
        .tooth-number {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-700);
        }
        
        .tooth-condition {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-top: 4px;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .tooth-type {
            font-size: 0.6rem;
            color: var(--gray-400);
            position: absolute;
            bottom: 3px;
        }
        
        /* Quadrant labels */
        .quadrant-labels {
            display: flex;
            justify-content: center;
            gap: 200px;
            margin-top: 8px;
            font-size: 0.75rem;
            color: var(--gray-400);
        }
        
        /* Legend */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px 24px;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Patient Info */
        .patient-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .patient-info .name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .patient-info .detail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .patient-info .detail svg {
            width: 16px;
            height: 16px;
            color: var(--gray-400);
        }
        
        /* Tooth Details */
        .tooth-details {
            display: none;
        }
        
        .tooth-details.active {
            display: block;
        }
        
        .selected-tooth-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .selected-tooth-num {
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .selected-tooth-info h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .selected-tooth-info p {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Condition Buttons */
        .condition-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .condition-btn {
            padding: 10px 8px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .condition-btn:hover {
            border-color: var(--gray-400);
        }
        
        .condition-btn.selected {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .condition-btn .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin: 0 auto 4px;
        }
        
        .condition-btn .label {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        /* Surface selector */
        .surface-selector {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-bottom: 16px;
        }
        
        .surface-btn {
            width: 36px;
            height: 36px;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius);
            background: white;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-600);
            transition: all 0.2s;
        }
        
        .surface-btn:hover {
            border-color: var(--gray-400);
        }
        
        .surface-btn.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--gray-100);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Patient search results */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 100;
        }
        
        .search-results.active {
            display: block;
        }
        
        .search-result-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        
        .search-result-item:hover {
            background: var(--gray-50);
        }
        
        .search-result-item .name {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .search-result-item .info {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1rem;
            margin-bottom: 8px;
            color: var(--gray-700);
        }
        
        /* Print styles */
        @media print {
            .header, .sidebar, .notation-toggle, .chart-legend { display: none !important; }
            .main-container { display: block; }
            .chart-section { box-shadow: none; }
        }
    </style>
</head>
<body>

<header class="header">
    <h1>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2C8 2 6 5 6 8c0 2 1 4 1 6s-1 6 2 6c2 0 2-3 3-3s1 3 3 3c3 0 2-4 2-6s1-4 1-6c0-3-2-6-6-6z"/>
        </svg>
        Dental Chart
    </h1>
    <div class="patient-selector" style="position: relative;">
        <input type="text" id="patientSearch" placeholder="Search patients..." autocomplete="off">
        <div class="search-results" id="searchResults"></div>
        <button class="btn btn-primary" onclick="openPatientModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            New Patient
        </button>
    </div>
</header>

<div class="main-container">
    <!-- Chart Section -->
    <div class="chart-section">
        <div class="chart-header">
            <h2>
                <span id="chartPatientName">Select a Patient</span>
            </h2>
            <div class="notation-toggle">
                <button class="active" data-notation="universal">Universal</button>
                <button data-notation="palmer">Palmer</button>
            </div>
        </div>
        
        <div class="chart-body">
            <div class="tooth-chart">
                <!-- Upper Arch -->
                <div class="arch upper-arch">
                    <div class="arch-label">Upper Arch (Maxillary)</div>
                    <div class="teeth-row" id="upperTeeth">
                        <!-- Teeth 1-16 will be generated -->
                    </div>
                    <div class="quadrant-labels">
                        <span>Upper Right</span>
                        <span>Upper Left</span>
                    </div>
                </div>
                
                <!-- Lower Arch -->
                <div class="arch lower-arch">
                    <div class="teeth-row" id="lowerTeeth">
                        <!-- Teeth 17-32 will be generated -->
                    </div>
                    <div class="quadrant-labels">
                        <span>Lower Left</span>
                        <span>Lower Right</span>
                    </div>
                    <div class="arch-label" style="margin-top: 16px;">Lower Arch (Mandibular)</div>
                </div>
            </div>
        </div>
        
        <div class="chart-legend" id="chartLegend">
            <!-- Legend items will be generated -->
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Patient Info Card -->
        <div class="card" id="patientCard" style="display: none;">
            <div class="card-header">
                Patient Information
                <button class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="openPatientModal(currentPatient)">Edit</button>
            </div>
            <div class="card-body">
                <div class="patient-info" id="patientInfo">
                    <!-- Patient info will be populated -->
                </div>
            </div>
        </div>
        
        <!-- Tooth Details Card -->
        <div class="card tooth-details" id="toothDetails">
            <div class="card-header">Tooth Details</div>
            <div class="card-body">
                <div class="selected-tooth-header">
                    <div class="selected-tooth-num" id="selectedToothNum">-</div>
                    <div class="selected-tooth-info">
                        <h3 id="selectedToothName">Select a tooth</h3>
                        <p id="selectedToothType">Click on any tooth to view details</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Condition</label>
                    <div class="condition-grid" id="conditionGrid">
                        <!-- Condition buttons will be generated -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Surface (if applicable)</label>
                    <div class="surface-selector">
                        <button class="surface-btn" data-surface="M" title="Mesial">M</button>
                        <button class="surface-btn" data-surface="O" title="Occlusal">O</button>
                        <button class="surface-btn" data-surface="D" title="Distal">D</button>
                        <button class="surface-btn" data-surface="B" title="Buccal">B</button>
                        <button class="surface-btn" data-surface="L" title="Lingual">L</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="toothNotes" placeholder="Add notes about this tooth..."></textarea>
                </div>
                
                <button class="btn btn-success" style="width: 100%;" onclick="saveToothCondition()">
                    Save Changes
                </button>
            </div>
        </div>
        
        <!-- No Patient Selected -->
        <div class="card" id="noPatientCard">
            <div class="card-body">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <h3>No Patient Selected</h3>
                    <p>Search for an existing patient or create a new one to begin charting.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Patient Modal -->
<div class="modal-overlay" id="patientModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="patientModalTitle">New Patient</h3>
            <button class="modal-close" onclick="closePatientModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="patientForm">
                <input type="hidden" id="patientId" name="patient_id">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" id="dateOfBirth" name="date_of_birth">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Insurance Provider</label>
                        <input type="text" id="insuranceProvider" name="insurance_provider">
                    </div>
                    <div class="form-group">
                        <label>Insurance ID</label>
                        <input type="text" id="insuranceId" name="insurance_id">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closePatientModal()">Cancel</button>
            <button class="btn btn-success" onclick="savePatient()">Save Patient</button>
        </div>
    </div>
</div>

<script>
// Tooth data
const toothNames = {
    1: 'Upper Right Third Molar', 2: 'Upper Right Second Molar', 3: 'Upper Right First Molar',
    4: 'Upper Right Second Premolar', 5: 'Upper Right First Premolar', 6: 'Upper Right Canine',
    7: 'Upper Right Lateral Incisor', 8: 'Upper Right Central Incisor',
    9: 'Upper Left Central Incisor', 10: 'Upper Left Lateral Incisor', 11: 'Upper Left Canine',
    12: 'Upper Left First Premolar', 13: 'Upper Left Second Premolar', 14: 'Upper Left First Molar',
    15: 'Upper Left Second Molar', 16: 'Upper Left Third Molar',
    17: 'Lower Left Third Molar', 18: 'Lower Left Second Molar', 19: 'Lower Left First Molar',
    20: 'Lower Left Second Premolar', 21: 'Lower Left First Premolar', 22: 'Lower Left Canine',
    23: 'Lower Left Lateral Incisor', 24: 'Lower Left Central Incisor',
    25: 'Lower Right Central Incisor', 26: 'Lower Right Lateral Incisor', 27: 'Lower Right Canine',
    28: 'Lower Right First Premolar', 29: 'Lower Right Second Premolar', 30: 'Lower Right First Molar',
    31: 'Lower Right Second Molar', 32: 'Lower Right Third Molar'
};

const toothTypes = {
    1: 'M', 2: 'M', 3: 'M', 4: 'PM', 5: 'PM', 6: 'C', 7: 'I', 8: 'I',
    9: 'I', 10: 'I', 11: 'C', 12: 'PM', 13: 'PM', 14: 'M', 15: 'M', 16: 'M',
    17: 'M', 18: 'M', 19: 'M', 20: 'PM', 21: 'PM', 22: 'C', 23: 'I', 24: 'I',
    25: 'I', 26: 'I', 27: 'C', 28: 'PM', 29: 'PM', 30: 'M', 31: 'M', 32: 'M'
};

// Conditions from PHP
const conditions = <?php echo json_encode($conditions); ?>;

// State
let currentPatient = null;
let selectedTooth = null;
let selectedCondition = null;
let selectedSurfaces = [];
let toothRecords = {};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    generateTeeth();
    generateLegend();
    generateConditionButtons();
    setupSearch();
    setupNotationToggle();
    setupSurfaceButtons();
});

function generateTeeth() {
    const upperTeeth = document.getElementById('upperTeeth');
    const lowerTeeth = document.getElementById('lowerTeeth');
    
    // Upper teeth (1-16)
    for (let i = 1; i <= 16; i++) {
        upperTeeth.appendChild(createToothElement(i));
    }
    
    // Lower teeth (17-32) - note: displayed 32 to 17 visually
    for (let i = 32; i >= 17; i--) {
        lowerTeeth.appendChild(createToothElement(i, true));
    }
}

function createToothElement(num, isLower = false) {
    const tooth = document.createElement('div');
    tooth.className = 'tooth' + (isLower ? ' lower' : '');
    tooth.dataset.tooth = num;
    tooth.innerHTML = `
        <span class="tooth-number">${num}</span>
        <div class="tooth-condition" style="display: none;"></div>
        <span class="tooth-type">${toothTypes[num]}</span>
    `;
    tooth.addEventListener('click', () => selectTooth(num));
    return tooth;
}

function generateLegend() {
    const legend = document.getElementById('chartLegend');
    conditions.forEach(cond => {
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.innerHTML = `
            <div class="legend-color" style="background: ${cond.color};"></div>
            <span>${cond.name}</span>
        `;
        legend.appendChild(item);
    });
}

function generateConditionButtons() {
    const grid = document.getElementById('conditionGrid');
    conditions.forEach(cond => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'condition-btn';
        btn.dataset.condition = cond.id;
        btn.innerHTML = `
            <div class="dot" style="background: ${cond.color};"></div>
            <div class="label">${cond.name}</div>
        `;
        btn.addEventListener('click', () => selectCondition(cond.id));
        grid.appendChild(btn);
    });
}

function setupSearch() {
    const input = document.getElementById('patientSearch');
    const results = document.getElementById('searchResults');
    let timeout;
    
    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            results.classList.remove('active');
            return;
        }
        
        timeout = setTimeout(() => {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_patients&search=${encodeURIComponent(query)}`
            })
            .then(r => r.json())
            .then(patients => {
                if (patients.length === 0) {
                    results.innerHTML = '<div class="search-result-item"><span class="info">No patients found</span></div>';
                } else {
                    results.innerHTML = patients.map(p => `
                        <div class="search-result-item" onclick="loadPatient(${p.id})">
                            <div class="name">${p.last_name}, ${p.first_name}</div>
                            <div class="info">${p.date_of_birth || ''} ${p.phone ? '• ' + p.phone : ''}</div>
                        </div>
                    `).join('');
                }
                results.classList.add('active');
            });
        }, 300);
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.patient-selector')) {
            results.classList.remove('active');
        }
    });
}

function setupNotationToggle() {
    document.querySelectorAll('.notation-toggle button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.notation-toggle button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateNotation(this.dataset.notation);
        });
    });
}

function updateNotation(type) {
    document.querySelectorAll('.tooth').forEach(tooth => {
        const num = parseInt(tooth.dataset.tooth);
        const numSpan = tooth.querySelector('.tooth-number');
        
        if (type === 'palmer') {
            // Palmer notation
            let palmerNum;
            if (num <= 8) palmerNum = 9 - num;
            else if (num <= 16) palmerNum = num - 8;
            else if (num <= 24) palmerNum = 25 - num;
            else palmerNum = num - 24;
            numSpan.textContent = palmerNum;
        } else {
            numSpan.textContent = num;
        }
    });
}

function setupSurfaceButtons() {
    document.querySelectorAll('.surface-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.toggle('selected');
            const surface = this.dataset.surface;
            if (selectedSurfaces.includes(surface)) {
                selectedSurfaces = selectedSurfaces.filter(s => s !== surface);
            } else {
                selectedSurfaces.push(surface);
            }
        });
    });
}

function loadPatient(id) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_patient&patient_id=${id}`
    })
    .then(r => r.json())
    .then(patient => {
        currentPatient = patient;
        document.getElementById('searchResults').classList.remove('active');
        document.getElementById('patientSearch').value = '';
        document.getElementById('chartPatientName').textContent = `${patient.first_name} ${patient.last_name}`;
        
        // Show patient card
        document.getElementById('noPatientCard').style.display = 'none';
        document.getElementById('patientCard').style.display = 'block';
        
        // Populate patient info
        document.getElementById('patientInfo').innerHTML = `
            <div class="name">${patient.first_name} ${patient.last_name}</div>
            ${patient.date_of_birth ? `<div class="detail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>${patient.date_of_birth}</div>` : ''}
            ${patient.phone ? `<div class="detail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>${patient.phone}</div>` : ''}
            ${patient.email ? `<div class="detail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>${patient.email}</div>` : ''}
            ${patient.insurance_provider ? `<div class="detail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>${patient.insurance_provider} ${patient.insurance_id ? '(' + patient.insurance_id + ')' : ''}</div>` : ''}
        `;
        
        // Load tooth records
        loadToothRecords(id);
    });
}

function loadToothRecords(patientId) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_tooth_records&patient_id=${patientId}`
    })
    .then(r => r.json())
    .then(records => {
        // Reset all teeth
        toothRecords = {};
        document.querySelectorAll('.tooth').forEach(tooth => {
            const conditionDot = tooth.querySelector('.tooth-condition');
            conditionDot.style.display = 'none';
            conditionDot.style.background = '';
        });
        
        // Apply records
        records.forEach(record => {
            toothRecords[record.tooth_number] = record;
            const tooth = document.querySelector(`.tooth[data-tooth="${record.tooth_number}"]`);
            if (tooth && record.color) {
                const conditionDot = tooth.querySelector('.tooth-condition');
                conditionDot.style.display = 'block';
                conditionDot.style.background = record.color;
            }
        });
    });
}

function selectTooth(num) {
    // Update selection
    document.querySelectorAll('.tooth').forEach(t => t.classList.remove('selected'));
    document.querySelector(`.tooth[data-tooth="${num}"]`).classList.add('selected');
    
    selectedTooth = num;
    selectedCondition = null;
    selectedSurfaces = [];
    
    // Show tooth details panel
    document.getElementById('toothDetails').classList.add('active');
    document.getElementById('selectedToothNum').textContent = num;
    document.getElementById('selectedToothName').textContent = toothNames[num];
    document.getElementById('selectedToothType').textContent = getToothTypeFull(toothTypes[num]);
    
    // Reset form
    document.querySelectorAll('.condition-btn').forEach(b => b.classList.remove('selected'));
    document.querySelectorAll('.surface-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('toothNotes').value = '';
    
    // Load existing data if any
    if (toothRecords[num]) {
        const record = toothRecords[num];
        if (record.condition_id) {
            selectCondition(record.condition_id);
        }
        if (record.surface) {
            record.surface.split('').forEach(s => {
                const btn = document.querySelector(`.surface-btn[data-surface="${s}"]`);
                if (btn) {
                    btn.classList.add('selected');
                    selectedSurfaces.push(s);
                }
            });
        }
        if (record.notes) {
            document.getElementById('toothNotes').value = record.notes;
        }
    }
}

function getToothTypeFull(type) {
    const types = { 'M': 'Molar', 'PM': 'Premolar', 'C': 'Canine', 'I': 'Incisor' };
    return types[type] || type;
}

function selectCondition(id) {
    selectedCondition = id;
    document.querySelectorAll('.condition-btn').forEach(b => {
        b.classList.toggle('selected', b.dataset.condition == id);
    });
}

function saveToothCondition() {
    if (!currentPatient || !selectedTooth) {
        alert('Please select a patient and tooth first.');
        return;
    }
    
    const data = new URLSearchParams({
        action: 'save_tooth_condition',
        patient_id: currentPatient.id,
        tooth_number: selectedTooth,
        condition_id: selectedCondition || '',
        surface: selectedSurfaces.join(''),
        notes: document.getElementById('toothNotes').value,
        recorded_by: 'Staff'
    });
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            loadToothRecords(currentPatient.id);
            // Visual feedback
            const tooth = document.querySelector(`.tooth[data-tooth="${selectedTooth}"]`);
            tooth.style.animation = 'pulse 0.3s';
            setTimeout(() => tooth.style.animation = '', 300);
        }
    });
}

function openPatientModal(patient = null) {
    const modal = document.getElementById('patientModal');
    const title = document.getElementById('patientModalTitle');
    const form = document.getElementById('patientForm');
    
    form.reset();
    
    if (patient) {
        title.textContent = 'Edit Patient';
        document.getElementById('patientId').value = patient.id;
        document.getElementById('firstName').value = patient.first_name;
        document.getElementById('lastName').value = patient.last_name;
        document.getElementById('dateOfBirth').value = patient.date_of_birth || '';
        document.getElementById('phone').value = patient.phone || '';
        document.getElementById('email').value = patient.email || '';
        document.getElementById('insuranceProvider').value = patient.insurance_provider || '';
        document.getElementById('insuranceId').value = patient.insurance_id || '';
    } else {
        title.textContent = 'New Patient';
        document.getElementById('patientId').value = '';
    }
    
    modal.classList.add('active');
}

function closePatientModal() {
    document.getElementById('patientModal').classList.remove('active');
}

function savePatient() {
    const form = document.getElementById('patientForm');
    const formData = new FormData(form);
    formData.append('action', 'save_patient');
    
    fetch('', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closePatientModal();
            const id = result.id || document.getElementById('patientId').value;
            loadPatient(id);
        }
    });
}

// Close modal on overlay click
document.getElementById('patientModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientModal();
});
</script>

</body>
</html>
