"""Chia văn bản thành các đoạn (chunk) có chồng lấp nhẹ, ưu tiên cắt ở cuối câu."""
import re


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
                    end = cut + 1
            piece = text[start:end].strip()
            if len(piece) >= min_len:
                chunks.append((page, piece))
            if end >= len(text):
                break
            start = max(end - overlap, start + 1)
    return chunks
