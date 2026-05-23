<?php
// Bật hiển thị lỗi để dễ kiểm tra nếu có sự cố
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Đường dẫn file lưu dữ liệu tạm thời trên server
$storage_file = __DIR__ . '/online_devices.json';

// Đọc dữ liệu cũ lên (nếu chưa có file thì tạo mảng rỗng)
$active_devices = [];
if (file_exists($storage_file)) {
    $active_devices = json_decode(file_get_contents($storage_file), true) ?? [];
}

// Lấy đường dẫn (Route) mà app đang truy cập
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// =========================================================================
// CHỨC NĂNG 1: TIẾP NHẬN BÁO DANH TỪ APP (/online?id_devici=1234)
// =========================================================================
if ($request_uri === '/online') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Cho phép mọi App truy cập không bị lỗi CORS

    // Lấy tham số id_devici từ URL
    if (isset($_GET['id_devici']) && !empty(trim($_GET['id_devici']))) {
        $device_id = trim($_GET['id_devici']);
        
        // Ghi nhận thời gian hiện tại của máy này (tính bằng giây)
        $active_devices[$device_id] = time(); 
        
        // Lưu lại vào file JSON trên server
        if (file_put_contents($storage_file, json_encode($active_devices, JSON_PRETTY_PRINT))) {
            echo json_encode([
                "status" => "success", 
                "message" => "Da ghi nhan may " . $device_id . " online."
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Khong the ghi dữ liệu lên server"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thieu hoac trong id_devici tren URL"]);
    }
    exit;
}

// =========================================================================
// CHỨC NĂNG 2: GIAO DIỆN QUẢN TRỊ TRÊN TRANG CHỦ (Khi bạn vào xem bằng trình duyệt)
// =========================================================================
$now = time();
$online_count = 0;
$active_list = [];
$updated_devices = [];

// Duyệt qua danh sách để lọc các máy đang hoạt động
foreach ($active_devices as $id => $last_active) {
    // Vì App 10 phút (600 giây) mới gửi 1 lần -> Cho phép trễ tối đa 11 phút (660 giây)
    if ($now - $last_active < 660) { 
        $online_count++;
        $active_list[] = htmlspecialchars($id);
        $updated_devices[$id] = $last_active; // Giữ lại những máy còn sống
    }
}

// Cập nhật lại file để tự động xóa những máy đã tắt quá 11 phút
file_put_contents($storage_file, json_encode($updated_devices));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Thiết bị Online</title>
    <!-- Tự động tải lại trang sau mỗi 15 giây để cập nhật số liệu mới nhất -->
    <meta http-equiv="refresh" content="15"> 
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding-top: 60px; background: #f0f3f5; margin: 0; }
        .card { background: white; padding: 40px; display: inline-block; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 450px; width: 90%; box-sizing: border-box; }
        h2 { color: #34495e; font-size: 22px; margin-top: 0; font-weight: 600; }
        h1 { color: #e67e22; font-size: 72px; margin: 10px 0; font-weight: bold; }
        .device-container { text-align: left; margin-top: 25px; }
        .label { font-size: 14px; font-weight: bold; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        .device-list { background: #f8f9fa; padding: 15px; border-radius: 8px; max-height: 180px; overflow-y: auto; border: 1px solid #e2e8f0; margin-top: 8px; }
        .device-tag { display: inline-block; background: #e8f4fd; color: #1d9bf0; padding: 6px 12px; border-radius: 6px; margin: 4px; font-size: 14px; font-weight: 500; border: 1px solid #d2e9fc; }
        .no-device { color: #95a5a6; font-size: 14px; font-style: italic; text-align: center; display: block; padding: 10px 0; }
        .footer-note { color: #bdc3c7; font-size: 12px; margin-top: 25px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>SỐ MÁY ĐANG CHẠY THỰC TẾ</h2>
        <h1><?php echo $online_count; ?></h1>
        
        <div class="device-container">
            <span class="label">Danh sách ID máy đang online:</span>
            <div class="device-list">
                <?php if (!empty($active_list)): ?>
                    <?php foreach ($active_list as $device): ?>
                        <span class="device-tag">● <?php echo $device; ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="no-device">Không có thiết bị nào hoạt động trong 11 phút qua</span>
                <?php endif; ?>
            </div>
        </div>
        
        <small class="footer-note">Giao diện tự động làm mới sau mỗi 15 giây</small>
    </div>
</body>
</html>
