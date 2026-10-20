# Ngân Hàng Đề Thi — Starter Repository

Bộ khung khởi đầu theo ràng buộc công nghệ:
- Web: Laravel (PHP)
- CSDL quan hệ: MySQL 8
- Queue: Laravel Queue (database driver mặc định; có thể đổi Redis sau)
- RAG API: Python + FastAPI
- Embedding: `BAAI/bge-m3`
- Vector DB: Qdrant (`chunk_tai_lieu`, `cau_hoi`)
- Nội dung và metadata chuẩn: MySQL
- LLM: gọi qua API, cấu hình nhà cung cấp/model bằng biến môi trường
- Đầu vào dự kiến: PDF, DOCX, PPTX
- Đầu ra dự kiến: DOCX, PDF

> Đây là **bộ khung ban đầu**, chưa phải ứng dụng hoàn chỉnh. Phần Laravel được tạo dưới dạng hướng dẫn và thư mục đặt mã nguồn; hãy khởi tạo Laravel chính thức ở bước dưới.

## 1. Yêu cầu
- PHP và Composer phù hợp với phiên bản Laravel bạn chọn
- Python 3.11+
- Docker Desktop + Docker Compose
- Git

## 2. Cấu trúc thư mục

```text
NganHangDeThi/
├── app/                         # Nơi đặt Laravel app sau khi khởi tạo
├── rag-service/                 # FastAPI RAG service
│   ├── app/
│   │   ├── main.py
│   │   ├── config.py
│   │   ├── schemas.py
│   │   └── vector_store.py
│   ├── requirements.txt
│   └── Dockerfile
├── docs/
│   └── ARCHITECTURE.md
├── .env.example
├── .gitignore
├── docker-compose.yml
└── README.md
```

## 3. Khởi tạo Laravel

Tại thư mục gốc repository, chạy:

```bash
composer create-project laravel/laravel app
```

Sau đó sao chép các biến cần thiết từ `.env.example` vào `app/.env`, cập nhật thông tin MySQL và chạy:

```bash
cd app
php artisan key:generate
php artisan migrate
php artisan serve
```

Để dùng Laravel Queue với database:
```bash
php artisan make:queue-table
php artisan migrate
php artisan queue:work
```
Nếu phiên bản Laravel của bạn không có `make:queue-table`, hãy dùng lệnh tạo bảng queue tương ứng với phiên bản Laravel đang cài.

## 4. Chạy các dịch vụ dữ liệu

Từ thư mục gốc:

```bash
docker compose up -d mysql qdrant
```

MySQL mặc định được ánh xạ cổng `3307` trên máy host để tránh xung đột cổng `3306`. Qdrant: `6333` (HTTP), `6334` (gRPC).

## 5. Chạy RAG API local

```bash
cd rag-service
python -m venv .venv
# Windows:
.venv\Scripts\activate
# macOS/Linux:
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
```

Tài liệu API: http://127.0.0.1:8000/docs
Health check: http://127.0.0.1:8000/health

## 6. Lưu ý quan trọng
- Không commit `.env`, API key, mật khẩu hoặc dữ liệu đề thi thật lên GitHub.
- Qdrant chỉ lưu vector và payload tối thiểu phục vụ truy xuất; nội dung và metadata chính thức cần lưu trong MySQL theo thiết kế.
- Mã hiện tại chỉ cung cấp health endpoint và khung API; chưa triển khai đầy đủ parsing PDF/DOCX/PPTX, chunking, indexing, retrieval, sinh đề và xuất DOCX/PDF.
