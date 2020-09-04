<?php

// Usage Updater
$GLOBALS['TL_CRON']['hourly']['updateFileUsages'] = array('Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper', 'updateFileUsages');
$GLOBALS['TL_PURGE']['custom']['updateFileUsages'] = array('callback' => array('Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper', 'updateFileUsages'));