"""Gọi LLM qua API tương thích OpenAI (Gemini, OpenAI, Groq, Ollama...). Cấu hình bằng biến môi trường."""
import logging
import time

import httpx

from .config import settings

log = logging.getLogger("uvicorn.error")


class LLMError(Exception):
    pass


def chat(
    messages: list[dict],
    temperature: float = 0.4,
    max_tokens: int | None = None,
    deadline: float | None = None,
) -> tuple[str, dict]:
    """Gọi LLM. `deadline` là mốc time.monotonic() không được vượt qua (kể cả thời gian thử lại);
    mặc định là llm_total_timeout giây kể từ lúc gọi. Hết thời gian thì báo lỗi rõ ràng thay vì treo."""
    if not (settings.llm_base_url and settings.llm_api_key and settings.llm_model):
        raise LLMError("Chưa cấu hình LLM_BASE_URL / LLM_API_KEY / LLM_MODEL trong rag-service/.env")

    if deadline is None:
        deadline = time.monotonic() + settings.llm_total_timeout

    url = settings.llm_base_url.rstrip("/") + "/chat/completions"
    headers = {"Authorization": f"Bearer {settings.llm_api_key}"}
    payload = {
        "model": settings.llm_model,
        "messages": messages,
        "temperature": temperature,
        "max_tokens": max_tokens or settings.llm_max_tokens,
    }
    if settings.llm_reasoning_effort:
        payload["reasoning_effort"] = settings.llm_reasoning_effort

    last_status = None
    for attempt in range(1, 5):
        con_lai = deadline - time.monotonic()
        if con_lai < 5:
            raise LLMError(
                f"Hết thời gian chờ LLM (tối đa {settings.llm_total_timeout}s cho một yêu cầu). "
                f"Lần cuối: {last_status or 'chưa có phản hồi'}. "
                "Hãy thử model nhanh hơn, đặt LLM_REASONING_EFFORT=low, hoặc giảm số câu/số đoạn ngữ cảnh."
            )
        doc = min(settings.llm_timeout, con_lai)
        timeout = httpx.Timeout(connect=settings.llm_connect_timeout, read=doc, write=30, pool=10)
        t0 = time.monotonic()
        log.info("LLM %s: lần %d, chờ tối đa %.0fs", settings.llm_model, attempt, doc)
        try:
            r = httpx.post(url, headers=headers, json=payload, timeout=timeout)
        except httpx.ReadTimeout:
            # Mô hình quá chậm: thử lại cùng yêu cầu gần như chắc chắn chậm tiếp, nên báo lỗi ngay
            raise LLMError(
                f"LLM '{settings.llm_model}' không trả lời trong {doc:.0f} giây. "
                "Hãy thử model nhanh hơn (ví dụ gemini-2.5-flash), đặt LLM_REASONING_EFFORT=low "
                "hoặc giảm LLM_MAX_TOKENS."
            )
        except httpx.ConnectError as exc:
            raise LLMError(f"Không kết nối được tới LLM_BASE_URL ({settings.llm_base_url}): {exc}. Kiểm tra mạng/proxy/VPN.")
        except httpx.HTTPError as exc:
            last_status = f"lỗi kết nối: {exc!r}"
            time.sleep(min(2 * attempt, max(0, deadline - time.monotonic() - 5)))
            continue

        log.info("LLM trả về %s sau %.1fs", r.status_code, time.monotonic() - t0)
        if r.status_code in (429, 500, 502, 503, 504):  # hết hạn mức hoặc lỗi tạm thời -> thử lại
            last_status = f"HTTP {r.status_code}: {r.text[:150]}"
            try:
                cho = float(r.headers.get("retry-after", ""))
            except ValueError:
                cho = 3 * (2 ** (attempt - 1))
            time.sleep(min(cho, 30, max(0, deadline - time.monotonic() - 5)))
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
