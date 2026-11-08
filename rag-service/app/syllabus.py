"""Đọc đề cương chi tiết (.docx) bằng quy tắc: thông tin học phần, bảng CLO, ma trận CLO - PLO.

Không dùng LLM vì các bảng của đề cương có cấu trúc cố định, đọc bằng quy tắc chính xác và nhanh hơn.
Phần nào không tìm thấy sẽ được ghi vào danh sách cảnh báo để người dùng kiểm tra ở bước xem trước.
"""
import io
import re

from docx import Document

MUC_DO = "IRM"


def _grid(table) -> list[list[str]]:
    """Mỗi dòng là danh sách nội dung ô theo đúng vị trí cột (ô gộp được lặp lại ở các cột nó phủ)."""
    return [[" ".join(cell.text.split()) for cell in row.cells] for row in table.rows]


def _int(text: str) -> int | None:
    match = re.search(r"\d+", text or "")
    return int(match.group()) if match else None


def _chuan_hoa_plo(text: str) -> str | None:
    match = re.search(r"PLO\s*(\d+)", text or "", re.I)
    return f"PLO{match.group(1)}" if match else None


def parse_syllabus(data: bytes) -> dict:
    doc = Document(io.BytesIO(data))
    luoi = [_grid(t) for t in doc.tables]
    canh_bao: list[str] = []

    kq: dict = {
        "ma_hoc_phan": None, "ten_hoc_phan": None, "ten_tieng_anh": None, "so_tin_chi": None,
        "gio_ly_thuyet": None, "gio_bai_tap": None, "gio_thuc_hanh": None, "gio_do_an": None, "gio_tu_hoc": None,
        "bo_mon": None, "khoa": None, "khoi_kien_thuc": None, "nganh": None, "ma_nganh": None,
    }

    # ---------- Thông tin học phần ----------
    for bang in luoi:
        for dong in bang:
            nhan = dong[0] if dong else ""
            gia_tri = dong[1] if len(dong) > 1 else ""
            for o in dong:
                if (m := re.search(r"Mã học phần\s*:\s*([A-Za-z0-9]+)", o)) and not kq["ma_hoc_phan"]:
                    kq["ma_hoc_phan"] = m.group(1)
                if (m := re.search(r"Tên học phần\s*:\s*(.+)", o)) and not kq["ten_hoc_phan"]:
                    kq["ten_hoc_phan"] = m.group(1).strip()
                if (m := re.search(r"Tên tiếng Anh\s*:\s*(.*)", o)) and not kq["ten_tieng_anh"] and m.group(1).strip():
                    kq["ten_tieng_anh"] = m.group(1).strip()
                if (m := re.search(r"Mã ngành\s*:\s*(\d+)", o)) and not kq["ma_nganh"]:
                    kq["ma_nganh"] = m.group(1)
                if (m := re.search(r"(?<!Mã )Ngành\s*:\s*(.+?)(?:\s+Mã ngành|$)", o)) and not kq["nganh"]:
                    kq["nganh"] = m.group(1).strip()
                if "☒" in o and "Giáo dục đại cương" in o and not kq["khoi_kien_thuc"]:
                    if m := re.search(r"☒\s*(.+?)(?=\s*[⬜☐☒]|$)", o):
                        kq["khoi_kien_thuc"] = m.group(1).strip()

            if re.search(r"Số tín chỉ", nhan) and kq["so_tin_chi"] is None:
                kq["so_tin_chi"] = _int(gia_tri)
            elif re.match(r"Lý thuyết", nhan) and kq["gio_ly_thuyet"] is None:
                kq["gio_ly_thuyet"] = _int(gia_tri)
            elif re.match(r"Bài tập", nhan) and kq["gio_bai_tap"] is None:
                kq["gio_bai_tap"] = _int(gia_tri)
            elif re.match(r"Thực hành", nhan) and kq["gio_thuc_hanh"] is None:
                kq["gio_thuc_hanh"] = _int(gia_tri)
            elif re.match(r"Đồ án", nhan) and kq["gio_do_an"] is None:
                kq["gio_do_an"] = _int(gia_tri)
            elif re.match(r"Tự học", nhan) and kq["gio_tu_hoc"] is None:
                kq["gio_tu_hoc"] = _int(gia_tri)
            elif re.match(r"Bộ môn\s*:?$", nhan) and not kq["bo_mon"] and gia_tri:
                kq["bo_mon"] = gia_tri
            elif re.match(r"Khoa\s*:?$", nhan) and not kq["khoa"] and gia_tri:
                kq["khoa"] = gia_tri

    for truong, ten in (("ma_hoc_phan", "mã học phần"), ("ten_hoc_phan", "tên học phần"), ("so_tin_chi", "số tín chỉ")):
        if not kq[truong]:
            canh_bao.append(f"Không đọc được {ten} từ đề cương, hãy nhập tay ở bước xem trước.")

    # ---------- Bảng CLO ----------
    clos: dict[str, dict] = {}
    bang_clo = next(
        (b for b in luoi if b and b[0] and b[0][0].strip().upper() == "CLO"
         and any("Mô tả" in o for o in b[0]) and any("PI" in o for o in b[0])),
        None,
    )
    if bang_clo is None:
        canh_bao.append("Không tìm thấy bảng CLO (cột CLO, Mô tả, Chỉ số PI) trong đề cương.")
    else:
        cot_pi = next(i for i, o in enumerate(bang_clo[0]) if "PI" in o)
        for dong in bang_clo[1:]:
            m = re.fullmatch(r"CLO\s*(\d+)", dong[0], re.I)
            if not m:
                continue
            ky_hieu = f"CLO{int(m.group(1))}"
            pi = re.findall(r"\d+\.\d+", dong[cot_pi]) if cot_pi < len(dong) else []
            clos[ky_hieu] = {"ky_hieu": ky_hieu, "mo_ta": dong[1], "pi": pi, "plo": []}
            if not pi:
                canh_bao.append(f"{ky_hieu} chưa có chỉ số PI.")

    # ---------- Ma trận CLO - PLO ----------
    tong_hop: list[dict] = []
    bang_ma_tran = next(
        (b for b in luoi if b and len(b[0]) > 1 and b[0][0].strip().upper() == "PLO" and _chuan_hoa_plo(b[0][1])),
        None,
    )
    if bang_ma_tran is None:
        canh_bao.append("Không tìm thấy ma trận CLO - PLO trong đề cương.")
    else:
        cac_plo = [_chuan_hoa_plo(o) for o in bang_ma_tran[0]]
        for dong in bang_ma_tran[1:]:
            muc = []
            for i, o in enumerate(dong):
                if i == 0 or i >= len(cac_plo) or not cac_plo[i]:
                    continue
                chu = next((c for c in o.upper() if c in MUC_DO), None)
                if chu:
                    muc.append({"ky_hieu": cac_plo[i], "muc_do": chu})
            nhan = dong[0].strip()
            if re.fullmatch(r"CLO\s*\d+", nhan, re.I):
                ky_hieu = f"CLO{int(re.search(r'\d+', nhan).group())}"
                if ky_hieu in clos:
                    clos[ky_hieu]["plo"] = muc
                else:
                    canh_bao.append(f"{ky_hieu} có trong ma trận PLO nhưng không có trong bảng CLO.")
            elif "tổng hợp" in nhan.lower():
                tong_hop = muc

    # Đề cương không ghi ánh xạ CLO - PLO: suy ra PLO từ chỉ số PI (PI1.2 -> PLO1), mức lấy từ dòng Tổng hợp.
    # Hợp lý vì chỉ số PI luôn thuộc một PLO; kết quả được đánh dấu để người dùng kiểm tra ở bước xem trước.
    muc_tong_hop = {p["ky_hieu"]: p["muc_do"] for p in tong_hop}
    for c in clos.values():
        if c["plo"] or not c["pi"]:
            continue
        suy_ra = []
        for so in sorted({int(pi.split(".")[0]) for pi in c["pi"]}):
            ky = f"PLO{so}"
            if muc_tong_hop and ky not in muc_tong_hop:
                continue  # PLO không thuộc ma trận học phần: không suy ra, để phần kiểm tra bên dưới báo lỗi
            suy_ra.append({"ky_hieu": ky, "muc_do": muc_tong_hop.get(ky, "I")})
        if suy_ra:
            c["plo"] = suy_ra
            c["suy_ra_tu_pi"] = True
            canh_bao.append(
                f"{c['ky_hieu']}: đề cương không có ánh xạ CLO - PLO, hệ thống suy ra "
                f"{', '.join(p['ky_hieu'] for p in suy_ra)} từ chỉ số PI. Hãy kiểm tra lại."
            )

    # Kiểm tra chéo và bù dữ liệu thiếu
    if clos and tong_hop == [] and bang_ma_tran is not None:
        canh_bao.append("Không thấy dòng 'Tổng hợp' trong ma trận, hệ thống tự tổng hợp từ các CLO (lấy mức cao nhất).")
    if clos and not tong_hop:
        cao_nhat: dict[str, str] = {}
        for c in clos.values():
            for p in c["plo"]:
                if p["ky_hieu"] not in cao_nhat or MUC_DO.index(p["muc_do"]) > MUC_DO.index(cao_nhat[p["ky_hieu"]]):
                    cao_nhat[p["ky_hieu"]] = p["muc_do"]
        tong_hop = [{"ky_hieu": k, "muc_do": v} for k, v in sorted(cao_nhat.items())]

    for c in clos.values():
        if not c["plo"]:
            canh_bao.append(f"{c['ky_hieu']} chưa được ánh xạ tới PLO nào trong ma trận.")
        so_plo = {int(re.search(r"\d+", p["ky_hieu"]).group()) for p in c["plo"]}
        for pi in c["pi"]:
            if int(pi.split(".")[0]) not in so_plo:
                canh_bao.append(f"{c['ky_hieu']}: PI{pi} không khớp với PLO trong ma trận.")

    kq["clos"] = list(clos.values())
    kq["tong_hop"] = tong_hop
    kq["canh_bao"] = canh_bao
    return kq
