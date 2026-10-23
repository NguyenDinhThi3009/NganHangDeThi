"""Luồng RAG sinh câu hỏi: tìm đoạn liên quan theo CLO -> ghép prompt -> gọi LLM -> kiểm tra kết quả."""
import json
import random
import re
import time

from . import embedding, llm, vector_store
from .config import settings
from .prompts import SYSTEM_PROMPT, build_user_prompt
from .schemas import (
    DapAnTracNghiem,
    GeneratedQuestion,
    GenerateRequest,
    GenerateResponse,
    SearchResult,
    YDapAnTuLuan,
)

MAX_ATTEMPTS = 2  # số lần hỏi LLM nếu phản hồi không phải JSON hợp lệ


class NoContextError(Exception):
    pass


class BadLLMOutput(Exception):
    pass


def _parse_json(text: str) -> dict:
    text = re.sub(r"^```(?:json)?|```$", "", text.strip(), flags=re.MULTILINE).strip()
    start = text.find("{")
    if start == -1:
        raise BadLLMOutput("LLM không trả về JSON")
    snippet = text[start : text.rfind("}") + 1] if text.rfind("}") > start else text[start:]
    try:
        return json.loads(snippet)
    except json.JSONDecodeError as exc:
        # Thử sửa các lỗi thường gặp: bị cắt cụt (thiếu ] }), dấu ngoặc kép chưa thoát, thiếu dấu phẩy
        try:
            from json_repair import repair_json

            fixed = repair_json(text[start:], return_objects=True)
            if isinstance(fixed, dict) and isinstance(fixed.get("cau_hoi"), list):
                return fixed
        except Exception:
            pass
        raise BadLLMOutput(f"JSON không hợp lệ: {exc}")


def _to_search_results(hits: list[dict]) -> list[SearchResult]:
    return [
        SearchResult(
            chunk_id=h["chunk_index"],
            document_id=h["document_id"],
            text=h["text"],
            score=h["score"],
            metadata={"page": h.get("page")},
        )
        for h in hits
    ]


def _ask_llm(messages: list[dict]) -> tuple[dict, int, int, list[str]]:
    """Gọi LLM, nếu JSON hỏng thì hỏi lại 1 lần kèm yêu cầu sửa. Trả về (dữ liệu, token vào, token ra, cảnh báo)."""
    tokens_in = tokens_out = 0
    warnings: list[str] = []
    last_error: BadLLMOutput | None = None
    tail = ""

    for attempt in range(1, MAX_ATTEMPTS + 1):
        content, usage = llm.chat(messages)
        tokens_in += usage.get("prompt_tokens") or 0
        tokens_out += usage.get("completion_tokens") or 0
        truncated = usage.get("finish_reason") == "length"
        try:
            data = _parse_json(content)
            if truncated:
                warnings.append("Phản hồi của mô hình bị cắt do hết giới hạn độ dài; các câu cuối có thể bị thiếu.")
            if attempt > 1:
                warnings.append(f"Phải hỏi lại mô hình {attempt} lần vì lần đầu trả về JSON không hợp lệ.")
            return data, tokens_in, tokens_out, warnings
        except BadLLMOutput as exc:
            last_error = exc
            tail = content[-200:].replace("\n", " ")
            messages = messages + [
                {"role": "assistant", "content": content},
                {
                    "role": "user",
                    "content": (
                        "Phản hồi trước không phải JSON hợp lệ hoặc bị cắt cụt. Hãy trả lại ĐÚNG MỘT đối tượng JSON hợp lệ, "
                        "ngắn gọn hơn (giải thích tối đa 1-2 câu), không dùng dấu ngoặc kép bên trong nội dung văn bản."
                    ),
                },
            ]

    raise BadLLMOutput(f"{last_error} (sau {MAX_ATTEMPTS} lần hỏi; phần cuối phản hồi: ...{tail})")


def generate_questions(req: GenerateRequest) -> GenerateResponse:
    started = time.time()

    query_vector = embedding.embed_texts([f"{req.hoc_phan}. {req.clo_mo_ta}"])[0]
    hits = vector_store.search_chunks(query_vector, req.top_k, req.document_ids)
    if not hits:
        raise NoContextError("Chưa có tài liệu nào được nạp (hoặc không tìm thấy đoạn liên quan).")

    data, tokens_in, tokens_out, warnings = _ask_llm(
        [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": build_user_prompt(req, hits)},
        ]
    )

    is_mc = req.loai_cau_hoi.lower().startswith("trắc")
    questions: list[GeneratedQuestion] = []

    items = data.get("cau_hoi", [])
    if not items:
        warnings.append(
            "Mô hình không sinh câu nào vì cho rằng ngữ cảnh không đủ kiến thức để ra câu hỏi "
            "(ví dụ tài liệu đã nạp là đề cương mô tả học phần chứ không phải giáo trình/bài giảng). "
            "Hãy xem mục ngu_canh để biết các đoạn đã được dùng."
        )

    for i, item in enumerate(items, start=1):
        noi_dung = str(item.get("noi_dung", "")).strip()
        if not noi_dung:
            warnings.append(f"Câu {i} bị bỏ vì thiếu nội dung.")
            continue
        nguon = [
            hits[n - 1]["chunk_index"]
            for n in item.get("nguon", [])
            if isinstance(n, int) and 1 <= n <= len(hits)
        ]
        q = GeneratedQuestion(
            noi_dung=noi_dung,
            loai_cau_hoi=req.loai_cau_hoi,
            giai_thich=(item.get("giai_thich") or None),
            nguon_chunk=nguon,
        )
        if is_mc:
            options = [
                DapAnTracNghiem(noi_dung=str(o.get("noi_dung", "")).strip(), la_dap_an_dung=bool(o.get("dung")))
                for o in item.get("dap_an", [])
                if str(o.get("noi_dung", "")).strip()
            ]
            if len(options) < 2 or sum(o.la_dap_an_dung for o in options) != 1:
                warnings.append(f"Câu {i} bị bỏ vì phương án không hợp lệ (cần ≥2 phương án và đúng 1 đáp án đúng).")
                continue
            random.shuffle(options)  # LLM thường đặt đáp án đúng ở vị trí đầu -> xáo trộn để tránh lộ đáp án
            q.dap_an_trac_nghiem = options
        else:
            parts = []
            for o in item.get("y_dap_an", []):
                text = str(o.get("noi_dung", "")).strip()
                if not text:
                    continue
                try:
                    diem = float(o.get("diem", 0))
                except (TypeError, ValueError):
                    diem = 0.0
                parts.append(YDapAnTuLuan(noi_dung=text, diem=diem))
            if not parts:
                warnings.append(f"Câu {i} bị bỏ vì thiếu đáp án.")
                continue
            tong = sum(p.diem for p in parts)
            if abs(tong - 10) > 0.01:
                warnings.append(f"Câu {i}: tổng điểm các ý là {tong:g} (yêu cầu 10), cần giảng viên chỉnh lại.")
            q.y_dap_an_tu_luan = parts
        questions.append(q)

    if len(questions) < req.so_luong:
        warnings.append(f"Chỉ sinh được {len(questions)}/{req.so_luong} câu hợp lệ.")

    return GenerateResponse(
        questions=questions,
        canh_bao=warnings,
        ngu_canh=_to_search_results(hits),
        ten_model=llm.settings.llm_model or "",
        so_token_vao=tokens_in or None,
        so_token_ra=tokens_out or None,
        thoi_gian_xu_ly_ms=int((time.time() - started) * 1000),
    )
