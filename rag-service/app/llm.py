"""Gọi LLM qua API tương thích OpenAI (Gemini, OpenAI, Groq, Ollama...). Cấu hình bằng biến môi trường."""
import time

import httpx

from .config import settings


class LLMError(Exception):
    pass


def chat(messages: list[dict], temperature: float = 0.4, max_tokens: int | None = None) -> tuple[str, dict]:
    if not (settings.llm_base_url and settings.llm_api_key and settings.llm_model):
        raise LLMError("Chưa cấu hình LLM_BASE_URL / LLM_API_KEY / LLM_MODEL trong rag-service/.env")

    url = settings.llm_base_url.rstrip("/") + "/chat/completions"
    headers = {"Authorization": f"Bearer {settings.llm_api_key}"}
    payload = {
        "model": settings.llm_model,
        "messages": messages,
        "temperature": temperature,
        "max_tokens": max_tokens or settings.llm_max_tokens,
    }

    last_status = None
    for attempt in range(4):
        try:
            r = httpx.post(url, headers=headers, json=payload, timeout=settings.llm_timeout)
        except httpx.HTTPError as exc:
            last_status = f"lỗi kết nối: {exc}"
            time.sleep(2 * (attempt + 1))
            continue
        if r.status_code in (429, 500, 502, 503, 504):  # hết hạn mức hoặc lỗi tạm thời -> thử lại
            last_status = r.status_code
            time.sleep(3 * (2 ** attempt))
            continue
        if r.status_code != 200:
            raise LLMError(f"LLM trả về {r.status_code}: {r.text[:300]}")
        data = r.json()
        try:
            choice = data["choices"][0]
            content = choice["message"]["content"] or ""
        except (KeyError, IndexError):
            raise LLMError(f"Phản hồi LLM không đúng định dạng: {str(data)[:300]}")
        usage = dict(data.get("usage", {}) or {})
        usage["finish_reason"] = choice.get("finish_reason")  # "length" = bị cắt do hết max_tokens
        return content, usage

    raise LLMError(f"LLM không phản hồi sau nhiều lần thử (lần cuối: {last_status})")
