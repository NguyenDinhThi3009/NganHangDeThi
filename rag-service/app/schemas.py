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
    diem_cau: float = Field(default=2.0, gt=0, le=10, description="Tổng điểm của một câu tự luận (mỗi ý tối đa 0,5 điểm)")
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
    nguon_vector_ids: list[str] = Field(default_factory=list)  # id điểm vector của các đoạn nguồn (khớp doantailieu.vectorId)
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


# ---------- Phát hiện câu hỏi trùng lặp ----------
class DuplicateItem(BaseModel):
    ma_cau_hoi: int
    ma_hoc_phan: str
    noi_dung: str = Field(min_length=1, max_length=5000)


class DuplicateRequest(BaseModel):
    items: list[DuplicateItem] = Field(min_length=1, max_length=50)
    top_k: int = Field(default=5, ge=1, le=20)


class DuplicateNeighbor(BaseModel):
    ma_cau_hoi: int
    score: float  # độ tương đồng cosine


class DuplicateItemResult(BaseModel):
    ma_cau_hoi: int
    vector_id: str
    neighbors: list[DuplicateNeighbor]  # xếp từ gần nhất tới xa nhất


class DuplicateResponse(BaseModel):
    model_embedding: str
    results: list[DuplicateItemResult]


class DeleteQuestionsRequest(BaseModel):
    ma_cau_hoi: list[int] = Field(min_length=1, max_length=500)


# ---------- Đọc đề cương chi tiết ----------
class SyllabusPlo(BaseModel):
    ky_hieu: str  # PLO1...
    muc_do: str   # I | R | M


class SyllabusClo(BaseModel):
    ky_hieu: str
    mo_ta: str
    pi: list[str] = Field(default_factory=list)  # ví dụ ["1.1"]
    plo: list[SyllabusPlo] = Field(default_factory=list)
    suy_ra_tu_pi: bool = False  # True nếu đề cương không ghi ánh xạ CLO - PLO nên PLO được suy ra từ chỉ số PI


class SyllabusResponse(BaseModel):
    ma_hoc_phan: str | None = None
    ten_hoc_phan: str | None = None
    ten_tieng_anh: str | None = None
    so_tin_chi: int | None = None
    gio_ly_thuyet: int | None = None
    gio_bai_tap: int | None = None
    gio_thuc_hanh: int | None = None
    gio_do_an: int | None = None
    gio_tu_hoc: int | None = None
    bo_mon: str | None = None
    khoa: str | None = None
    khoi_kien_thuc: str | None = None
    nganh: str | None = None
    ma_nganh: str | None = None
    clos: list[SyllabusClo] = Field(default_factory=list)
    tong_hop: list[SyllabusPlo] = Field(default_factory=list)
    canh_bao: list[str] = Field(default_factory=list)


# ---------- Đọc Bản mô tả chương trình đào tạo (bản scan) ----------
class CtdtPlo(BaseModel):
    ky_hieu: str
    mo_ta: str


class CtdtResponse(BaseModel):
    ten_chuong_trinh: str | None = None
    ten_tieng_anh: str | None = None
    trinh_do: str | None = None
    ma_nganh: str | None = None
    tong_so_tin_chi: int | None = None
    khoa_quan_ly: str | None = None
    khoa_hoc: str | None = None
    nam_ban_hanh: int | None = None
    plo: list[CtdtPlo] = Field(default_factory=list)
    canh_bao: list[str] = Field(default_factory=list)
    so_trang_da_doc: int = 0
    trang_da_doc: str = ""
    ten_model: str = ""
    so_token_vao: int | None = None
    so_token_ra: int | None = None
