<?php
// Bật hiển thị lỗi để dễ kiểm tra nếu có sự cố
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Đường dẫn file lưu dữ liệu trên server
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
    header('Access-Control-Allow-Origin: *');

    if (isset($_GET['id_devici']) && !empty(trim($_GET['id_devici']))) {
        $device_id = trim($_GET['id_devici']);
        
        // Luôn tự động thêm mới nếu chưa có, hoặc cập nhật thời gian nếu đã có
        $active_devices[$device_id] = time(); 
        
        // Lưu lại vào file JSON
        file_put_contents($storage_file, json_encode($active_devices, JSON_PRETTY_PRINT));
        
        echo json_encode(["status" => "success", "message" => "Da ghi nhan may " . $device_id]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thieu id_devici"]);
    }
    exit;
}

// =========================================================================
// CHỨC NĂNG 2: PHÂN LOẠI ON/OFF VÀ HIỂN THỊ GIAO DIỆN
// =========================================================================
$now = time();
$online_count = 0;
$on_list = [];
$off_list = [];

// Thiết lập thời gian chờ: Quá 11 phút (660 giây) không gửi tín hiệu -> Coi như OFF
$max_wait_time = 660; 

foreach ($active_devices as $id => $last_active) {
    $time_passed = $now - $last_active;

    if ($time_passed < $max_wait_time) { 
        // 🟢 Máy đang ON
        $online_count++;
        $time_left = $max_wait_time - $time_passed;
        $minutes_left = floor($time_left / 60);
        
        $on_list[] = [
            "id" => htmlspecialchars($id),
            "info" => $minutes_left . " phút nữa OFF"
        ];
    } else {
        // 🔴 Máy đã OFF (Giữ lại ID chứ không xóa nữa)
        // Tính xem họ đã off cách đây bao lâu
        $time_off = $time_passed - $max_wait_time;
        if ($time_off < 60) {
            $off_info = "Vừa mới OFF";
        } elseif ($time_off < 3600) {
            $off_info = "OFF " . floor($time_off / 60) . " phút trước";
        } else {
            $off_info = "OFF từ " . date("H:i - d/m", $last_active);
        }

        $off_list[] = [
            "id" => htmlspecialchars($id),
            "info" => $off_info
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Thiết bị</title>
    <meta http-equiv="refresh" content="15"> <!-- Tự động F5 sau mỗi 15 giây -->
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding: 40px 20px; background: #f0f3f5; margin: 0; }
        .card { background: white; padding: 35px; display: inline-block; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 650px; width: 100%; box-sizing: border-box; }
        h2 { color: #34495e; font-size: 20px; margin-top: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        h1 { color: #2ecc71; font-size: 64px; margin: 5px 0; font-weight: bold; }
        
        /* Chia cột danh sách */
        .grid-container { display: flex; gap: 20px; margin-top: 30px; text-align: left; }
        .column { flex: 1; background: #f8f9fa; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .col-title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 2px solid #edf2f7; }
        .col-title.on { color: #2ecc71; }
        .col-title.off { color: #95a5a6; }
        
        .device-list { max-height: 250px; overflow-y: auto; }
        .device-row { display: flex; justify-content: space-between; background: white; padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; border: 1px solid #edf2f7; font-size: 13.5px; align-items: center; }
        
        .on-id { font-weight: 600; color: #1d9bf0; }
        .on-info { color: #e74c3c; font-size: 12px; font-style: italic; }
        
        .off-id { font-weight: 600; color: #7f8c8d; text-decoration: line-through; }
        .off-info { color: #bdc3c7; font-size: 12px; }
        
        .no-device { color: #95a5a6; font-size: 13px; font-style: italic; text-align: center; display: block; padding: 10px 0; }
        .footer-note { color: #bdc3c7; font-size: 11px; margin-top: 30px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Số máy đang hoạt động</h2>
        <h1><?php echo $online_count; ?></h1>
        
        <div class="grid-container">
            <!-- CỘT MÁY ĐANG ONLINE -->
            <div class="column">
                <div class="col-title on">🟢 Đang chạy (ON)</div>
                <div class="device-list">
                    <?php if (!empty($on_list)): ?>
                        <?php foreach ($on_list as $device): ?>
                            <div class="device-row">
                                <span class="on-id">● <?php echo $device['id']; ?></span>
                                <span class="on-info"><?php echo $device['info']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="no-device">Không có máy nào đang bật</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- CỘT MÁY ĐÃ OFFLINE -->
            <div class="column">
                <div class="col-title off">🔴 Đã tắt (OFF)</div>
                <div class="device-list">
                    <?php if (!empty($off_list)): ?>
                        <?php foreach ($off_list as $device): ?>
                            <div class="device-row">
                                <span class="off-id">● <?php echo $device['id']; ?></span>
                                <span class="off-info"><?php echo $device['info']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="no-device">Chưa có lịch sử máy tắt</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <small class="footer-note">Hệ thống tự động đồng bộ sau mỗi 15 giây</small>
    </div>
</body>
</html>
