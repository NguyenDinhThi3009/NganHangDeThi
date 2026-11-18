"""Chẩn đoán nhanh: chạy trong thư mục rag-service:  python test_llm.py
Kiểm tra lần lượt Qdrant, danh sách model của LLM và một lần gọi chat, kèm thời gian từng bước."""
import time

import httpx

from app.config import settings


def buoc(ten, ham):
    t0 = time.monotonic()
    try:
        kq = ham()
        print(f"[OK ] {ten} ({time.monotonic() - t0:.1f}s): {kq}")
    except Exception as exc:
        print(f"[LỖI] {ten} ({time.monotonic() - t0:.1f}s): {exc!r}")


print("LLM_BASE_URL =", settings.llm_base_url, "| LLM_MODEL =", settings.llm_model, "| QDRANT_URL =", settings.qdrant_url)
buoc("Qdrant", lambda: httpx.get(settings.qdrant_url.rstrip("/") + "/collections", timeout=10).status_code)

if settings.llm_base_url and settings.llm_api_key:
    base = settings.llm_base_url.rstrip("/")
    h = {"Authorization": f"Bearer {settings.llm_api_key}"}
    buoc("Danh sách model", lambda: [m["id"] for m in httpx.get(f"{base}/models", headers=h, timeout=30).json().get("data", []) if "flash" in m["id"]])
    buoc(
        "Gọi chat ngắn",
        lambda: httpx.post(
            f"{base}/chat/completions", headers=h, timeout=90,
            json={"model": settings.llm_model, "messages": [{"role": "user", "content": "Chào, trả lời một câu ngắn."}]},
        ).text[:300],
    )
else:
    print("Chưa cấu hình LLM_BASE_URL / LLM_API_KEY trong rag-service/.env")
