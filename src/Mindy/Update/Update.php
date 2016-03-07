<?php

namespace Mindy\Update;

use Exception;
use ZipArchive;

/**
 * Class Update
 * @package Mindy\Update
 */
class Update
{
    /**
     * @var string
     */
    public $repoUrl = '';
    /**
     * @var string absolute path to installation dir (Modules directory)
     */
    public $installDir = '';
    /**
     * @var string absolute path to download dir (Runtime dir)
     */
    public $downloadDir = '';
    /**
     * Cached requests
     * @var array
     */
    private $_cached = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        foreach($config as $key => $value) {
            $this->$key = $value;
        }

        $this->init();
    }

    /**
     * Override for your needs
     */
    public function init()
    {
        $this->prepareInstallDir();
        $this->prepareDownloadDir();
    }

    /**
     * Create install directory if doesnt exists and check permissions.
     * @throws \Exception
     */
    protected function prepareInstallDir()
    {
        $installDir = $this->getInstallDir();
        if(!is_dir($installDir) && !@mkdir($installDir, 0777, true)) {
            throw new Exception("Directory $installDir not found. Can't create directory");
        } else {
            /*
             * Check permissions
             */
            $installDir = $this->getInstallDir('test.txt');
            if(!@file_put_contents($installDir, 'test')) {
                throw new Exception("Please set 777 permissions for " . $this->getInstallDir() . " folder");
            } else {
                unlink($installDir);
            }
        }
    }

    /**
     * Create download directory if doesnt exists and check permissions.
     * @throws \Exception
     */
    protected function prepareDownloadDir()
    {
        $downloadDir = $this->getDownloadDir('');
        if(!is_dir($downloadDir) && !@mkdir($downloadDir, 0777, true)) {
            throw new Exception("Directory $downloadDir not found. Can't create directory");
        } else {
            /*
             * Check permissions
             */
            $downloadDir = $this->getDownloadDir('test.txt');
            if(!@file_put_contents($downloadDir, 'test')) {
                throw new Exception("Please set 777 permissions for " . $this->getDownloadDir() . " folder");
            } else {
                unlink($downloadDir);
            }
        }
    }

    /**
     * Prepare url
     * @param $name
     * @return string
     */
    protected function url($name)
    {
        return rtrim($this->repoUrl, '/') . '/' . $name . '/?format=json';
    }

    /**
     * @param $name
     * @return object
     */
    public function getInfo($name)
    {
        if(!isset($this->_cached[$name])) {
            $content = file_get_contents($this->url($name));
            $this->_cached[$name] = (object) json_decode($content);
        }

        return $this->_cached[$name];
    }

    /**
     * @param $name
     * @param $currentVersion
     * @return bool
     */
    public function checkNewVersion($name, $currentVersion)
    {
        $info = $this->getInfo($name);
        $needUpdate = false;
        foreach($info->versions as $version) {
            if($version->version >= $currentVersion) {
                $needUpdate = true;
                break;
            }
        }

        return $needUpdate;
    }

    /**
     * Install package
     * @param $name
     * @param bool $throw
     * @return bool
     * @throws \Exception
     */
    public function install($name, $throw = false)
    {
        $needVersion = null;
        $info = $this->getInfo($name);
        foreach($info->versions as $version) {
            $needVersion = $version;
            break;
        }

        $fileName = $this->getDownloadDir(basename($needVersion->file));
        $copied = file_put_contents($fileName, file_get_contents($needVersion->file));
        if(!$copied) {
            if($throw) {
                throw new Exception("Can't copy source file " . $needVersion->file . ". Please try again later.");
            } else {
                return false;
            }
        }

        $pathToSave = $this->getInstallDir($name);
        if(!$this->extractPackage($fileName, $pathToSave)) {
            if($throw) {
                throw new Exception("Can't extract source file " . $fileName . ' to ' . $pathToSave);
            } else {
                return false;
            }
        }

        return $needVersion->version;
    }

    /**
     * Update package to new version
     * @param $name
     * @param $currentVersion
     * @param null $updateToVersion
     * @param bool $throw
     * @return bool
     * @throws \Exception
     */
    public function update($name, $currentVersion, $updateToVersion = null, $throw = false)
    {
        if($updateToVersion !== null && $currentVersion >= $updateToVersion) {
            throw new Exception("Can't downgrade version.");
        }

        $needVersion = null;
        $info = $this->getInfo($name);
        foreach($info->versions as $version) {
            if($updateToVersion === null) {
                $needVersion = $version;
                break;
            } else if ($version->version == $updateToVersion) {
                $needVersion = $version;
                break;
            } else {
                continue;
            }
        }

        $fileName = $this->getDownloadDir(basename($needVersion->file));
        $copied = file_put_contents($fileName, file_get_contents($needVersion->file));
        if(!$copied) {
            if($throw) {
                throw new Exception("Can't copy source file " . $needVersion->file . ". Please try again later.");
            } else {
                return false;
            }
        }

        $pathToSave = $this->getInstallDir($name);
        if(!$this->extractPackage($fileName, $pathToSave)) {
            if($throw) {
                throw new Exception("Can't extract source file " . $fileName . ' to ' . $pathToSave);
            } else {
                return false;
            }
        }

        return $needVersion->version;
    }

    /**
     * Get absolute path to download dir
     * @param $name
     * @return string
     */
    public function getDownloadDir($name = '')
    {
        return rtrim($this->downloadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Get absolute path to installation dir
     * @param $name
     * @return string
     */
    public function getInstallDir($name = '')
    {
        return rtrim($this->installDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param $from
     * @param $to
     * @return bool
     */
    protected function extractPackage($from, $to)
    {
        $zip = new ZipArchive;
        $res = $zip->open($from);
        if ($res === true) {
            $zip->extractTo($to);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
}
