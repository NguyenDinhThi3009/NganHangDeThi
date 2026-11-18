@extends('layouts.app')

@section('tieu_de', 'Phê duyệt đề #'.$deThi->maDeThi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('phe-duyet.index') }}">&larr; Danh sách chờ duyệt</a></p>

    <div class="the">
        <h2>{{ $deThi->tenDeThi }}</h2>
        @include('de-thi._thong-tin')

        @if ($nhanXet)
            <div class="canh-bao"><strong>Nhận xét phản biện:</strong> {{ $nhanXet }}</div>
        @endif
        @if ($lyDoTuChoi)
            <div class="loi-khung"><strong>Đề đã bị từ chối.</strong> {{ $lyDoTuChoi }}</div>
        @endif
        @foreach ($deTrung as $t)
            <div class="canh-bao">Đề trùng {{ number_format($t['ty_le'], 1, ',', '') }}% số câu với đề đã duyệt <a href="{{ route('phe-duyet.show', $t['ma']) }}">#{{ $t['ma'] }} - {{ $t['ten'] }}</a>. Quy định yêu cầu các đề khác nhau tối thiểu 70% (BR-09), bạn cân nhắc khi phê duyệt.</div>
        @endforeach

        @if ($deThi->trangThai === 'Chờ duyệt')
            <div class="hang-nut">
                <form method="POST" action="{{ route('phe-duyet.duyet', $deThi->maDeThi) }}"
                      onsubmit="return confirm('Phê duyệt đề này và đưa vào ngân hàng đề thi?');">
                    @csrf
                    <button type="submit" class="nut-xanh">Phê duyệt</button>
                </form>
                <form method="POST" action="{{ route('phe-duyet.tu-choi', $deThi->maDeThi) }}" style="display:flex; gap:8px; align-items:center;"
                      onsubmit="return confirm('Từ chối đề thi này?');">
                    @csrf
                    <input type="text" name="ly_do" maxlength="1000" placeholder="Lý do từ chối (không bắt buộc)" style="width:320px;">
                    <button type="submit" class="nut-do">Từ chối</button>
                </form>
            </div>
        @endif

        @include('de-thi._doi-chieu')

        <h3>Nội dung đề, đáp án và thang điểm</h3>
        @include('de-thi._noi-dung', ['muc' => $muc, 'hinhThuc' => $deThi->hinhThucThi, 'tongDiem' => $tongDiem, 'hienDapAn' => true])
    </div>
@endsection
