<?php

namespace Nfc036\ContaoFileUsageBundle\Module;

use \Contao\Input;
use \Contao\Image;
use \Contao\StringUtil;

class FileUsageHelper extends \Backend
{
	/**
	 * Return the file usage button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function showUsages($row, $href, $label, $title, $icon, $attributes)
	{
		if (\Contao\Input::get('popup'))
		{
			return '';
    }
    if ($row['type'] !== 'file') {
      return '';
    }
    $objFile = $this->Database->prepare("select * from tl_files where path=?")->execute($row['id'])->fetchAssoc();
    $linkTitle = '';
    if ($objFile['nfu_lastcheck'] > 0) {
      if ($objFile['nfu_count'] === 0) {
        $iconHtml = \Contao\Image::getHtml('folMinus.gif', 'Keine Referenzen gefunden, letzter Check '.$objFile['nfu_lastcheck']);
        $linkTitle = 'Keine Referenzen gefunden, letzter Check '.$objFile['nfu_lastcheck'];
      } else {
        $iconHtml = \Contao\Image::getHtml('folPlus.gif', sprintf('%d Referenzen gefunden, letzter Check '.$objFile['nfu_lastcheck'], $objFile['nfu_count']));
        $linkTitle = sprintf('%d Referenzen gefunden, letzter Check '.$objFile['nfu_lastcheck'], $objFile['nfu_count']);
      }
    } else {
      $iconHtml = \Contao\Image::getHtml('error.gif', 'File Usage Suche noch nicht ausgeführt');
      $linkTitle = 'Suche noch nie ausgeführt';
    }

		return '<a href="contao/popup.php?src=' . base64_encode($row['id']) . '" title="' . \Contao\StringUtil::specialchars($linkTitle) . '"' . $attributes . ' onclick="Backend.openModalIframe({\'title\':\'' . str_replace("'", "\\'", \Contao\StringUtil::specialchars($row['fileNameEncoded'])) . '\',\'url\':this.href});return false">' . $iconHtml . '</a> ';
	}

}