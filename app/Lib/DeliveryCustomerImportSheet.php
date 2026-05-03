<?php

declare(strict_types=1);

/**
 * 派送客户批量导入：XLSX/XLS 模板生成与解析（依赖 phpoffice/phpspreadsheet + php-zip）。
 */
final class DeliveryCustomerImportSheet
{
    public static function isAvailable(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
    }

    /**
     * 输出「派送客户导入模板」.xlsx（首行表头 + 第二行示例）。
     */
    public static function sendTemplateDownload(string $exampleConsigningCode): void
    {
        if (!self::isAvailable()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo '未加载 PhpSpreadsheet：请在项目根目录执行 composer install，并启用 PHP zip 扩展。';
            exit;
        }

        $headers = [
            '委托客户编码',
            '派送客户编号',
            '微信号',
            'Line',
            '收件人',
            '电话',
            '门牌号',
            '路（巷）',
            '村',
            '镇（街道）（乡）',
            '县（区）',
            '府',
            'Zipcode',
            '定位',
            '定位状态',
            '主路线',
            '副路线',
            '小区英文名',
            '小区泰文名',
            '客户状态',
            '客户要求',
        ];

        $exCc = $exampleConsigningCode;
        $row2 = [
            $exCc,
            'R001',
            'my_wx',
            'line_x',
            '张三',
            '0812345678',
            '99/1',
            'Soi Ramkhamhaeng',
            'Moo 5',
            'Hua Mak',
            'Bang Kapi',
            'Bangkok',
            '10240',
            '13.756331, 100.501765',
            '已定位',
            '主A',
            '副B',
            'TownEn',
            'ทาวน์ไทย',
            '正常',
            '需先电话联络后送货',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->fromArray($row2, null, 'A2', true);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="delivery_import_template.xlsx"; filename*=UTF-8\'\'%E6%B4%BE%E9%80%81%E5%AE%A2%E6%88%B7%E5%AF%BC%E5%85%A5%E6%A8%A1%E6%9D%BF.xlsx');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    /**
     * @return array{ok:bool, error:string, headers:list<string>, rows:list<list<string>>}
     */
    public static function loadMatrixFromUpload(string $tmpPath, string $origName): array
    {
        $empty = ['ok' => false, 'error' => '', 'headers' => [], 'rows' => []];
        if (!self::isAvailable()) {
            $empty['error'] = '服务器未安装 PhpSpreadsheet，请在项目根目录执行 composer install，并启用 php-zip 扩展。';

            return $empty;
        }
        if (!is_readable($tmpPath)) {
            $empty['error'] = '无法读取上传文件';

            return $empty;
        }
        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            $empty['error'] = '不支持的文件类型（请使用 .xlsx 或 .xls）';

            return $empty;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
        } catch (Throwable $e) {
            $empty['error'] = 'Excel 文件无法打开或已损坏';

            return $empty;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int)$sheet->getHighestDataRow();
        $highestColStr = (string)$sheet->getHighestDataColumn(1);
        if ($highestRow < 1 || $highestColStr === '') {
            $empty['error'] = '工作表为空';

            return $empty;
        }

        $maxCi = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColStr);
        if ($maxCi < 1) {
            $empty['error'] = '无法识别表头列';

            return $empty;
        }

        $headers = [];
        for ($ci = 1; $ci <= $maxCi; $ci++) {
            $headers[] = self::cellStringValue($sheet, $ci, 1);
        }
        while ($headers !== [] && trim((string)end($headers)) === '') {
            array_pop($headers);
        }
        if ($headers === []) {
            $empty['error'] = '表头为空';

            return $empty;
        }
        $maxCi = count($headers);

        $rows = [];
        for ($ri = 2; $ri <= $highestRow; $ri++) {
            $row = [];
            for ($ci = 1; $ci <= $maxCi; $ci++) {
                $row[] = self::cellStringValue($sheet, $ci, $ri);
            }
            $nonEmpty = false;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if (!$nonEmpty) {
                continue;
            }
            $rows[] = $row;
        }

        try {
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            // ignore
        }

        return ['ok' => true, 'error' => '', 'headers' => $headers, 'rows' => $rows];
    }

    private static function cellStringValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex1, int $rowIndex1): string
    {
        $cell = $sheet->getCell([$colIndex1, $rowIndex1]);
        $v = $cell->getValue();
        if ($v === null) {
            return '';
        }
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return trim($v->getPlainText());
        }
        if ($v instanceof \DateTimeInterface) {
            return trim($v->format('Y-m-d H:i:s'));
        }

        return trim((string)$v);
    }
}
