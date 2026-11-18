<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Bộ tạo file Word (.docx) tối giản, không cần thư viện ngoài (chỉ cần phần mở rộng PHP zip).
 * Hỗ trợ: đoạn văn nhiều kiểu chữ, bảng có/không viền, ngắt trang, số trang ở chân trang.
 */
class DocxBuilder
{
    private string $thanTai = '';

    public function __construct(private readonly string $phongChu = 'Times New Roman', private readonly int $coChu = 26) {}

    /**
     * @param  string|list<array{0: string, 1?: string}>  $noiDung  chuỗi, hoặc danh sách [văn bản, kiểu] với kiểu chứa b (đậm), i (nghiêng), u (gạch chân)
     * @param  array{canh?: string, thut?: int, treo?: int, truoc?: int, sau?: int, giuVoiTiep?: bool, coChu?: int, ngatTrangTruoc?: bool, vienDuoi?: bool}  $tuyChon
     */
    public function doan(string|array $noiDung, array $tuyChon = []): void
    {
        $this->thanTai .= $this->xmlDoan($noiDung, $tuyChon);
    }

    public function ngatTrang(): void
    {
        $this->thanTai .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    /**
     * @param  list<list<array{noiDung: string|array, tuyChon?: array, tieuDe?: bool}>>  $hang  mỗi ô là mảng gồm noiDung (hoặc danh sách đoạn trong key 'cacDoan')
     * @param  list<int>  $doRong  độ rộng cột (twip, tổng khoảng 9500)
     */
    public function bang(array $hang, array $doRong, bool $vien = true): void
    {
        $vienXml = $vien
            ? '<w:tblBorders>'.implode('', array_map(fn ($c) => "<w:{$c} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>", ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])).'</w:tblBorders>'
            : '';

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($doRong).'" w:type="dxa"/>'.$vienXml
            .'<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="40" w:type="dxa"/><w:left w:w="90" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr>';
        $xml .= '<w:tblGrid>'.implode('', array_map(fn ($w) => "<w:gridCol w:w=\"{$w}\"/>", $doRong)).'</w:tblGrid>';

        foreach ($hang as $cacO) {
            $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            foreach ($cacO as $i => $o) {
                $nen = ! empty($o['tieuDe']) ? '<w:shd w:val="clear" w:color="auto" w:fill="E5E7EB"/>' : '';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.($doRong[$i] ?? 1000).'" w:type="dxa"/>'.$nen.'</w:tcPr>';
                $cacDoan = $o['cacDoan'] ?? [[$o['noiDung'] ?? '', $o['tuyChon'] ?? []]];
                foreach ($cacDoan as [$nd, $tc]) {
                    $xml .= $this->xmlDoan($nd, $tc + ['truoc' => 0, 'sau' => 0]);
                }
                $xml .= '</w:tc>';
            }
            $xml .= '</w:tr>';
        }
        $xml .= '</w:tbl>';
        // Đoạn trống nhỏ để hai bảng liền nhau không dính vào nhau
        $this->thanTai .= $xml.'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="120" w:lineRule="exact"/></w:pPr></w:p>';
    }

    public function luu(string $duongDan): void
    {
        $zip = new ZipArchive;
        if ($zip->open($duongDan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file Word tạm.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            .'</Relationships>');

        $f = htmlspecialchars($this->phongChu, ENT_XML1);
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr>'
            ."<w:rFonts w:ascii=\"{$f}\" w:hAnsi=\"{$f}\" w:cs=\"{$f}\" w:eastAsia=\"{$f}\"/><w:sz w:val=\"{$this->coChu}\"/><w:szCs w:val=\"{$this->coChu}\"/><w:lang w:val=\"vi-VN\"/>"
            .'</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:before="0" w:after="60" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style></w:styles>');

        $zip->addFromString('word/footer1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve">Trang </w:t></w:r>'
            .$this->truongTrang('PAGE').'<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve"> / </w:t></w:r>'.$this->truongTrang('NUMPAGES')
            .'</w:p></w:ftr>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
            .$this->thanTai
            .'<w:sectPr><w:footerReference w:type="default" r:id="rId2"/><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1418" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');

        $zip->close();
    }

    private function truongTrang(string $ten): string
    {
        return '<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
            ."<w:r><w:rPr><w:sz w:val=\"20\"/></w:rPr><w:instrText xml:space=\"preserve\"> {$ten} </w:instrText></w:r>"
            .'<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t>1</w:t></w:r>'
            .'<w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>';
    }

    private function xmlDoan(string|array $noiDung, array $tc): string
    {
        $ppr = '';
        if (! empty($tc['giuVoiTiep'])) {
            $ppr .= '<w:keepNext/>';
        }
        if (! empty($tc['ngatTrangTruoc'])) {
            $ppr .= '<w:pageBreakBefore/>';
        }
        if (! empty($tc['vienDuoi'])) {
            $ppr .= '<w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="000000"/></w:pBdr>';
        }
        $ppr .= '<w:spacing w:before="'.($tc['truoc'] ?? 0).'" w:after="'.($tc['sau'] ?? 60).'"/>';
        if (isset($tc['thut']) || isset($tc['treo'])) {
            $ppr .= '<w:ind w:left="'.($tc['thut'] ?? 0).'"'.(isset($tc['treo']) ? ' w:hanging="'.$tc['treo'].'"' : '').'/>';
        }
        $canh = ['trai' => 'left', 'giua' => 'center', 'phai' => 'right', 'deu' => 'both'][$tc['canh'] ?? 'trai'] ?? 'left';
        $ppr .= "<w:jc w:val=\"{$canh}\"/>";

        $cacDoan = is_string($noiDung) ? [[$noiDung, '']] : $noiDung;
        $xml = '';
        foreach ($cacDoan as $doan) {
            $kieu = $doan[1] ?? '';
            $rpr = (str_contains($kieu, 'b') ? '<w:b/><w:bCs/>' : '').(str_contains($kieu, 'i') ? '<w:i/><w:iCs/>' : '').(str_contains($kieu, 'u') ? '<w:u w:val="single"/>' : '');
            if (isset($tc['coChu'])) {
                $rpr .= "<w:sz w:val=\"{$tc['coChu']}\"/><w:szCs w:val=\"{$tc['coChu']}\"/>";
            }
            $dongs = preg_split('/\R/u', (string) $doan[0]);
            $r = '';
            foreach ($dongs as $i => $dong) {
                $r .= ($i > 0 ? '<w:br/>' : '').'<w:t xml:space="preserve">'.$this->thoat($dong).'</w:t>';
            }
            $xml .= '<w:r>'.($rpr !== '' ? "<w:rPr>{$rpr}</w:rPr>" : '').$r.'</w:r>';
        }

        return "<w:p><w:pPr>{$ppr}</w:pPr>{$xml}</w:p>";
    }

    /** Bỏ ký tự điều khiển không hợp lệ trong XML, rồi thoát ký tự đặc biệt. */
    private function thoat(string $chuoi): string
    {
        $chuoi = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $chuoi) ?? '';

        return htmlspecialchars($chuoi, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
