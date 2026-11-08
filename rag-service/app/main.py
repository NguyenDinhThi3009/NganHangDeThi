from fastapi import FastAPI, File, Form, HTTPException, UploadFile

from . import ctdt, embedding, generator, llm, pdf_pages, syllabus, vector_store
from .chunking import chunk_pages
from .config import settings
from .extract import SUPPORTED, UnsupportedFormat, extract_pages
from .schemas import (
    ChunkInfo,
    CtdtResponse,
    DeleteQuestionsRequest,
    DuplicateItemResult,
    DuplicateNeighbor,
    DuplicateRequest,
    DuplicateResponse,
    GenerateRequest,
    GenerateResponse,
    HealthResponse,
    IngestResponse,
    SearchRequest,
    SearchResponse,
    SearchResult,
    SyllabusResponse,
)

app = FastAPI(
    title=settings.app_name,
    version="0.2.0",
    description="API nạp tài liệu, truy xuất đoạn liên quan và sinh câu hỏi bằng RAG.",
)

MAX_UPLOAD_BYTES = 50 * 1024 * 1024  # 50 MB


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse()


@app.get("/")
def root() -> dict[str, str]:
    return {"service": "nganhangdethi-rag", "docs": "/docs", "status": "ready"}


@app.post("/v1/documents/ingest", response_model=IngestResponse)
def ingest(document_id: int = Form(...), file: UploadFile = File(...)) -> IngestResponse:
    """Trích văn bản -> chia đoạn -> tạo vector -> lưu vào Qdrant. Nạp lại cùng document_id sẽ ghi đè."""
    data = file.file.read()
    if len(data) > MAX_UPLOAD_BYTES:
        raise HTTPException(413, "File vượt quá 50 MB")

    try:
        pages = extract_pages(file.filename or "", data)
    except UnsupportedFormat as exc:
        raise HTTPException(415, f"{exc}. Hỗ trợ: {', '.join(sorted(SUPPORTED))}")
    except Exception as exc:  # file hỏng, có mật khẩu...
        raise HTTPException(422, f"Không đọc được file: {exc}")

    chunks = chunk_pages(pages, settings.chunk_size, settings.chunk_overlap)
    if not chunks:
        raise HTTPException(
            422, "Không trích xuất được văn bản. Nếu là PDF scan (ảnh) thì cần OCR trước khi nạp."
        )

    try:
        vectors = embedding.embed_texts([text for _, text in chunks])
        vector_store.ensure_collections(embedding.embedding_dim())
        vector_store.delete_document_chunks(document_id)
        vector_ids = vector_store.upsert_chunks(document_id, chunks, vectors, settings.embedding_model)
    except Exception as exc:
        raise HTTPException(502, f"Lỗi khi tạo vector hoặc lưu vào Qdrant (Qdrant đã chạy chưa?): {exc}")

    return IngestResponse(
        document_id=document_id,
        so_trang=len(pages),
        so_chunk=len(chunks),
        model_embedding=settings.embedding_model,
        chunks=[
            ChunkInfo(chunk_index=i, page=page, vector_id=vector_ids[i], text=text)
            for i, (page, text) in enumerate(chunks)
        ],
    )


@app.post("/v1/search", response_model=SearchResponse)
def search(request: SearchRequest) -> SearchResponse:
    try:
        vector = embedding.embed_texts([request.query])[0]
        hits = vector_store.search_chunks(vector, request.limit, request.document_ids)
    except Exception as exc:
        raise HTTPException(502, f"Lỗi tìm kiếm (Qdrant đã chạy, tài liệu đã nạp chưa?): {exc}")
    return SearchResponse(
        results=[
            SearchResult(
                chunk_id=h["chunk_index"],
                document_id=h["document_id"],
                text=h["text"],
                score=h["score"],
                metadata={"page": h.get("page")},
            )
            for h in hits
        ]
    )


@app.post("/v1/questions/generate", response_model=GenerateResponse)
def generate(request: GenerateRequest) -> GenerateResponse:
    try:
        return generator.generate_questions(request)
    except generator.NoContextError as exc:
        raise HTTPException(404, str(exc))
    except (llm.LLMError, generator.BadLLMOutput) as exc:
        raise HTTPException(502, str(exc))
    except Exception as exc:
        raise HTTPException(502, f"Lỗi khi sinh câu hỏi: {exc}")


@app.post("/v1/questions/check", response_model=DuplicateResponse)
def check_duplicates(request: DuplicateRequest) -> DuplicateResponse:
    """Tạo vector cho từng câu hỏi, tìm các câu gần nghĩa nhất trong cùng học phần rồi lưu vector vào Qdrant.

    Các câu được xử lý lần lượt nên câu sau so sánh được với cả các câu trước trong cùng lô.
    Việc so với ngưỡng và lưu kết quả do Laravel đảm nhiệm.
    """
    try:
        vectors = embedding.embed_texts([item.noi_dung for item in request.items])
        vector_store.ensure_collections(embedding.embedding_dim())

        results: list[DuplicateItemResult] = []
        for item, vector in zip(request.items, vectors):
            neighbors = vector_store.search_questions(vector, request.top_k, item.ma_hoc_phan, item.ma_cau_hoi)
            vector_id = vector_store.upsert_question(item.ma_cau_hoi, item.ma_hoc_phan, vector, settings.embedding_model)
            results.append(
                DuplicateItemResult(
                    ma_cau_hoi=item.ma_cau_hoi,
                    vector_id=vector_id,
                    neighbors=[DuplicateNeighbor(**n) for n in neighbors],
                )
            )
    except Exception as exc:
        raise HTTPException(502, f"Lỗi khi kiểm tra trùng lặp (Qdrant đã chạy chưa?): {exc}")

    return DuplicateResponse(model_embedding=settings.embedding_model, results=results)


@app.post("/v1/questions/delete")
def delete_questions(request: DeleteQuestionsRequest) -> dict[str, int]:
    """Xóa vector của các câu hỏi đã bị xóa khỏi ngân hàng."""
    try:
        vector_store.delete_questions(request.ma_cau_hoi)
    except Exception as exc:
        raise HTTPException(502, f"Lỗi khi xóa vector câu hỏi: {exc}")
    return {"da_xoa": len(request.ma_cau_hoi)}


@app.post("/v1/syllabus/parse", response_model=SyllabusResponse)
def parse_syllabus(file: UploadFile = File(...)) -> SyllabusResponse:
    """Đọc đề cương chi tiết (.docx) bằng quy tắc: học phần, CLO, ánh xạ CLO - PLO. Không lưu gì."""
    if not (file.filename or "").lower().endswith(".docx"):
        raise HTTPException(415, "Chỉ hỗ trợ đề cương định dạng .docx")
    data = file.file.read()
    if len(data) > MAX_UPLOAD_BYTES:
        raise HTTPException(413, "File vượt quá 50 MB")
    try:
        return SyllabusResponse(**syllabus.parse_syllabus(data))
    except Exception as exc:
        raise HTTPException(422, f"Không đọc được đề cương: {exc}")


@app.post("/v1/ctdt/parse", response_model=CtdtResponse)
def parse_ctdt(file: UploadFile = File(...), pages: str = Form("")) -> CtdtResponse:
    """Đọc thông tin chung và danh sách PLO từ Bản mô tả CTĐT (PDF, kể cả bản scan).

    Nếu không truyền `pages` thì tự tìm: đọc từ đầu file theo lô 12 trang và dừng khi đã đủ thông tin.
    """
    if not (file.filename or "").lower().endswith(".pdf"):
        raise HTTPException(415, "Chỉ hỗ trợ Bản mô tả định dạng .pdf")
    data = file.file.read()
    if len(data) > MAX_UPLOAD_BYTES:
        raise HTTPException(413, "File vượt quá 50 MB")

    try:
        tong_trang = pdf_pages.so_trang(data)
        danh_sach_trang = pdf_pages.parse_pages(pages, tong_trang) if pages.strip() else None
        images = pdf_pages.render_pages(data, danh_sach_trang) if danh_sach_trang else None
    except ValueError as exc:
        raise HTTPException(422, str(exc))
    except Exception as exc:
        raise HTTPException(422, f"Không đọc được file PDF: {exc}")

    try:
        if images is not None:
            ket_qua = ctdt.doc_thong_tin_chung(images)
            ket_qua["so_trang_da_doc"] = len(danh_sach_trang)
            ket_qua["trang_da_doc"] = pages.strip()
        else:
            ket_qua = ctdt.doc_tu_dong(tong_trang, lambda trang: pdf_pages.render_pages(data, trang))
    except llm.LLMError as exc:
        raise HTTPException(502, str(exc))
    except ValueError as exc:
        raise HTTPException(502, f"Không hiểu được phản hồi của mô hình: {exc}")
    except Exception as exc:
        raise HTTPException(502, f"Lỗi khi đọc Bản mô tả: {exc}")

    return CtdtResponse(**ket_qua, ten_model=llm.settings.llm_model or "")
