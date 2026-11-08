<?php

// Cấu hình kết nối tới RAG service (Python/FastAPI). Giá trị lấy từ app/.env
return [
    'url' => env('RAG_API_URL', 'http://127.0.0.1:8000'),
    'timeout' => (int) env('RAG_API_TIMEOUT', 300), // giây; lần đầu RAG service nạp mô hình embedding có thể mất vài phút

    // Ngưỡng cảnh báo trùng lặp: độ tương đồng cosine lớn hơn giá trị này thì cảnh báo (FR-08, mặc định 0,85).
    // Sau này khi có ma trận đề thì lấy theo maTranDeThi.nguongTrungLap.
    'nguong_trung_lap' => (float) env('NGUONG_TRUNG_LAP', 0.85),
];
