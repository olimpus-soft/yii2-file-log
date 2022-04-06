<?php
namespace OlimpusSoft;
use Yii;
use yii\log\Logger as yLog;
use \SplFileObject;
use \DateTime;
use \SplFileInfo;

/**
 * Logger class
 */
class Logger { //extends yLog
  protected $_levels;
  protected $_categories;
  protected $_except;
  protected $_logs;
  private static $maxFileSize = 25; //tamaño en Mb
  private static $maxLogsDays = 15;
  private static $basename = 'log';
  private static $method = 'log';

  const LEVEL_ERROR = yLog::LEVEL_ERROR;
  const LEVEL_WARNING = yLog::LEVEL_WARNING;
  const LEVEL_INFO = yLog::LEVEL_INFO;
  const LEVEL_TRACE = yLog::LEVEL_TRACE;
  const LEVEL_PROFILE = yLog::LEVEL_PROFILE;
  const LEVEL_PROFILE_BEGIN = yLog::LEVEL_PROFILE_BEGIN;
  const LEVEL_PROFILE_END = yLog::LEVEL_PROFILE_END;
  
  private static $log_styles = array(
    yLog::LEVEL_ERROR => array(
      'pre' => "<pre style='color: red;max-width: 100%; white-space: break-spaces;'>", 
      'pos' =>'</pre>'
    ),
    yLog::LEVEL_INFO => array(
      'pre' => "<pre>", 
      'pos' =>'</pre>'
    ),
    yLog::LEVEL_WARNING => array(
      'pre' => "<pre style='color: orange;max-width: 100%; white-space: break-spaces;'>", 
      'pos' =>'</pre>'
    ),
    yLog::LEVEL_PROFILE => array(
      'pre' => "<pre style='color: cyan;max-width: 100%; white-space: break-spaces;'>", 
      'pos' =>'</pre>'
    )
  );
  private static $log_strings = array(
    yLog::LEVEL_ERROR => 'ERROR',
    yLog::LEVEL_INFO => 'INFO',
    yLog::LEVEL_WARNING => 'WARNING',
    yLog::LEVEL_TRACE => 'TRACE',
    yLog::LEVEL_PROFILE => 'PROFILE',
  );

  public function __call($method, $args) {
    if(!function_exists($method)) {
      self::$method = $method;
      call_user_func_array(array($this, 'log'), $args);
    }
  }

  public static function __callStatic($method, $args) {
    if(!function_exists($method)) {
      self::$method = $method;
      call_user_func_array(array('self', 'log'), $args);
    }
  }

  static function moveOldLog(&$flog=null) {
    $basepath = Yii::getAlias('@runtime').DIRECTORY_SEPARATOR;
    $fname = $basepath.'oldlogs/app-'.(self::$basename).'-'.date('Y-m-d').'.txt';
    $logPath = $basepath.'logs/app-'.(self::$basename).'-'.date('Y-m-d').'.txt';
    try {
      $rotate = false;

      if(!is_dir($basepath.'logs')) {
        @mkdir($basepath.'logs', 0777, true);
      }
      if(file_exists($logPath)) {
        $fp = new SplFileInfo($logPath);
        $fz = round((($fp->getSize()/1024)/1024), 2);
        if($fz >= self::$maxFileSize) {
          $rotate = true;
        }
      }
      if(!is_dir($basepath.'oldlogs')) {
        @mkdir($basepath.'oldlogs', 0777, true);
      }
      if(!file_exists($fname) || $rotate == true) {
        $flog = new SplFileObject($logPath, 'a+');
        $msg = str_repeat('\\', 40).' ARCHIVO FINALIZADO: '. date('Y-m-d H:i:s O').' '.str_repeat('/', 40);
        self::prepareMSG($msg, Logger::LEVEL_INFO, __METHOD__);
        $flog->fwrite(PHP_EOL.PHP_EOL.$msg->text);
        $tail = 0;
        while (file_exists($fname)) {
          $tail++;          
          $fname = $basepath.'oldlogs/app-'.(self::$basename).'-'.date('Y-m-d').'.'.(str_pad($tail, 3, '0', STR_PAD_LEFT)).'.txt';
        }
        @rename($logPath, $fname);
      }
      if(!file_exists($logPath)) {
        $flog = new SplFileObject($logPath, 'a+');
        $msg = str_repeat('=', 40).' ARCHIVO CREADO: '. date('Y-m-d H:i:s O').' '.str_repeat('=', 40);
        self::prepareMSG($msg, Logger::LEVEL_INFO, __METHOD__);
        $flog->fwrite($msg->text.PHP_EOL.PHP_EOL);
      }
      $flog = new SplFileObject($logPath, 'a+');
      $h = date('H');
      if($h%6===0) {
        self::removeOldLogs($flog);
      }
    } catch (Exception $e) {
      $flog = new SplFileObject($logPath, 'a+');
      $msg = str_repeat('=', 40).' ARCHIVO CREADO: '. date('Y-m-d H:i:s O').' '.str_repeat('=', 40);
      self::prepareMSG($msg, Logger::LEVEL_INFO, __METHOD__);
      $flog->fwrite($msg->text.PHP_EOL.PHP_EOL);
      $flog->fwrite('HA OCURRIDO UN ERROR: '.$e->getMessage().' File: '.$e->getFile().' LINE: '.$e->getLine());
    }  
  }

  static function removeOldLogs(&$flog) {
    try {
      $maxLogsDays = Yii::$app->request->get('maxLogsDays', (isset(Yii::$app->params['maxLogsDays'])?Yii::$app->params['maxLogsDays']:self::$maxLogsDays));
      $logsAppDirs = array('oldlogs','logs','');
      foreach ($logsAppDirs as $kP => $vPath) {
        $oldLogs = Yii::getAlias('@runtime').DIRECTORY_SEPARATOR.$vPath;
        if(is_dir($oldLogs)) {
          $files = scandir($oldLogs);
          foreach ($files as $f => $file) {
            if(!in_array($file, array('.', '..'))) {
              $fp = new SplFileInfo($oldLogs.DIRECTORY_SEPARATOR.$file);
              $ext = explode(".", $file);
              $ext = end($ext);
              if((strstr($file, 'log') && in_array(strtolower($ext), array('log', 'txt'))) || strstr(strtolower($file), 'application.log')) {
                
                $files[$f] = $oldLogs.DIRECTORY_SEPARATOR.$file;
                $fTime = new DateTime(date('Y-m-d H:i:s', $fp->getATime()));
                $mTime = new DateTime(date('Y-m-d 00:00:00'));
                $mTime->modify(' - '.$maxLogsDays.' days');
                if($fTime < $mTime) {
                  $files[$f] = "{$oldLogs}/{$file}";
                  try {
                    if (file_exists($files[$f]) && unlink($files[$f])) {
                      $flog->fwrite(PHP_EOL."[Archivo borrado: <b>{$files[$f]}</b>]".PHP_EOL.PHP_EOL);
                    }                    
                  } catch (Exception $e) {
                    $flog->fwrite(PHP_EOL."[ ERROR: ]|[ ".$e->getMessage()." ]".PHP_EOL.PHP_EOL);
                  }
                }
              }
            } else {
              unset($files[$f]);
            }
          }
        }
      }
    } catch (Exception $e) {
      $basepath = Yii::getAlias('@runtime').DIRECTORY_SEPARATOR.'logs';
      $logPath = $basepath.(self::$basename).'.txt';
      $flog = new SplFileObject($logPath, 'a+');
      $flog->fwrite('[ ERROR '.__METHOD__.' ]|[ '.$e->getMessage().' ]'.PHP_EOL);
    }
  }

  public static function prepareMSG(&$msg,$level=Logger::LEVEL_INFO,$category='application', $bq=0) {
    if(!$msg) $msg = false;
    if(!is_string($msg)) {
      $msg = print_r($msg, true);
    }
    if(!strstr($category, 'system.')) {
      $category = 'system.'.$category;
    }
    $msg = '|'.ltrim($msg, '|');
    $inMsg = $msg;
    $action = Yii::$app->urlManager->parseRequest(Yii::$app->request);
    $action = explode('/', strtolower($action[0]));
    $action = end($action);
    $tailLogFile = trim(Yii::$app->request->get('tailLog',''));
    self::$basename = self::$method.($action=='index'||empty($action)?'':'-'.$action).(!empty($tailLogFile)?'-'.$tailLogFile:'');
    $levelStr = self::$log_strings[$level]?: 'INFO';
    $oMsg = strtoupper("[ Log <b>$levelStr</b> ]");
    $oMsg.= "|[ ".date('Y-m-d H:i:s O').' ]';
    //Habilitar en caso de auditorias
    $oMsg.= "<span style='display:none;'>|[ Remote IP: ".Yii::$app->request->userIP.' ]</span>';
    $oMsg.= "<span style='display:none;'>|[ {$category} ]</span>";
    $oMsg.= "<span style='display:none;'>|[ ".strtoupper(Yii::$app->name)." ]</span>";
    $oMsg.= '<span style="display:none;">|[ '.(defined('LOG_UUID') ? ' Request UUID '.LOG_UUID : getmypid()).' ]</span>';
    $oMsg.= '<span style="display:none;">[ Memoria Usada: '.self::getMemoryUsage(false, false).' ]|</span>';
    $oMsg.= '<span style="display:none;">[ Tiempo Empleado: '.self::getExecutionTime(2).' segs ]|</span>';
    if(isset(Yii::$app->user->identity)) {
      $oMsg.= "<span style='display:none;'>|[ Datos de usuario: ( login: ".(Yii::$app->user->identity->login)." ) ( id: ".(Yii::$app->user->identity->id)." )  ( profile_id: ".(Yii::$app->user->identity->profile_id)." ) ( email: ".(Yii::$app->user->identity->email)." ) ]</span>";
    } else {
      $oMsg.= "<span style='display:none;'>|[ Datos de usuario: ( login: NO LOGUEADO ) ( id: NO LOGUEADO )  ( profile_id: NO LOGUEADO )  ( email: NO LOGUEADO ) ]</span>";
    }
    $oMsg.= utf8_decode($msg);
    $msg = (object) array(
      'text' => ($bq>0?str_repeat("\t", $bq):'').str_replace("\n", "\n".($bq>0?str_repeat("\t", $bq):''), strip_tags($oMsg)).PHP_EOL.PHP_EOL,
      'html' => ($bq>0?str_repeat('<blockquote>', $bq):'').self::$log_styles[$level]['pre'].htmlentities($oMsg).self::$log_styles[$level]['pos'].($bq>0?str_repeat('</blockquote>', $bq):''),
    );

    if($level == Logger::LEVEL_ERROR) {
      Yii::error($msg->text, $level, $category);
    }
    if($level == Logger::LEVEL_WARNING) {
      Yii::warning($msg->text, $level, $category);
    }
    return $msg;
  }

  public static function log($msg, $level=Logger::LEVEL_INFO, $category='application', $bq=0, $echo=false, $writeLog=true) {
    self::$method = __FUNCTION__;
    self::prepareMSG($msg, $level, $category, $bq);
    self::moveOldLog($flog);    
    if($writeLog) {
      $flog->fwrite($msg->text);
    }
    if($echo && !defined('NOLOGGER')) {
      echo $msg->html;
    }
  }

  public static function logRef($msg, $level=Logger::LEVEL_INFO, $category='application', $bq=0, $echo=false, $writeLog=true) {
    self::$method = __FUNCTION__;
    self::prepareMSG($msg, $level, $category, $bq);
    self::moveOldLog($flog);    
    if($writeLog) {
      $flog->fwrite($msg->text);
    }
    if($echo && !defined('NOLOGGER')) {
      echo $msg->html;
    }
  }

  public static function logApi($msg, $level=Logger::LEVEL_INFO, $category='application', $bq=0, $echo=false, $writeLog=true) {
    self::$method = __FUNCTION__;
    self::prepareMSG($msg, $level, $category, $bq);
    self::moveOldLog($flog);
    if($writeLog) {
      $flog->fwrite($msg->text);
    }
    if($echo && !defined('NOLOGGER')) {
      echo $msg->html;
    }
  }

  public static function logDie($msg, $bq=0, $echo=false, $die=false) {
    Logger::log($msg ,Logger::LEVEL_INFO, "system.".__METHOD__.':'.__LINE__, $bq, false);
    if(!is_string($msg)) {
      $msg = print_r($msg, true);
    }
    $caller = debug_backtrace(1);
    $caller = array_filter(array_shift($caller));
    if($echo && !defined('NOLOGGER')) {
      echo ($bq>0?str_repeat('<blockquote style="max-width: 100%; white-space: break-spaces;">', $bq):'').'<pre style="width:calc(100% - 20px);border-radius:10px;background-color:#eef;padding:10px;max-width: 100%; white-space: break-spaces;">'.$msg.((isset($caller['file']) && isset($caller['line'])) ? '<br><pre style="max-width: 100%; white-space: break-spaces;"><small><code style="max-width: 100%; white-space: break-spaces;">File: '.$caller['file'].' / Line: '.$caller['line'].'</code></small></pre>':'').'</pre>'.($bq>0?str_repeat('</blockquote>', $bq):'');
    }
    if($die) die();
  }

  public static function getExecutionTime($decs=2) {
    return round(microtime(true)-YII_BEGIN_TIME, $decs);
  }

  public static function getMemoryUsage($nice=true, $unit = true) {
    $mem = 0;
    if(function_exists('memory_get_usage')) {
      $mem = memory_get_usage();
    } else {
      $output=array();
      if(strncmp(PHP_OS,'WIN',3)===0) {
        exec('tasklist /FI "PID eq ' . getmypid() . '" /FO LIST',$output);
        $mem = isset($output[5])?preg_replace('/[\D]/','',$output[5])*1024 : 0;
      } else {
        $pid=getmypid();
        exec("ps -eo%mem,rss,pid | grep $pid", $output);
        $output=explode("  ",$output[0]);
        $mem = isset($output[1]) ? $output[1]*1024 : 0;
      }
    }
    $units = array('bytes', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb', 'Zb', 'Yb', 'Bb' ,'Geb', 'Sb', 'Jb');
    $iun = 0;
    if($nice) {
      while ($mem >= 1024) {
        $mem = ($mem / 1024);
        $iun++;
      }
    }
    return round($mem, 2).($unit ? ' '.$units[$iun] : '');
  }
}