<?php
$cji = [
    'loading' => t('calendar.js.loading', '加载中...'),
    'loadFailed' => t('calendar.js.load_failed', '加载失败'),
    'logEmpty' => t('calendar.js.log_empty', '暂无状态变更记录'),
    'userLabel' => t('calendar.js.log_user', '用户'),
    'timeLabel' => t('calendar.js.log_time', '时间'),
    'statusDone' => t('calendar.js.status_done', '已完成'),
    'statusUndone' => t('calendar.js.status_undone', '未完成'),
    'progressWord' => t('calendar.js.progress_label', '进度'),
    'statusWord' => t('calendar.js.status_label', '状态'),
    'arrow' => t('calendar.js.arrow', '→'),
];
echo '<script>window.__calendarJsI18n=' . json_encode($cji, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
