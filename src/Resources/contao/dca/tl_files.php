<?php

/**
 * Tabelle um zusätzliche Felder (Funde und Zeitpunkt der letzten Suche) erweitern
 */

$GLOBALS['TL_DCA']['tl_files']['fields']['nfu_lastcheck'] = array(
  'sql' => "int(10) unsigned NOT NULL default 0"
);

$GLOBALS['TL_DCA']['tl_files']['fields']['nfu_count'] = array(
  'sql' => "int(10) unsigned NOT NULL default 0"
);

$GLOBALS['TL_DCA']['tl_files']['fields']['nfu_items'] = array(
  'sql' => "blob NULL"
);

$usageOperation = array(
  'href' => 'act=fileUsage',
  'button_callback' => array('Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper', 'showUsages')
);

/**
 * Usage Icon nach "show" einbinden
 */
$result = array();
foreach ($GLOBALS['TL_DCA']['tl_files']['list']['operations'] as $key => $operation) {
  $result[$key] = $operation;
  if ($key == 'show') {
    $result['fileusage'] = $usageOperation;
  }
}
$GLOBALS['TL_DCA']['tl_files']['list']['operations'] = $result;
