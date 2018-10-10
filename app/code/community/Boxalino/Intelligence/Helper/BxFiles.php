<?php


/**
 * Class BxFiles
 * @package Boxalino\Intelligence\Helper
 */
class Boxalino_Intelligence_Helper_BxFiles
{
    /**
     * @var string
     */
    public $XML_DELIMITER = ',';

    /**
     * @var string
     */
    public $XML_ENCLOSURE = '"';

    /**
     * @var string
     */
    public $XML_ENCLOSURE_TEXT = "&quot;"; // it's $XML_ENCLOSURE

    /**
     * @var string
     */
    public $XML_NEWLINE = '\n';

    /**
     * @var string
     */
    public $XML_ESCAPE = '\\\\';

    /**
     * @var string
     */
    public $XML_ENCODE = 'UTF-8';

    /**
     * @var string
     */
    public $XML_FORMAT = 'CSV';

    /**
     * @var array
     */
    protected $_attributesWithIds = array();

    /**
     * @var array
     */
    protected $_allTags = array();

    /**
     * @var array
     */
    protected $_countries = array();

    /**
     * @var array language code
     */
    protected $_languages = array(
        'en',
        'fr',
        'de',
        'it',
        'es',
        'zh',
        'cz',
        'ru',
    );

    /**
     * @var null
     */
    protected $_mainDir = null;

    /**
     * @var null
     */
    protected $_dir = null;

    /**
     * @var
     */
    protected $account;

    /**
     * @var
     */
    protected $type;

    /**
     * @var array
     */
    protected $_files = array();

    /**
     * @var array
     */
    private $filesMtM = array();

    /**
     * @param string $account
     * @param string $type
     */
    public function init($account = 'undefined', $type = 'full')
    {
        $this->account = $account;
        $this->type = $type;

        $this->_mainDir = Mage::getBaseDir() . "/var/tmp/boxalino";
        $this->_dir = $this->_mainDir . DS . $this->account . DS . $this->type;

        if (file_exists($this->_dir)) {
            $this->delTree($this->_dir);
        }
        mkdir($this->_dir, 0777 , true);
        return $this;
    }

    /**
     * @param $dir
     * @return bool|void
     */
    public function delTree($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                self::delTree("$dir/$file");
            } else if (file_exists("$dir/$file")) {
                @unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }

    /**
     * @param $file
     * @return string
     */
    public function getPath($file) {
        if (!file_exists($this->_dir)) {
            mkdir($this->_dir);
        }

        //save
        if (!in_array($file, $this->_files)) {
            $this->_files[] = $file;
        }

        return $this->_dir . '/' . $file;
    }

    /**
     * @param $file
     * @param $data
     */
    public function savePartToCsv($file, &$data)
    {
        $path = $this->getPath($file);

        $fh = fopen($path, 'a');
        foreach ($data as $dataRow) {
            fputcsv($fh, $dataRow, $this->XML_DELIMITER, $this->XML_ENCLOSURE);
        }
        fclose($fh);
        $data = null;
        $fh = null;
    }

    /**
     * @param $file
     */
    public function printFile($file) {
        $path = $this->getPath($file);
        echo file_get_contents($path);
    }

    /**
     * @param $files
     */
    public function prepareProductFiles($files) {

        foreach ($files as $attrs) {
            foreach($attrs as $attr){
                $key = $attr['attribute_code'];

                if ($attr['attribute_code'] == 'categories') {
                    $key = 'category';
                }

                if (!file_exists($this->_dir)) {
                    mkdir($this->_dir);
                }
                $file = 'product_' . $attr['attribute_code'] . '.csv';

                //save
                if (!in_array($file, $this->_files)) {
                    $this->_files[] = $file;
                }

                $fh = fopen($this->_dir . '/' . $file, 'a');
                $this->filesMtM[$attr['attribute_code']] = $fh;
            }
        }
    }

    /**
     * removing empty files from the exporter path
     *
     * @param null $fileNamePattern
     */
    public function clearEmptyFiles($fileNamePattern = null)
    {
        $files = array_diff(scandir($this->_dir), ['..','.']);
        foreach ($files as $file)
        {
            $filePath = $this->_dir . "/" . $file;
            if(filesize($filePath))
            {
                continue;
            }

            if(!is_null($fileNamePattern) && (substr($file, 0, strlen($fileNamePattern)) === $fileNamePattern))
            {
                @unlink($filePath);
                continue;
            }

            @unlink($filePath);
        }
    }
}
