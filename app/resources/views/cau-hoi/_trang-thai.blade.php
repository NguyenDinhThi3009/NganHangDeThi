@if ($trangThai)
    @php
        $lop = match ($trangThai['trang_thai']) {
            'Hoàn tất' => 'thanh-cong',
            'Lỗi' => 'loi-khung',
            default => 'canh-bao',
        };
    @endphp
    <div class="{{ $lop }}">
        <strong>Lần sinh gần nhất ({{ $trangThai['cap_nhat'] }}): {{ $trangThai['trang_thai'] }}.</strong>
        {{ $trangThai['thong_bao'] }}
        @if (! empty($trangThai['canh_bao']))
            <ul style="margin:8px 0 0;">
                @foreach ($trangThai['canh_bao'] as $cb)
                    <li>{{ $cb }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
