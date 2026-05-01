<?php

if (!function_exists('html_cell_tip_content')) {
    /**
     * 列表格内长文本：与主布局 `td.cell-tip` 及全站 JS 配合，单行省略、点击展开深色气泡。
     * 文本为空（trim 后）时输出占位符，不包一层可点击的 trigger。
     */
    /**
     * @param string|null $text
     * @param string $emptyDisplay
     * @return string
     */
    function html_cell_tip_content($text, $emptyDisplay = '—')
    {
        $t = trim((string) $text);
        if ($t === '') {
            return htmlspecialchars($emptyDisplay, ENT_QUOTES, 'UTF-8');
        }

        return '<span class="cell-tip-trigger" role="button" tabindex="0" title="点击展开全文">'
            . htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}
