<?php

declare(strict_types=1);

namespace tgui\Controllers\APIDownload;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Controllers\Controller;

use tgui\Services\CMDRun\CMDRun as CMDRun;

class APIDownloadCtrl extends Controller
{
  public function getDownloadCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
  	//INITIAL CODE////START//
  	$data=array();
  	$data=$this->initialData([
  		'type' => 'get',
  		'object' => 'download',
  		'action' => 'csv',
  	]);
  	#check error#
  	if ($_SESSION['error']['status']){
  		$data['error']=$_SESSION['error'];
  		return $this->json($response, $data, 401);
  	}
  	//INITIAL CODE////END//
     $data['clear'] = shell_exec( TAC_ROOT_PATH . '/main.sh delete temp');

     $file = str_replace("'","", urldecode( $this->param($request, 'file') ) );
 		if ( empty($file) ) {
 			echo '<h1>Error. File Parameter Inavailable</h1>';
 			return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
 		}
 		$path = TAC_ROOT_PATH . '/temp/';
 		//$path = '/backups/database/';

 		if ( !file_exists($path.$file) ) {
 			echo '<h1>Error. File '.$path . $file .' Not Found</h1>';
 			return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
 		}
 		$path = $path.$file;
 		header("X-Sendfile: $path");
 		header("Content-type: application/octet-stream");
 		header('Content-Disposition: attachment; filename="'.$file.'"');
 		exit(0);
 	}

  public function getDownloadLog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'download',
      'action' => 'csv',
    ]);
    #check error#
    if ($_SESSION['error']['status']){
      $data['error']=$_SESSION['error'];
      return $this->json($response, $data, 401);
    }
    //INITIAL CODE////END//

    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $file = str_replace("'","", urldecode( $this->param($request, 'file') ) );
    $filename = (!empty( $this->param($request, 'filename') )) ? str_replace("'","", urldecode( $this->param($request, 'filename') ) ) : '';
    if ( empty($file) ) {
      echo '<h1>Error. File Parameter Inavailable</h1>';
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = '/var/log/tacacsgui';
    //$path = '/backups/database/';

    if ( !file_exists($path.$file) ) {
      echo '<h1>Error. File '.$path . $file .' Not Found</h1>';
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = $path.$file;
    header("X-Sendfile: $path");
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'. ( (empty($filename)) ? $file : $filename) .'"');
    exit(0);
  }

  public function getDlCm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'download',
      'action' => 'configuration',
    ]);
    #check error#
    if ($_SESSION['error']['status']){
      $data['error']=$_SESSION['error'];
      return $this->json($response, $data, 401);
    }
    //INITIAL CODE////END//

    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $file = $filename = $this->param($request, 'name');
    $folder = $this->param($request, 'group');
    if ( $folder ) $file = $folder.'/'.$file;
    if ( empty($file) ) {
      echo '<h1>Error. File Parameter Inavailable</h1>'.$this->param($request, 'name').$this->param($request, 'group');
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = '/opt/tgui_data/confManager/configs/';
    //$path = '/backups/database/';

    if ( !file_exists($path.$file) ) {
      echo '<h1>Error. File '.$path . $file .' Not Found</h1>';
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = $path.$file;
    header("X-Sendfile: $path");
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'. ( (empty($filename)) ? $file : $filename) .'"');
    exit(0);
  }

  public function getCmHash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'download',
      'action' => 'configuration',
    ]);
    #check error#
    if ($_SESSION['error']['status']){
      $data['error']=$_SESSION['error'];
      return $this->json($response, $data, 401);
    }
    //INITIAL CODE////END//

    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $file = $this->param($request, 'show');
    $filename = $this->param($request, 'name');
    $hash = $this->param($request, 'hash');

    if ( empty($file) OR  empty($filename) OR empty($hash)) {
      echo '<h1>Error. File Parameter Inavailable</h1>'.$this->param($request, 'name').$this->param($request, 'show').$this->param($request, 'hash');
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = '/opt/tacacsgui/temp/';
    //$path = '/backups/database/';
    $filename = CMDRun::init()->setCmd(MAINSCRIPT)->
      setAttr(
        [
        'run',
        'cmd',
        '/opt/tacacsgui/plugins/ConfigManager/cm_git.sh',
        '--set-filename='.$filename,
        '--show-redirect='.$hash.':'.$file
        ])->
      get();
    if ( !$filename AND !file_exists($path.$filename) ) {
      echo '<h1>Error. File '.$path . $filename .' Not Found</h1>';
      return $response -> withStatus(404) -> withHeader('Content-type', 'text/html');
    }
    $path = $path.$filename;
    header("X-Sendfile: $path");
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'. $filename .'"');
    exit(0);
  }
}
