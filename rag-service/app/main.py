from fastapi import FastAPI, File, Form, HTTPException, UploadFile

from . import embedding, generator, llm, vector_store
from .chunking import chunk_pages
from .config import settings
from .extract import SUPPORTED, UnsupportedFormat, extract_pages
from .schemas import (
    ChunkInfo,
    GenerateRequest,
    GenerateResponse,
    HealthResponse,
    IngestResponse,
    SearchRequest,
    SearchResponse,
    SearchResult,
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
