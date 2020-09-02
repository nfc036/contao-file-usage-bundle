<?php

namespace Nfc036\ContaoFileUsageBundle\Module;

use Symfony\Component\HttpFoundation\Response;
use \Contao\Input;
use \Contao\Image;
use \Contao\StringUtil;
use \Contao\Config;
use \Contao\System;
use \Contao\Database;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Database\Result;
use Contao\Database\Statement;
use Contao\Date;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Idna;
use Contao\Model;
use Contao\Model\Collection;
use Contao\Model\QueryBuilder;
use Contao\RequestToken;

class FileUsageHelper extends Backend
{
  private static $FILTER = ['tl_files', 'tl_search', 'tl_search_index', 'tl_undo', 'tl_version', 'tl_log', 'tl_user', 'tl_user_group', 'tl_trusted_device'];

    /**
     * File.
     *
     * @var string
     */
    protected $strFile;

    /**
     * Database Instance.
     *
     * @var Database
     */
    private $database;

    /**
     * Initialize the controller.
     *
     * 1. Import the user
     * 2. Call the parent constructor
     * 3. Authenticate the user
     * 4. Load the language files
     * DO NOT CHANGE THIS ORDER!
     */
    public function __construct()
    {
        $this->import(BackendUser::class, 'User');
        parent::__construct();

        if (!System::getContainer()->get('security.authorization_checker')->isGranted('ROLE_USER')) {
            System::log('No Authentication', __METHOD__, TL_GENERAL);
            throw new AccessDeniedException('Access denied');
        }

        System::loadLanguageFile('default');

        $strFile = Input::get('src', true);
        $strFile = base64_decode($strFile, true);
        $strFile = ltrim(rawurldecode($strFile), '/');
        $this->strFile = $strFile;

        // get a handle to the database
        $this->database = Database::getInstance();

        if (!\is_array($GLOBALS['TL_CSS'])) {
            $GLOBALS['TL_CSS'] = [];
        }

        $GLOBALS['TL_CSS'][] = 'bundles/contaofileusage/css/contao-file-usage.css';

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');
        System::loadLanguageFile('tl_files');
    }

    public function runUpdate(): Response
    {
      $updated = 0;
      $count = 10;
      if (is_array($_GET) && array_key_exists('count', $_GET) && is_numeric($_GET['count']) && $_GET['count'] > 0) {
        $count = $_GET['count'];
      }
      $updated = $this->updateFiles($count);
      return \Symfony\Component\HttpFoundation\Response::create(sprintf("Für %d von %d Dateien wurden die Referenzen aktualisiert.", $updated, $count));
    }

    /**
     * Run the controller and parse the template.
     *
     * @return Response the http response of the controller
     */
    public function run(): Response
    {
        if ('' === $this->strFile) {
            die('No file given');
        }

        // Make sure there are no attempts to hack the file system
        if (preg_match('@^\.+@', $this->strFile) || preg_match('@\.+/@', $this->strFile) || preg_match('@(://)+@', $this->strFile)) {
            die('Invalid file name');
        }

        // Limit preview to the files directory
        if (!preg_match('@^'.preg_quote(Config::get('uploadPath'), '@').'@i', $this->strFile)) {
            die('Invalid path');
        }

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Check whether the file exists
        if (!file_exists($rootDir.'/'.$this->strFile)) {
            die('File not found');
        }

        // Check whether the file is mounted (thanks to Marko Cupic)
        if (!$this->User->hasAccess($this->strFile, 'filemounts')) {
            die('Permission denied');
        }

        // Open the download dialogue
        if (Input::get('download')) {
            $objFile = new File($this->strFile);
            $objFile->sendToBrowser();
        }

        /** @var BackendTemplate $objTemplate */
        $objTemplate = new BackendTemplate('be_fileusages');

        /** @var FilesModel $objModel */
        $objModel = FilesModel::findByPath($this->strFile);

        // Add the resource (see #6880)
        if (null === $objModel && Dbafs::shouldBeSynchronized($this->strFile)) {
            $objModel = Dbafs::addResource($this->strFile);
        }

        if (null !== $objModel) {
            $objTemplate->uuid = StringUtil::binToUuid($objModel->uuid); // see #5211
        }

        // Add the file info
        /** @var File $objFile */
        $objFile = new File($this->strFile);

        // Image
        if ($objFile->isImage) {
            $objTemplate->isImage = true;
            $objTemplate->width = $objFile->viewWidth;
            $objTemplate->height = $objFile->viewHeight;
            $objTemplate->src = $this->urlEncode($this->strFile);
            $objTemplate->dataUri = $objFile->dataUri;
        }

        // Meta data
        if (($objModel = FilesModel::findByPath($this->strFile)) instanceof FilesModel) {
            $arrMeta = StringUtil::deserialize($objModel->meta);

            if (\is_array($arrMeta)) {
                System::loadLanguageFile('languages');

                $objTemplate->meta = $arrMeta;
                $objTemplate->languages = (object) $GLOBALS['TL_LANG']['LNG'];
            }
        }
        $objTemplate->href = ampersand(Environment::get('request')).'&amp;download=1';
        $objTemplate->filesize = $this->getReadableSize($objFile->filesize).' ('.number_format($objFile->filesize, 0, $GLOBALS['TL_LANG']['MSC']['decimalSeparator'], $GLOBALS['TL_LANG']['MSC']['thousandsSeparator']).' Byte)';
        $arrUsages = [];
        $objDB = $this->database->prepare("select * from tl_files where path=?")->execute($this->strFile)->fetchAssoc();
        $this->findAndSaveReferences($objDB['id']);
        $objDB = $this->database->prepare("select * from tl_files where path=?")->execute($this->strFile)->fetchAssoc();
        $arrUsages = unserialize($objDB['nfu_items']);

        $objTemplate->usageCount = $objDB['nfu_count'];
        $objTemplate->usageLastCheck = Date::parse(Config::get('datimFormat'), $objDB['nfu_lastcheck']);
        $objTemplate->usages = $arrUsages;
        $objTemplate->icon = $objFile->icon;
        $objTemplate->mime = $objFile->mime;
        $objTemplate->ctime = Date::parse(Config::get('datimFormat'), $objFile->ctime);
        $objTemplate->mtime = Date::parse(Config::get('datimFormat'), $objFile->mtime);
        $objTemplate->atime = Date::parse(Config::get('datimFormat'), $objFile->atime);
        $objTemplate->path = StringUtil::specialchars($this->strFile);
        $objTemplate->theme = Backend::getTheme();
        $objTemplate->base = Environment::get('base');
        $objTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $objTemplate->title = StringUtil::specialchars($this->strFile);
        if (version_compare(VERSION, '4.9', '>=')) {
            $objTemplate->host = Backend::getDecodedHostname();
        } else {
            $objTemplate->host = static::getDecodedHostname();
        }
        $objTemplate->charset = Config::get('characterSet');
        $objTemplate->labels = (object) $GLOBALS['TL_LANG']['MSC'];
        $objTemplate->download = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['fileDownload']);

        return $objTemplate->getResponse();
    }

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
      $zeit = date(\Contao\Config::get('datimFormat'), $objFile['nfu_lastcheck']);
      if ($objFile['nfu_count'] == 0) {
        $iconHtml = \Contao\Image::getHtml('error.gif', 'Keine Referenzen gefunden, letzter Check '.$zeit);
        $linkTitle = 'Keine Referenzen gefunden, letzter Check '.$zeit;
      } else {
        $iconHtml = \Contao\Image::getHtml('sizes.gif', sprintf('%d Referenzen gefunden, letzter Check '.$zeit, $objFile['nfu_count']));
        $linkTitle = sprintf('%d Referenzen gefunden, letzter Check '.$zeit, $objFile['nfu_count']);
      }
    } else {
      $iconHtml = \Contao\Image::getHtml('reload.gif', 'File Usage Suche noch nicht ausgeführt');
      $linkTitle = 'Suche noch nie ausgeführt';
    }

		return '<a href="contao/fileUsage?src=' . base64_encode($row['id']) . '" title="' . \Contao\StringUtil::specialchars($linkTitle) . '"' . $attributes . ' onclick="Backend.openModalIframe({\'title\':\'' . str_replace("'", "\\'", \Contao\StringUtil::specialchars($row['fileNameEncoded'])) . '\',\'url\':this.href});return false">' . $iconHtml . '</a> ';
  }

  /**
   * Aktualisiert die angegebenen Anzahl von Dateien. Ruft findReferences jeweils
   * auf und schreibt die Rückgabewerte in die nfu-Spalten der Datenbank.
   * Eine Datei wird maximal einmal pro Stunde aufgerufen.
   */
  public function updateFiles($count = 10) {
    $updated = 0;
    $arrFiles = $this->Database->prepare(sprintf("SELECT * FROM tl_files WHERE type='file' and nfu_lastcheck is null or nfu_lastcheck < UNIX_TIMESTAMP() - 3600 order by nfu_lastcheck asc limit 0, %d", $count))
      ->execute()
      ->fetchAllAssoc();
    if (count($arrFiles) > 0) {
      // Ja, wir brauchen eine Info-Mail.
      foreach ($arrFiles as $row) {
        $updated += $this->findAndSaveReferences($row['id']);
      }
    }
    return $updated;
  }

  /**
   * Sucht die Referenzen und schreibt Ergebnis in Datenbank.
   */
  public function findAndSaveReferences($id) {
    $arrUsages = $this->findReferences($id);
    $statement = $this->Database->prepare("UPDATE tl_files set nfu_lastcheck=UNIX_TIMESTAMP(), nfu_count=?, nfu_items=? where id=?");
    $statement->execute(count($arrUsages), serialize($arrUsages), $id);
    return $statement->affectedRows;
  }

  /**
   * Sucht alle Referenzen auf die Datei mit der angegebenen ID und
   * liefert ein Array der Treffer (jeweils Hash mit url und title) zurück.
   */
  public function findReferences($id)
  {
    $result = array();

    $objFile = FilesModel::findByPk($id);
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
              StringUtil::binToUuid($objFile->uuid)
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
              StringUtil::binToUuid($objFile->uuid),
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
                  'url' => sprintf("/contao?do=article&table=tl_content&id=%d&act=edit&rt=%s", $objUsage['id'], RequestToken::get())
                );
                break;
              case 'tl_layout':
                $usage = array(
                  'title' => sprintf('Layout "%s"', $objUsage['name']),
                  'url' => sprintf("/contao?do=themes&table=tl_layout&id=%s&act=edit&rt=%s", $objUsage['id'], RequestToken::get())
                );
                break;
              case 'tl_style':
                $usage = array(
                  'title' => sprintf('CSS-Selector "%s"', $objUsage['selector']),
                  'url' => sprintf("/contao?do=themes&table=tl_style&id=%d&act=edit&rt=%s", $objUsage['id'], RequestToken::get())
                );
                break;
              case 'tl_module':
                $usage = array(
                  'title' => sprintf('Modul "%s"', $objUsage['name']),
                  'url' => sprintf("/contao?do=themes&table=tl_module&id=%d&act=edit&rt=%s", $objUsage['id'], RequestToken::get())
                );
                break;
              case 'tl_news':
                $usage = array(
                  'title' => sprintf('News "%s"', $objUsage['headline']),
                  'url' => sprintf("/contao?do=news&table=tl_content&id=%d&rt=%s", $objUsage['id'], RequestToken::get())
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