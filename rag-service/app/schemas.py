from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: str = "ok"
    service: str = "rag-service"


# ---------- Tìm kiếm ----------
class SearchRequest(BaseModel):
    query: str = Field(min_length=1, max_length=4000)
    limit: int = Field(default=5, ge=1, le=50)
    document_ids: list[int] | None = None


class SearchResult(BaseModel):
    chunk_id: int  # số thứ tự đoạn trong tài liệu (chunk_index)
    document_id: int
    text: str
    score: float
    metadata: dict = Field(default_factory=dict)


class SearchResponse(BaseModel):
    results: list[SearchResult]


# ---------- Nạp tài liệu ----------
class ChunkInfo(BaseModel):
    chunk_index: int
    page: int
    vector_id: str
    text: str


class IngestResponse(BaseModel):
    document_id: int
    so_trang: int
    so_chunk: int
    model_embedding: str
    chunks: list[ChunkInfo]


# ---------- Sinh câu hỏi ----------
class GenerateRequest(BaseModel):
    hoc_phan: str = Field(min_length=1, max_length=255)
    clo_ky_hieu: str = Field(default="CLO1", max_length=20)
    clo_mo_ta: str = Field(min_length=5, max_length=2000)
    muc_do_bloom: str = "Hiểu"
    do_kho: str = "Trung bình"
    loai_cau_hoi: str = Field(default="Trắc nghiệm", description="'Trắc nghiệm' hoặc 'Tự luận'")
    so_luong: int = Field(default=3, ge=1, le=10)
    document_ids: list[int] | None = None
    top_k: int = Field(default=6, ge=1, le=20)


class DapAnTracNghiem(BaseModel):
    noi_dung: str
    la_dap_an_dung: bool


class YDapAnTuLuan(BaseModel):
    noi_dung: str
    diem: float


class GeneratedQuestion(BaseModel):
    noi_dung: str
    loai_cau_hoi: str
    giai_thich: str | None = None
    nguon_chunk: list[int] = Field(default_factory=list)
    dap_an_trac_nghiem: list[DapAnTracNghiem] = Field(default_factory=list)
    y_dap_an_tu_luan: list[YDapAnTuLuan] = Field(default_factory=list)


class GenerateResponse(BaseModel):
    questions: list[GeneratedQuestion]
    canh_bao: list[str] = Field(default_factory=list)
    ngu_canh: list[SearchResult] = Field(default_factory=list)
    ten_model: str
    so_token_vao: int | None = None
    so_token_ra: int | None = None
    thoi_gian_xu_ly_ms: int
