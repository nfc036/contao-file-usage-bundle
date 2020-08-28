<?php

namespace Nfc036\ContaoFileUsageBundle\Module;

use \Contao\Input;
use \Contao\Image;
use \Contao\StringUtil;
use \Contao\Config;

class FileUsageHelper extends \Backend
{
  private static $FILTER = ['tl_files', 'tl_search', 'tl_search_index', 'tl_undo', 'tl_version', 'tl_log', 'tl_user', 'tl_user_group', 'tl_trusted_device'];

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
    $this->updateFiles();
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
      $zeit = date(\Contao\Config::get('datimFormat'), $objFile['nfu_lastcheck']);
      if ($objFile['nfu_count'] == 0) {
        $iconHtml = \Contao\Image::getHtml('folMinus.gif', 'Keine Referenzen gefunden, letzter Check '.$zeit);
        $linkTitle = 'Keine Referenzen gefunden, letzter Check '.$zeit;
      } else {
        $iconHtml = \Contao\Image::getHtml('folPlus.gif', sprintf('%d Referenzen gefunden, letzter Check '.$zeit, $objFile['nfu_count']));
        $linkTitle = sprintf('%d Referenzen gefunden, letzter Check '.$zeit, $objFile['nfu_count']);
      }
    } else {
      $iconHtml = \Contao\Image::getHtml('error.gif', 'File Usage Suche noch nicht ausgeführt');
      $linkTitle = 'Suche noch nie ausgeführt';
    }

		return '<a href="contao/popup.php?src=' . base64_encode($row['id']) . '" title="' . \Contao\StringUtil::specialchars($linkTitle) . '"' . $attributes . ' onclick="Backend.openModalIframe({\'title\':\'' . str_replace("'", "\\'", \Contao\StringUtil::specialchars($row['fileNameEncoded'])) . '\',\'url\':this.href});return false">' . $iconHtml . '</a> ';
  }
  
  public function updateFiles($count = 10) {
    $arrFiles = $this->Database->prepare(sprintf("SELECT * FROM tl_files WHERE type='file' and nfu_lastcheck is null or nfu_lastcheck < UNIX_TIMESTAMP() - 3600 order by nfu_lastcheck asc limit 0, %d", $count))
      ->execute()
      ->fetchAllAssoc();
    if (count($arrFiles) > 0) {
      // Ja, wir brauchen eine Info-Mail.
      foreach ($arrFiles as $row) {
        $arrUsages = $this->findReferences($row['id']);
        $this->Database->prepare("UPDATE tl_files set nfu_lastcheck=UNIX_TIMESTAMP(), nfu_count=?, nfu_items=? where id=?")
          ->execute(count($arrUsages), serialize($arrUsages), $row['id']);
      }
    }
  }

  public function findReferences($id)
  {
    $result = array();

    $objFile = \FilesModel::findByPk($id);
    #    print \StringUtil::binToUuid($objFile->uuid);
    #    return "";
    #   print_r($objFile);
    #   return "";

    $arrUsages = array();
    if ($objFile) {
      $arrTables = $this->Database->listTables();
      // remove tables we don't want to search in
      $arrTables = array_filter($arrTables, function ($table) {
        return !\in_array($table, self::$FILTER, true);
      });

      foreach ($arrTables as $strTable) {
        $arrFields = $this->Database->listFields($strTable);
        $usageInTable = array();

        foreach ($arrFields as $arrField) {
          if ('binary' === $arrField['type']) {
            $sql = sprintf("select id from %s where HEX(%s) like '%%%s%%'", $strTable, $arrField['name'], strtoupper(bin2hex($objFile->uuid)));
            #print $sql;
            $arrUsage = $this->Database->prepare($sql)->query()->fetchEach('id');
          } elseif ('blob' === $arrField['type'] || 'mediumblob' === $arrField['type']) {
            $sql = sprintf(
              "select id from `%s` where HEX(`%s`) like '%%%s%%' or `%s` like '%%%s%%'",
              $strTable,
              $arrField['name'],
              strtoupper(bin2hex($objFile->uuid)),
              $arrField['name'],
              \StringUtil::binToUuid($objFile->uuid)
            );
            #print $sql . ";\n";
            $arrUsage = $this->Database->prepare($sql)->query()->fetchEach('id');
          } elseif ('varchar' === $arrField['type'] || 'text' === $arrField['type'] || 'mediumtext' === $arrField['type'] || 'longtext' === $arrField['type']) {
            $sql = sprintf(
              "select id from `%s` where `%s` like '%%%s%%' or `%s` like '%%%s%%' or `%s` like '%%%s%%'",
              $strTable,
              $arrField['name'],
              strtoupper(bin2hex($objFile->uuid)),
              $arrField['name'],
              \StringUtil::binToUuid($objFile->uuid),
              $arrField['name'],
              $objFile->path
            );
            #print $sql . ";\n";
            $arrUsage = $this->Database->prepare($sql)->query()->fetchEach('id');
          } else {
            $arrUsage = array();
            #$result .= sprintf("%s.%s = %s<br>\n", $strTable, $arrField['name'], $arrField['type']);
          }
          if (is_array($arrUsage) && count($arrUsage) > 0) {
            $usageInTable = array_merge($usageInTable, $arrUsage);
          }
        } 
        if (count($usageInTable)>0) {
          $arrUsage = $this->Database->prepare(sprintf("select * from %s where id in (0, %s)", $strTable, join(", ", $usageInTable)))->query()->fetchAllAssoc();
          foreach ($arrUsage as $objUsage) {
            switch ($strTable) {
              case 'tl_content':
                $hl = unserialize($objUsage['headline']);
                $usage = array(
                  'title' => sprintf('Inhaltselement "%s"', $hl['value']),
                  'url' => sprintf("/contao?do=article&table=tl_content&id=%d&act=edit&rt=%s", $objUsage['id'], \RequestToken::get())
                );
                break;
              case 'tl_layout':
                $usage = array(
                  'title' => sprintf('Layout "%s"', $objUsage['name']),
                  'url' => sprintf("/contao?do=themes&table=tl_layout&id=%s&act=edit&rt=%s", $objUsage['id'], \RequestToken::get())
                );
                break;
              case 'tl_style':
                $usage = array(
                  'title' => sprintf('CSS-Selector "%s"', $objUsage['selector']),
                  'url' => sprintf("/contao?do=themes&table=tl_style&id=%d&act=edit&rt=%s", $objUsage['id'], \RequestToken::get())
                );
                break;
              case 'tl_module':
                $usage = array(
                  'title' => sprintf('Modul "%s"', $objUsage['name']),
                  'url' => sprintf("/contao?do=themes&table=tl_module&id=%d&act=edit&rt=%s", $objUsage['id'], \RequestToken::get())
                );
                break;
              case 'tl_news':
                $usage = array(
                  'title' => sprintf('News "%s"', $objUsage['headline']),
                  'url' => sprintf("/contao?do=news&table=tl_content&id=%d&rt=%s", $objUsage['id'], \RequestToken::get())
                );
                break;
              default:
                print "<h1>strTable=$strTable (evtl. Link definieren!?)</h1>";
                print "<pre>";
                print_r($objUsage);
                print "</pre>";
                $usage = $objUsage;
                break;
            }
            $arrUsages[] = $usage;
          }
        }
      }
      $result = array_merge($result, $arrUsages);
    }
    return $result;
  }

}