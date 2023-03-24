<?php

// Usage Updater
$GLOBALS['TL_CRON']['hourly'][] = [\Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper::class, 'updateFileUsages'];
$GLOBALS['TL_PURGE']['custom']['updateFileUsages'] = array('callback' => array('Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper', 'updateFileUsages'));
