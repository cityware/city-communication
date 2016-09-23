<?php

/**
 * DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * Version 2, December 2004
 *
 * Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>
 *
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 *
 * DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 *
 * 0. You just DO WHAT THE FUCK YOU WANT TO.
 */

namespace Cityware\Communication\Ftp;

/**
 * Ftp class for recursive upload and download files via ftp protocol
 *
 * @author Kristian Feldsam - iKFSystems, info@ikfsystems.sk
 * @web	http://www.ikfsystems.sk
 */
class Client {

    private $conn, $login_result, $logData, $nameLog, $ftpUser, $ftpPass, $ftpHost, $retry, $ftpPasv, $ftpMode, $verbose, $logPath, $createMask;
    private $ftpTimeout = 90;
    private $ftpPort = 21;
    public $ftpSsl = false;
    public $system_type;

    /**
     * Construct method
     *
     * @param	array	keys[passive_mode(true|false)|transfer_mode(FTP_ASCII|FTP_BINARY)|reattempts(int) |log_path|verbose(true|false)|create_mask(default:0777)]
     * @return void
     */
    public function __construct(array $o = null) {
        $this->retry = (isset($o['reattempts'])) ? $o['reattempts'] : 3;
        $this->ftpPasv = (isset($o['passive_mode'])) ? $o['passive_mode'] : true;
        $this->ftpMode = (isset($o['transfer_mode'])) ? $o['transfer_mode'] : FTP_BINARY;
        $this->verbose = (isset($o['verbose'])) ? $o['verbose'] : false;
        $this->logPath = (isset($o['log_path'])) ? $o['log_path'] : false;
        $this->createMask = (isset($o['create_mask'])) ? $o['create_mask'] : 0777;
    }

    /**
     * Connection method
     *
     * @param	string	hostname
     * @param	string	username
     * @param	string	password
     * @return void
     */
    public function conn($hostname, $username, $password) {
        $this->ftpUser = $username;
        $this->ftpPass = $password;
        $this->ftpHost = $hostname;

        $this->initConn();
    }

    /**
     * Init connection method - connect to ftp server and set passive mode
     *
     * @return bool
     */
    public function initConn() {
        // check if non-SSL connection
        if (!$this->ftpSsl) {
            // attempt connection
            if (!$this->conn = ftp_connect($this->ftpHost, $this->ftpPort, $this->ftpTimeout)) {
                // set last error
                $this->logData("Failed to connect to {$this->ftpHost}", 'error');
                return false;
            }
            // SSL connection
        } elseif (function_exists("ftp_ssl_connect")) {
            // attempt SSL connection
            if (!$this->conn = ftp_ssl_connect($this->ftpHost, $this->ftpPort, $this->ftpTimeout)) {
                // set last error
                $this->logData("Failed to connect to {$this->ftpHost} (SSL connection)", 'error');
                return false;
            }
            // invalid connection type
        } else {
            $this->logData("Failed to connect to {$this->ftpHost} (invalid connection type)", 'error');
            return false;
        }

        // attempt login
        if (ftp_login($this->conn, $this->ftpUser, $this->ftpPass)) {
            // set passive mode
            ftp_pasv($this->conn, (bool) $this->ftpPasv);

            // set system type
            $this->system_type = ftp_systype($this->conn);

            // connection successful
            return true;
            // login failed
        } else {
            $this->logData("Failed to connect to {$this->ftpHost} (login failed)", 'error');
            return false;
        }

        /*
          $this->conn = ftp_connect($this->ftpHost);
          $this->login_result = ftp_login($this->conn, $this->ftpUser, $this->ftpPass);
          if ($this->conn && $this->login_result) {
          ftp_pasv($this->conn, $this->ftpPasv);

          return true;
          }

          return false;
         * 
         */
    }

    /**
     * Upload method - upload files(folders) to ftp server
     *
     * @param	string	path to destionation file/folder on ftp
     * @param	string	path to source file/folder on local disk
     * @param	int	only for identify reattempt, dont use this param
     * @return bool
     */
    public function upload($destinationFile, $sourceFile, $retry = 0) {
        if (file_exists($sourceFile)) {
            if (!$this->isDir($sourceFile, true)) {
                $this->createSubDirs($destinationFile);
                if (!ftp_put($this->conn, $destinationFile, $sourceFile, $this->ftpMode)) {
                    $retry++;
                    if ($retry > $this->retry) {
                        $this->logData('Error when uploading file: ' . $sourceFile . ' => ' . $destinationFile, 'error');

                        return false;
                    }
                    if ($this->verbose) {
                        echo 'Retry: ' . $retry . "\n";
                    }
                    $this->reconnect();
                    $this->upload($destinationFile, $sourceFile, $retry);
                } else {
                    $this->logData($sourceFile . ' => ' . $destinationFile, 'ok');

                    return true;
                }
            } else {
                $this->recursive($destinationFile, $sourceFile, 'put');
            }
        }
    }

    /**
     * Download method - download files(folders) from ftp server
     *
     * @param	string	path to destionation file/folder on local disk
     * @param	string	path to source file/folder on ftp server
     * @param	int	only for identify reattempt, dont use this param
     * @return bool
     */
    public function download($destinationFile, $sourceFile, $retry = 0) {
        if (!$this->isDir($sourceFile, false)) {
            if ($this->verbose) {
                echo $sourceFile . ' => ' . $destinationFile . "\n";
            }
            $this->createSubDirs($destinationFile, false, true);
            if (!ftp_get($this->conn, $destinationFile, $sourceFile, $this->ftpMode)) {
                $retry++;
                if ($retry > $this->retry) {
                    $this->logData('Error when downloading file: ' . $sourceFile . ' => ' . $destinationFile, 'error');

                    return false;
                }
                if ($this->verbose) {
                    echo 'Retry: ' . $retry . "\n";
                }
                $this->reconnect();
                $this->download($destinationFile, $sourceFile, $retry);
            } else {
                $this->logData($sourceFile . ' => ' . $destinationFile, 'ok');

                return true;
            }
        } else {
            $this->recursive($destinationFile, $sourceFile, 'get');
        }
    }

    /**
     * Make dir method - make folder on ftp server or local disk
     *
     * @param	string	path to destionation folder on ftp or local disk
     * @param	bool	true for local, false for ftp
     * @return bool
     */
    public function makeDir($dir) {
        ftp_mkdir($this->conn, $dir);
        return ftp_chmod($this->conn, $this->createMask, $dir);
    }

    /**
     * Remove directory on FTP server
     *
     * @param string $directory
     * @return bool
     */
    public function rmdir($directory = null) {
        // attempt remove dir
        if (ftp_rmdir($this->conn, $directory)) {
            // success
            return true;
            // fail
        } else {
            $this->logData("Failed to remove directory \"{$directory}\"", 'error');
            return false;
        }
    }

    /**
     * Cd up method - change working dir up
     * 
     * @return bool
     */
    public function cdUp() {
        return ftp_cdup($this->conn);
    }

    /**
     * List contents of dir method - list all files in specified directory
     *
     * @param	string	path to destionation folder on ftp or local disk
     * @return bool
     */
    public function listFiles($file) {
        if (!$this->isDir($file)) {
            return false;
        }
        if (!preg_match('/\//', $file)) {
            return ftp_nlist($this->conn, $file);
        } else {
            $dirs = explode('/', $file);
            foreach ($dirs as $dir) {
                $this->changeDir($dir);
            }
            $last = count($dirs) - 1;
            $this->cdUp();
            $list = ftp_nlist($this->conn, $dirs[$last]);
            $i = 0;
            foreach ($dirs as $dir) {
                if ($i < $last) {
                    $this->cdUp();
                }
                $i++;
            }

            return $list;
        }
    }

    /**
     *
     * @param  type    $old_file
     * @param  type    $new_file
     * @return boolean

      public function rename($old_file, $new_file) {
      if (ftp_rename($this->conn, $old_file, $new_file)) {
      return true;
      } else {
      return false;
      }
      } */

    /**
     * Rename file on FTP server
     *
     * @param string $old_name
     * @param string $new_name
     * @return bool
     */
    public function rename($old_name = null, $new_name = null) {
        // attempt rename
        if (ftp_rename($this->conn, $old_name, $new_name)) {
            // success
            return true;
            // fail
        } else {
            $this->logData("Failed to rename file \"{$old_name}\"", 'error');
            return false;
        }
    }

    /**
     * Delete file on FTP server
     *
     * @param string $remote_file
     * @return bool
     */
    public function delete($remote_file = null) {
        // attempt to delete file
        if (ftp_delete($this->conn, $remote_file)) {
            // success
            return true;
            // fail
        } else {
            $this->logData("Failed to delete file \"{$remote_file}\"", 'error');
            return false;
        }
    }

    /**
     * Returns current working directory
     *
     * @param	bool	true for local, false for ftp
     * @return bool
     */
    public function pwd() {
        return ftp_pwd($this->conn);
    }

    /**
     * Change current working directory
     *
     * @param	string	dir name
     * @param	bool	true for local, false for ftp
     * @return bool
     */
    public function changeDir($dir) {
        return ftp_chdir($this->conn, $dir);
    }

    /**
     * Create subdirectories
     *
     * @param	string	path
     * @param	bool
     * @param	bool	true for local, false for ftp
     * @param	bool	change current working directory back
     * @return void
     */
    public function createSubDirs($file, $last = false, $chDirBack = true) {
        if (preg_match('/\//', $file)) {
            $origin = $this->pwd();
            if (!$last) {
                $file = substr($file, 0, strrpos($file, '/'));
            }
            $dirs = explode('/', $file);
            foreach ($dirs as $dir) {
                if (!$this->isDir($dir)) {
                    $this->makeDir($dir);
                    $this->changeDir($dir);
                } else {
                    $this->changeDir($dir);
                }
            }
            if ($chDirBack) {
                $this->changeDir($origin);
            }
        }
    }

    /**
     * Recursion
     *
     * @param	string	destionation file/folder
     * @param	string	source file/folder
     * @param	string	put or get
     * @return void
     */
    public function recursive($destinationFile, $sourceFile, $mode) {
        $local = ($mode == 'put') ? true : false;
        $list = $this->listFiles($sourceFile, $local);
        if ($this->verbose) {
            echo "\n" . 'Folder: ' . $sourceFile . "\n";
        }
        if ($this->verbose) {
            print_r($list);
        }
        foreach ($list as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $destFile = $destinationFile . '/' . $file;
            $srcFile = $sourceFile . '/' . $file;
            if ($this->isDir($srcFile, $local)) {
                $this->recursive($destFile, $srcFile, $mode);
            } else {
                if ($local) {
                    $this->upload($destFile, $srcFile);
                } else {
                    $this->download($destFile, $srcFile);
                }
            }
        }
    }

    /**
     * Check if is dir
     *
     * @param	string	path to folder
     * @return bool
     */
    public function isDir($dir) {
        if ($this->changeDir($dir)) {
            return $this->cdUp(0);
        }

        return false;
    }

    /**
     * Save log data to array
     *
     * @param	string	data
     * @param	string	type(error|ok)
     * @return void
     */
    public function logData($data, $type) {
        $this->logData[$type][] = $data;
    }

    /**
     * Get log data array
     *
     * @return array
     */
    public function getLogData() {
        return $this->logData;
    }

    /**
     * Save log data to file
     *
     * @return void
     */
    public function logDataToFiles() {
        if (!$this->logPath) {
            return false;
        }
        $this->makeDir($this->logPath, true);
        $log = $this->getLogData();
        $sep = "\n" . date('y-m-d H-i-s') . ' ';
        $logc = date('y-m-d H-i-s') . ' ' . join($sep, $log['error']) . "\n";
        $this->addToFile($this->logPath . '/' . $this->nameLog . '-error.log', $logc);
        $logc = date('y-m-d H-i-s') . ' ' . join($sep, $log['ok']) . "\n";
        $this->addToFile($this->logPath . '/' . $this->name . '-ok.log', $logc);
    }

    /**
     * Reconnect method
     *
     * @return void
     */
    public function reconnect() {
        $this->closeConn();
        $this->initConn();
    }

    /**
     * Close connection method
     *
     * @return void
     */
    public function closeConn() {
        return ftp_close($this->conn);
    }

    /**
     * Write to file
     *
     * @param	string	path to file
     * @param	string	text
     * @param	string	fopen mode
     * @return void
     */
    public function addToFile($file, $ins, $mode = 'a') {
        $fp = fopen($file, $mode);
        fwrite($fp, $ins);
        fclose($fp);
    }

    /**
     * Destruct method - close connection and save log data to file
     *
     * @return void
     */
    public function __destruct() {
        $this->closeConn();
        $this->logDataToFiles();
    }

}
