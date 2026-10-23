import httpx
from app.config import settings

base = settings.llm_base_url.rstrip("/")
h = {"Authorization": f"Bearer {settings.llm_api_key}"}

# 1) In ra các mô hình "flash" mà key của bạn gọi được
ids = [m["id"] for m in httpx.get(f"{base}/models", headers=h, timeout=30).json().get("data", [])]
print([i for i in ids if "flash" in i])

# 2) Thử gọi chat bằng LLM_MODEL đã cấu hình
r = httpx.post(
    f"{base}/chat/completions",
    headers=h,
    json={"model": settings.llm_model, "messages": [{"role": "user", "content": "Chào, trả lời một câu ngắn."}]},
    timeout=60,
)
print(r.status_code, r.text[:500])