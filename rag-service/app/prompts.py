"""Prompt mẫu. Về sau sẽ chuyển sang bảng mauPrompt trong MySQL để quản trị viên chỉnh sửa."""

SYSTEM_PROMPT = (
    "Bạn là giảng viên đại học giàu kinh nghiệm, chuyên biên soạn câu hỏi thi bằng tiếng Việt. "
    "Bạn CHỈ được dùng thông tin có trong phần NGỮ CẢNH do người dùng cung cấp, tuyệt đối không bịa thêm kiến thức ngoài ngữ cảnh. "
    "Nếu ngữ cảnh không đủ để ra câu hỏi, hãy trả về danh sách rỗng. "
    "Luôn trả lời bằng MỘT đối tượng JSON hợp lệ, không kèm giải thích hay định dạng markdown."
)


def build_user_prompt(req, context_chunks: list[dict]) -> str:
    ngu_canh = "\n\n".join(f"[{i}] {c['text']}" for i, c in enumerate(context_chunks, start=1))

    if req.loai_cau_hoi.lower().startswith("trắc"):
        dinh_dang = (
            '{"cau_hoi": [{"noi_dung": "...", "giai_thich": "...", "nguon": [1],\n'
            '  "dap_an": [{"noi_dung": "...", "dung": true}, {"noi_dung": "...", "dung": false}, '
            '{"noi_dung": "...", "dung": false}, {"noi_dung": "...", "dung": false}]}]}'
        )
        yeu_cau_loai = (
            "Mỗi câu có đúng 4 phương án, chỉ 1 phương án đúng. Các phương án sai phải hợp lý nhưng chắc chắn sai theo NGỮ CẢNH, "
            "không được có phương án nào có thể bị coi là đúng một phần. Bốn phương án có độ dài và mức chi tiết tương đương nhau "
            "(không để phương án đúng luôn dài nhất); diễn đạt lại bằng lời khác, không chép nguyên văn câu trong ngữ cảnh."
        )
    else:
        dinh_dang = (
            '{"cau_hoi": [{"noi_dung": "...", "giai_thich": "...", "nguon": [1],\n'
            '  "y_dap_an": [{"noi_dung": "...", "diem": 2.0}, {"noi_dung": "...", "diem": 3.0}]}]}'
        )
        yeu_cau_loai = "Mỗi câu có đáp án chia thành các ý, mỗi ý kèm điểm; tổng điểm các ý của một câu bằng 10."

    return (
        f"Học phần: {req.hoc_phan}\n"
        f"Chuẩn đầu ra học phần ({req.clo_ky_hieu}): {req.clo_mo_ta}\n"
        f"Loại câu hỏi: {req.loai_cau_hoi}\n"
        f"Mức độ Bloom: {req.muc_do_bloom}\n"
        f"Độ khó: {req.do_kho}\n"
        f"Số câu cần sinh: {req.so_luong}\n\n"
        f"NGỮ CẢNH (trích từ tài liệu học phần, đánh số):\n{ngu_canh}\n\n"
        "YÊU CẦU:\n"
        f"- Sinh đúng {req.so_luong} câu hỏi đo mức đạt của chuẩn đầu ra trên, đúng mức Bloom và độ khó đã nêu.\n"
        f"- {yeu_cau_loai}\n"
        "- Mỗi câu phải trả lời được hoàn toàn từ NGỮ CẢNH; ghi số thứ tự các đoạn đã dùng vào trường \"nguon\".\n"
        "- Các câu hỏi không được trùng ý nhau.\n"
        "- Chỉ ghi số thứ tự đoạn vào trường \"nguon\"; tuyệt đối không nhắc \"đoạn 1\", \"theo ngữ cảnh\"... trong noi_dung hay giai_thich.\n"
        "- Đáp án chỉ gồm những ý mà câu hỏi thực sự yêu cầu, không thêm ý ngoài yêu cầu của đề.\n"
        "- Trường giai_thich chỉ 1-2 câu ngắn gọn.\n"
        "- Không dùng dấu ngoặc kép (\") bên trong nội dung văn bản, nếu cần trích dẫn hãy dùng dấu nháy đơn (') để JSON luôn hợp lệ.\n\n"
        f"ĐỊNH DẠNG JSON (chỉ trả về JSON này):\n{dinh_dang}"
    )