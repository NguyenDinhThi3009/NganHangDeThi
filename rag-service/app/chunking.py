"""Chia văn bản thành các đoạn (chunk) có chồng lấp nhẹ, ưu tiên cắt ở cuối câu, không cắt giữa từ."""
import re


def _align_start(text: str, start: int, limit: int = 40) -> int:
    """Nếu điểm bắt đầu rơi giữa một từ thì dời tới đầu từ kế tiếp."""
    if start > 0 and not text[start - 1].isspace():
        nxt = text.find(" ", start, start + limit)
        if nxt != -1:
            return nxt + 1
    return start


def chunk_pages(
    pages: list[tuple[int, str]], size: int = 1000, overlap: int = 150, min_len: int = 50
) -> list[tuple[int, str]]:
    chunks: list[tuple[int, str]] = []
    for page, raw in pages:
        text = re.sub(r"\s+", " ", raw).strip()
        if not text:
            continue
        start = 0
        while start < len(text):
            end = min(start + size, len(text))
            if end < len(text):
                cut = max(text.rfind(". ", start, end), text.rfind("; ", start, end), text.rfind(": ", start, end))
                if cut > start + size // 2:
                    end = cut + 1  # cắt ngay sau dấu câu
                else:
                    space = text.rfind(" ", start + size // 2, end)
                    if space != -1:
                        end = space  # không có dấu câu: cắt tại khoảng trắng, tránh cắt giữa từ
            piece = text[start:end].strip()
            if len(piece) >= min_len:
                chunks.append((page, piece))
            if end >= len(text):
                break
            start = _align_start(text, max(end - overlap, start + 1))
    return chunks