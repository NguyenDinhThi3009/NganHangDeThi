"""Đọc Bản mô tả chương trình đào tạo (bản scan) bằng mô hình đọc ảnh: thông tin chung và danh sách PLO."""
import base64
import json
import re

from . import llm

SYSTEM_PROMPT = (
    "Bạn là công cụ đọc văn bản trong ảnh chụp tài liệu tiếng Việt. Chép lại CHÍNH XÁC chữ trong ảnh, "
    "không sửa lỗi, không suy diễn, không thêm thông tin không có trong ảnh. Thông tin nào không xuất hiện thì để null. "
    "Luôn trả lời bằng MỘT đối tượng JSON hợp lệ, không kèm giải thích hay định dạng markdown."
)

PROMPT_CHUNG_PLO = """Các ảnh là trang trích từ "Bản mô tả chương trình đào tạo". Hãy đọc và trả về JSON theo mẫu:

{
  "ten_chuong_trinh": "tên chương trình đào tạo (tiếng Việt)",
  "ten_tieng_anh": "tên chương trình bằng tiếng Anh hoặc null",
  "trinh_do": "trình độ đào tạo, ví dụ Đại học",
  "ma_nganh": "mã ngành đào tạo (chuỗi số)",
  "tong_so_tin_chi": 159,
  "khoa_quan_ly": "khoa quản lý",
  "khoa_hoc": "khóa học áp dụng nếu có ghi rõ, nếu không thì null",
  "nam_ban_hanh": 2025,
  "plo": [{"ky_hieu": "PLO1", "mo_ta": "nội dung chuẩn đầu ra"}]
}

Quy tắc:
- Thông tin chung nằm ở bảng "1.1. Thông tin chung" (tên chương trình, trình độ, mã ngành, số tín chỉ yêu cầu, khoa quản lý).
- PLO nằm ở bảng "Chuẩn đầu ra CTĐT" (cột TT là PLO1, PLO2, ..., cột bên cạnh là nội dung). Chép ĐỦ TẤT CẢ các PLO, mỗi PLO một phần tử. Câu bị xuống dòng trong bảng thì nối lại thành một câu liền mạch.
- nam_ban_hanh là năm ban hành hoặc năm lập Bản mô tả, ghi ở trang bìa (ví dụ "Đà Nẵng, 08/2025" hoặc "ban hành kèm theo Quyết định ... năm 2025") hoặc mục "Ban hành"; là số nguyên 4 chữ số, null nếu không thấy trong ảnh.
- KHÔNG lấy các mục tiêu đào tạo O1, O2, O3, O4 và không lấy bảng liên hệ giữa mục tiêu và PLO.
- Không dùng dấu ngoặc kép (\\") bên trong nội dung văn bản; nếu cần hãy dùng dấu nháy đơn (')."""


def _phan_tich_json(text: str) -> dict:
    text = re.sub(r"^```(?:json)?|```$", "", text.strip(), flags=re.MULTILINE).strip()
    start = text.find("{")
    if start == -1:
        raise ValueError("Mô hình không trả về JSON")
    end = text.rfind("}")
    snippet = text[start : end + 1] if end > start else text[start:]
    try:
        return json.loads(snippet)
    except json.JSONDecodeError:
        from json_repair import repair_json

        du_lieu = repair_json(text[start:], return_objects=True)
        if isinstance(du_lieu, dict) and du_lieu:
            return du_lieu
        raise ValueError("JSON do mô hình trả về không hợp lệ")


def _anh_sang_noi_dung(images: list[bytes]) -> list[dict]:
    return [
        {"type": "image_url", "image_url": {"url": "data:image/jpeg;base64," + base64.b64encode(img).decode()}}
        for img in images
    ]


def _int(value) -> int | None:
    match = re.search(r"\d+", str(value)) if value is not None else None
    return int(match.group()) if match else None


def _nam(value) -> int | None:
    """Lấy năm 4 chữ số (1990-2100) từ giá trị như 2025 hoặc "08/2025"; không có thì trả về None."""
    if value is None:
        return None
    match = re.search(r"(?<!\d)(?:19|20)\d{2}(?!\d)", str(value))
    return int(match.group()) if match else None


def _chuoi(value) -> str | None:
    if value is None:
        return None
    text = " ".join(str(value).split())
    return text if text and text.lower() not in ("null", "none") else None


def _goi_mo_hinh(images: list[bytes]) -> tuple[dict, dict]:
    """Gửi ảnh các trang cho mô hình, trả về (JSON thô, usage)."""
    noi_dung, usage = llm.chat(
        [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": [{"type": "text", "text": PROMPT_CHUNG_PLO}, *_anh_sang_noi_dung(images)]},
        ],
        temperature=0.0,
        max_tokens=8192,
    )
    return _phan_tich_json(noi_dung), usage


def doc_thong_tin_chung(images: list[bytes]) -> dict:
    """Đọc các trang người dùng đã chọn. Trả về dict khớp CtdtResponse."""
    du_lieu, usage = _goi_mo_hinh(images)
    return chuan_hoa(du_lieu, usage)


def _gop(goc: dict | None, moi: dict) -> dict:
    """Gộp kết quả thô của hai lô trang: trường đơn lấy giá trị đầu tiên có dữ liệu, PLO hợp lại."""
    if goc is None:
        return {**moi, "plo": list(moi.get("plo") or [])}
    ket_qua = dict(goc)
    for truong, gia_tri in moi.items():
        if truong != "plo" and not ket_qua.get(truong) and gia_tri:
            ket_qua[truong] = gia_tri
    ket_qua["plo"] = list(goc.get("plo") or []) + list(moi.get("plo") or [])
    return ket_qua


def _du_thong_tin(du_lieu: dict) -> bool:
    chuan = chuan_hoa(du_lieu)
    return bool(
        chuan["ten_chuong_trinh"] and chuan["tong_so_tin_chi"] and chuan["khoa_quan_ly"] and chuan["plo"]
    )


def doc_tu_dong(tong_trang: int, render_lo, kich_thuoc_lo: int = 12, toi_da_lo: int = 3) -> dict:
    """Tự tìm thông tin: đọc từ trang đầu theo từng lô, dừng ngay khi đã có thông tin chung và PLO.

    render_lo(danh_sach_trang) -> danh sách ảnh. Thông tin cần lấy nằm ở phần đầu Bản mô tả nên thường chỉ cần lô đầu tiên.
    """
    gop: dict | None = None
    tokens_vao = tokens_ra = 0
    da_doc: list[int] = []

    for i in range(toi_da_lo):
        bat_dau = i * kich_thuoc_lo + 1
        if bat_dau > tong_trang:
            break
        trang = list(range(bat_dau, min(bat_dau + kich_thuoc_lo - 1, tong_trang) + 1))
        du_lieu, usage = _goi_mo_hinh(render_lo(trang))
        gop = _gop(gop, du_lieu)
        da_doc.extend(trang)
        tokens_vao += usage.get("prompt_tokens") or 0
        tokens_ra += usage.get("completion_tokens") or 0
        if _du_thong_tin(gop):
            break

    ket_qua = chuan_hoa(gop or {}, {"prompt_tokens": tokens_vao, "completion_tokens": tokens_ra})
    ket_qua["trang_da_doc"] = f"{da_doc[0]}-{da_doc[-1]}" if da_doc else ""
    ket_qua["so_trang_da_doc"] = len(da_doc)
    if not ket_qua["plo"]:
        ket_qua["canh_bao"] = [w for w in ket_qua["canh_bao"] if not w.startswith("Không đọc được PLO nào")]
        ket_qua["canh_bao"].append(
            f"Không tìm thấy PLO trong {len(da_doc)} trang đã đọc (trang {ket_qua['trang_da_doc']}). "
            "Hãy nhập số trang có bảng 'Chuẩn đầu ra CTĐT' thủ công."
        )
    return ket_qua


def chuan_hoa(du_lieu: dict, usage: dict | None = None) -> dict:
    canh_bao: list[str] = []

    ket_qua = {
        "ten_chuong_trinh": _chuoi(du_lieu.get("ten_chuong_trinh")),
        "ten_tieng_anh": _chuoi(du_lieu.get("ten_tieng_anh")),
        "trinh_do": _chuoi(du_lieu.get("trinh_do")),
        "ma_nganh": (re.sub(r"\D", "", str(du_lieu.get("ma_nganh") or "")) or None),
        "tong_so_tin_chi": _int(du_lieu.get("tong_so_tin_chi")),
        "khoa_quan_ly": _chuoi(du_lieu.get("khoa_quan_ly")),
        "khoa_hoc": _chuoi(du_lieu.get("khoa_hoc")),
        "nam_ban_hanh": _nam(du_lieu.get("nam_ban_hanh")),
    }
    for truong, ten in (
        ("ten_chuong_trinh", "tên chương trình"),
        ("trinh_do", "trình độ đào tạo"),
        ("tong_so_tin_chi", "tổng số tín chỉ"),
        ("khoa_quan_ly", "khoa quản lý"),
    ):
        if not ket_qua[truong]:
            canh_bao.append(f"Không đọc được {ten}, hãy nhập tay ở bước xem trước.")

    # PLO: chuẩn hóa ký hiệu, bỏ trùng (giữ bản dài hơn), sắp theo số
    plo_theo_so: dict[int, dict] = {}
    for p in du_lieu.get("plo") or []:
        if not isinstance(p, dict):
            continue
        m = re.search(r"PLO\s*(\d+)", str(p.get("ky_hieu", "")), re.I)
        mo_ta = _chuoi(p.get("mo_ta"))
        if not m or not mo_ta:
            continue
        so = int(m.group(1))
        if so not in plo_theo_so or len(mo_ta) > len(plo_theo_so[so]["mo_ta"]):
            plo_theo_so[so] = {"ky_hieu": f"PLO{so}", "mo_ta": mo_ta}
    ket_qua["plo"] = [plo_theo_so[so] for so in sorted(plo_theo_so)]

    if not ket_qua["plo"]:
        canh_bao.append("Không đọc được PLO nào. Hãy kiểm tra số trang đã chọn (trang có bảng 'Chuẩn đầu ra CTĐT').")
    else:
        thieu = [f"PLO{i}" for i in range(1, max(plo_theo_so) + 1) if i not in plo_theo_so]
        if thieu:
            canh_bao.append(f"Danh sách PLO bị thiếu: {', '.join(thieu)} (có thể ở trang khác hoặc chưa đọc được).")

    ket_qua["canh_bao"] = canh_bao
    ket_qua["so_token_vao"] = (usage or {}).get("prompt_tokens")
    ket_qua["so_token_ra"] = (usage or {}).get("completion_tokens")
    return ket_qua
