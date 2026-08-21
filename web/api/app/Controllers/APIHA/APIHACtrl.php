<?php
declare(strict_types=1);
namespace tgui\Controllers\APIHA;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Controllers\Controller;
use tgui\Controllers\APIHA\HAGeneral;
use tgui\Controllers\APIHA\HAMaster;

use tgui\Services\CMDRun\CMDRun as CMDRun;

use Respect\Validation\Validator as v;

class APIHACtrl extends Controller
{

  public function getSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'ha',
      'action' => 'settings',
    ]);
    #check error#
    if ($_SESSION['error']['status']){
      $data['error']=$_SESSION['error'];
      return $this->json($response, $data, 401);
    }
    //INITIAL CODE////END//
    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1, true))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $data['db'] = $this->databaseHash()[0];
    $data['ha'] = $this->HAGeneral->getFullConfig();
    $data['slaves'] = $this->HAMaster->getSlaves();
		$data['master'] = $this->HASlave->getMaster();
    $data['rootpw_check'] = $this->HAGeneral->checkRoot();

    return $this->json($response, $data, 200);
  }

  public function postSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'post',
      'object' => 'ha',
      'action' => 'settings',
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

    $params = $this->params($request);

    $validation = $this->validator->validate($request, [
      'role' => v::oneOf(v::notEmpty(), v::numericVal()->between(0, 2)),
      'ip' => v::when( v::haRole() , v::ip()->setName('Master IP'), v::alwaysValid() ),
      'ip_m' => v::when( v::haRole($params['role'], 'slave') , v::notEmpty()->ip()->setName('Master IP'), v::alwaysValid() ),
      'psk' => v::when( v::haRole() , v::notEmpty()->setName('Preshared Key'), v::alwaysValid() ),
      'psk_s' => v::when( v::haRole($params['role'], 'slave') , v::notEmpty()->setName('Preshared Key'), v::alwaysValid() ),
      'slaves_ip' => v::when( v::haRole() , v::each(v::ip())->setName('Slaves IP'), v::alwaysValid() ),
      'emails' => v::when( v::haRole() , v::each(v::email())->setName('Emails'), v::alwaysValid() ),
    ]);

    if ($validation->failed()){
      $data['error']['status']=true;
      $data['error']['validation']=$validation->error_messages;
      return $this->json($response, $data, 200);
    }

    //$HA =  $this->HAGeneral;

    if ($this->HAGeneral->checkRoot()){
      $params['rootpw'] = $this->HAGeneral->getRootpw();
    }

    if (!$this->HAGeneral->checkRoot($params['rootpw'])){
      $data['error']['status']=true;
      $data['error']['validation']=['rootpw' => ['Incorrect MySQL Root Password!']];
      return $this->json($response, $data, 200);
    }

    switch ($params['role']) {
      case '1':
        unlink($this->HASlave->masterFile);
        $data['result'] = $this->HAMaster->setup($params);
        break;
      case '2':
        unlink($this->HAMaster->slavesFile);
        unlink($this->HASlave->masterFile);
        $data['result'] = $this->HASlave->setup($params);
        break;
      default:
        $data['result'] = $this->HAGeneral->setRootpw($params['rootpw'])->disable();
        break;
    }

    return $this->json($response, $data, 200);
  }

  public function getStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'ha',
      'action' => 'status',
    ]);
    #check error#
    if ($_SESSION['error']['status']){
      $data['error']=$_SESSION['error'];
      return $this->json($response, $data, 401);
    }
    //INITIAL CODE////END//
    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1, true))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $data['ha'] = $this->HAGeneral->getFullConfig();
    // $psk = (@$data['ha']['role'] == 1) ? $data['ha']['psk'] : $data['ha']['psk_s'];
    // $data['psk'] = $psk;
    $data['status'] = $this->HAGeneral->getStatus($data['ha']['role']);

    return $this->json($response, $data, 200);
  }

  public function postSlaveDel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'ha',
      'action' => 'status',
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

    $this->HAMaster->delSlave($this->param($request, 'ip'));

    return $this->json($response, $data, 200);
  }

  public function postSlaveUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'get',
      'object' => 'ha',
      'action' => 'slave upgrade',
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

    $allParams = $this->params($request);
    $this->HAGeneral->getFullConfig();

    $data['test'] = $allParams['ip'];
    $data['resp'] = $this->HAMaster->slaveRequest(
      $allParams['ip'],
      'upgrade',
      $this->HAGeneral->psk,
      $this->databaseHash()[0]
    );


    return $this->json($response, $data, 200);
  }

  public function postSlaveUpdateDo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = [ 'error' => false, 'messages' => [] ];

    $allParams = $this->params($request);

    $config = $this->HAGeneral->authorization($this->params($request));

    if (empty($config))
      return $this->json($response, [], 401);

    $data['upgrade'] = $this->APIUpdateCtrl->gitPull();

    return $this->json($response, $data, 200);
  }

  public function postInitFromSlave(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = [ 'error' => true, 'messages' => [] ];

    $allParams = $this->params($request);

    $config = $this->HAGeneral->authorization($this->params($request));
    if (empty($config))
      return $this->json($response, $allParams['api'].$allParams['action'].$allParams['dbHash'], 401);
      //return $this->json($response, [], 401);
    if ($allParams['api'] !== APIVER){
      $data['messages'][] = 'Different versions used!';
      return $this->json($response, $data, 200);
    }
    if (empty($allParams['key'])){
      $data['messages'][] = 'Where is a installation key?!';
      return $this->json($response, $data, 200);
    }

    switch ($allParams['action']) {
      case 'init':
        $data['messages'][] = 'Master Key: '.Controller::uuid_hash();
        $data['messages'][] = 'Slave Key: '. $allParams['key'];
        if (!$this->HAGeneral->checkActivation([ Controller::uuid_hash(), $allParams['key'] ])){
          $data['messages'][] = 'Error: Can\'t check activation';
          return $this->json($response, $data, 200);
        }
        $this->HAMaster->setSlave(['status' => 0, 'api' => $allParams['api'], 'db' => $allParams['dbHash']]);
        $data['mysql'] = $this->HAMaster->getMysqlParams($config['psk']);
        $data['sid'] = $this->HAMaster->makeSlaveId();
        $data['api'] = APIVER;
        $data['db'] = $this->databaseHash()[0];
        break;
      case 'dump':

        $this->HAMaster->setSlave(['status' => 1, 'api' => $allParams['api'], 'db' => $allParams['dbHash']]);
        if ( $this->HAMaster->makeDump() !== '1'){
          $data['messages'][] = 'Can not create dump file!';
          return $this->json($response, $data, 200);
        }
        $file = TAC_ROOT_PATH . '/temp/'.'tgui_dump.sql';
        header("X-Sendfile: $file");
        header("Content-type: application/octet-stream");
        header('Content-Disposition: attachment; filename="tgui_dump.sql"');
        exit(0);


      default:
        list($data['db'], $data['dbList']) = $this->databaseHash();
        $data['emails'] = $config['emails'];
        if ($data['db'] !== $allParams['dbHash']){
          $this->HAMaster->setSlave(['status' => 2, 'api' => $allParams['api'], 'db' => $allParams['dbHash']]);
          $data['messages'][] = 'Database error!';
          return $this->json($response, $data, 200);
        }
        $this->HAMaster->setSlave(['status' => 99, 'api' => $allParams['api'], 'db' => $allParams['dbHash']]);
        break;
    }

    $data['error'] = false;
    $this->changeConfigurationFlag(['unset' => 0]);

    return $this->json($response, $data, 200);
  }

  public function postCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = [ 'error' => true, 'messages' => [] ];

    $allParams = $this->params($request);

    $config = $this->HAGeneral->authorization($this->params($request));
    if (empty($config))
      return $this->json($response, $allParams['api'].$allParams['action'].$allParams['dbHash'].$_SERVER['REMOTE_ADDR'], 401);
      //return $this->json($response, [], 401);
    $data['db'] = $this->databaseHash()[0];
    $data['api'] = APIVER;
    $data['cfg'] = $config['cfg'];
    if ($config['role'] == 1)
      $this->HAMaster->setSlave(['status' => 99, 'api' => $allParams['api'], 'db' => $allParams['dbHash']]);
    if ($config['role'] == 2) {
      $data['status'] = $this->HASlave->status($this->HAGeneral->psk, 'brief');
      $this->HASlave->setMaster(['status' => 99, 'api' => $allParams['api'], 'db' => $allParams['dbHash'], 'emails' => $allParams['emails']]);
      if (!$data['status']) {
        if (!$this->HAGeneral->checkRoot())
          return $this->json($response, $data, 200);
        $config['rootpw'] = $this->HAGeneral->getRootpw();
        $data['result'] = $this->HASlave->setup($config);
      }
    }

    $data['error'] = false;

    return $this->json($response, $data, 200);
  }

  public function postLoggingEvent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = [ 'error' => false, 'messages' => [] ];

    $allParams = $this->params($request);

    $config = $this->HAGeneral->authorization($this->params($request));

    if (empty($config))
      return $this->json($response, [], 401);

    $data['cmd'] = CMDRun::init()->setCmd('php')->setAttr( [
      TAC_ROOT_PATH."/parser/parser.php",
      $allParams['action'],
      $allParams['entry'],
      $_SERVER['REMOTE_ADDR']
    ] )->showCmd();
    $data['done'] = CMDRun::init()->setCmd('php')->setAttr( [
      TAC_ROOT_PATH."/parser/parser.php",
      $allParams['action'],
      $allParams['entry'],
      $_SERVER['REMOTE_ADDR']
    ] )->get();

    return $this->json($response, $data, 200);
  }

  public function postApply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data = [ 'error' => true, 'messages' => [] ];

    $allParams = $this->params($request);

    $config = $this->HAGeneral->authorization($this->params($request));
    if (empty($config))
      return $this->json($response, [], 401);
    $data['apply'] = ['error'=>true, 'message'=>''];
    $data['test'] = ['error'=>true, 'message'=>''];
    $data['status'] = $this->HASlave->status($this->HAGeneral->psk, 'brief');
    $data['db'] = $this->databaseHash()[0];
    $data['api'] = APIVER;
    $data['cfg'] = $this->HAGeneral->config['cfg'];

    if (!$data['status']){
      $data['apply']['message'] .= "\n Status out of sync!";
      return $this->json($response, $data, 200);
    }

    if ($data['db'] != $allParams['dbHash']){
      $data['apply']['message'] .= "\n Database out of sync!";
      return $this->json($response, $data, 200);
    }

    if ($allParams['api'] != APIVER){
      $data['apply']['message'] .= "\n Version doesn't match!";
      return $this->json($response, $data, 200);
    }

    $data['test'] = $this->TACConfigCtrl->testConfiguration($this->TACConfigCtrl->createConfiguration());
    if ( ! $data['testStatus']['error'] )
      $data['apply'] = $this->TACConfigCtrl->applyConfiguration($this->TACConfigCtrl->createConfiguration());
    if (!$data['apply']['error'])
      $this->HAGeneral->setCfg();

    $this->HAGeneral->setCfg();
    $data['cfg'] = $this->HAGeneral->config['cfg'];


    return $this->json($response, $data, 200);
  }

}
