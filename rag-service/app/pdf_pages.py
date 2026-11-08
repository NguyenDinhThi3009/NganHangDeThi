"""Chuyển các trang PDF (kể cả bản scan) thành ảnh JPEG để gửi cho mô hình đọc ảnh."""
import re

try:
    import pymupdf as fitz
except ImportError:  # bản PyMuPDF cũ chỉ có tên fitz
    import fitz

MAX_PAGES_PER_CALL = 12


def parse_pages(spec: str, total: int) -> list[int]:
    """'2,4-5' -> [2, 4, 5] (số trang tính từ 1). Ném ValueError nếu sai định dạng hoặc vượt quá số trang."""
    pages: list[int] = []
    for part in re.split(r"[,\s;]+", (spec or "").strip()):
        if not part:
            continue
        m = re.fullmatch(r"(\d+)(?:-(\d+))?", part)
        if not m:
            raise ValueError(f"Số trang không hợp lệ: '{part}' (ví dụ đúng: 2,4 hoặc 9-13)")
        a = int(m.group(1))
        b = int(m.group(2) or a)
        if a > b:
            raise ValueError(f"Khoảng trang '{part}' bị ngược")
        pages.extend(range(a, b + 1))

    pages = sorted(set(pages))
    if not pages:
        raise ValueError("Chưa chọn trang nào")
    if pages[0] < 1 or pages[-1] > total:
        raise ValueError(f"Trang phải nằm trong khoảng 1-{total}")
    if len(pages) > MAX_PAGES_PER_CALL:
        raise ValueError(f"Mỗi lần chỉ đọc tối đa {MAX_PAGES_PER_CALL} trang")
    return pages


def so_trang(pdf: bytes) -> int:
    with fitz.open(stream=pdf, filetype="pdf") as doc:
        return len(doc)


def render_pages(pdf: bytes, pages: list[int], dpi: int = 130) -> list[bytes]:
    with fitz.open(stream=pdf, filetype="pdf") as doc:
        return [doc[n - 1].get_pixmap(dpi=dpi).tobytes("jpeg", jpg_quality=85) for n in pages]
