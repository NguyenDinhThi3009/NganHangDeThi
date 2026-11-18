<h3>Đối chiếu với ma trận</h3>
<table style="width:auto; min-width:60%;">
    <thead><tr><th>CLO</th><th>Mức Bloom</th><th>Độ khó</th><th>Theo ma trận</th><th>Trong đề</th><th></th></tr></thead>
    <tbody>
        @foreach ($doiChieu as $h)
            <tr @if (! $h['khop']) style="background:#fef2f2;" @endif>
                <td>{{ $h['clo'] }}</td><td>{{ $h['bloom'] }}</td><td>{{ $h['do_kho'] }}</td>
                <td>{{ $h['can'] }}</td><td>{{ $h['co'] }}</td>
                <td>{{ $h['khop'] ? 'Khớp' : 'Lệch' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
