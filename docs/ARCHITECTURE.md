# Kiến trúc khởi đầu

## Thành phần
1. **Laravel Web**: giao diện, xác thực, quản lý tài liệu/câu hỏi/đề thi, API nghiệp vụ.
2. **MySQL 8**: lưu người dùng, tài liệu, metadata, nội dung trích xuất, chunks, câu hỏi và đề thi.
3. **Laravel Queue**: xử lý bất đồng bộ khi tải lên, trích xuất văn bản, chunking, embedding và xuất file.
4. **FastAPI RAG**: cung cấp API embedding/retrieval và phối hợp với Qdrant.
5. **Qdrant**: hai collection `chunk_tai_lieu` và `cau_hoi`.
6. **LLM API**: gọi qua adapter cấu hình bằng `LLM_PROVIDER`, `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL`.

## Luồng tài liệu
1. Người dùng tải PDF/DOCX/PPTX lên Laravel.
2. Laravel kiểm tra loại file/kích thước, lưu file và ghi metadata vào MySQL.
3. Laravel dispatch job vào queue.
4. Worker trích xuất nội dung, chia chunks và lưu nội dung/metadata vào MySQL.
5. Worker gọi RAG API để tạo embedding và upsert vector vào collection Qdrant.
6. Khi truy vấn, RAG API tìm vector liên quan trong Qdrant rồi lấy nội dung chuẩn từ MySQL.
7. LLM được gọi qua API adapter với cấu hình bên ngoài mã nguồn.
8. Kết quả được lưu và xuất DOCX/PDF.

## Các quyết định cần chốt khi triển khai
- Cơ chế xác thực giữa Laravel và FastAPI.
- Cách lưu file gốc (local, S3-compatible hoặc storage khác).
- Quy tắc chunking, quyền truy cập tài liệu và lọc theo người dùng.
- Provider LLM thực tế, timeout, retry và giới hạn chi phí.
- Kiểm thử định dạng tài liệu và chất lượng trích xuất.
