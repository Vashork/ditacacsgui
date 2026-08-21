<?php

declare(strict_types=1);

namespace tgui\Controllers\MAVIS\MAVISLocal;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use tgui\Models\MAVISLocal;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;

class MAVISLocalCtrl extends Controller
{
	public static function change_passwd_gui()
	{
		return MAVISLocal::select('change_passwd_gui')->where('change_passwd_gui',1)->count();
	}
################################################
########	MAVIS Local DB Parameters	###############START###########
	#########	GET Local DB Params	#########
	public function getParams(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'mavis local',
			'action' => 'get params',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		$data['params']=MAVISLocal::select()->first();

		return $this->json($response, $data, 200);
	}

	#########	POST Local DB Params	#########
	public function postParams(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'mavis local',
			'action' => 'set params',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $this->json($response, $data, 400);
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'enabled' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$data['mavis_local_update'] = MAVISLocal::where([['id','=',1]])->update($allParams);

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'edit', 'obj_name' => 'MAVIS', 'obj_id' => 'Local DB', 'section' => 'MAVIS Local DB', 'message' => 701);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	MAVIS Local DB Parameters	###############END###########
################################################
########	MAVIS Local DB Check	###############START###########
	public function postCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'mavis local',
			'action' => 'check',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(11, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'test_username' => v::notEmpty(),
			'test_password' => v::notEmpty()
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['test_configuration'] = $this->TACConfigCtrl->testConfiguration($this->TACConfigCtrl->createConfiguration("\n "));

		$data['check_result']=shell_exec(TAC_ROOT_PATH . '/main.sh check mavis '.$this->param($request, 'test_username').' '.$this->param($request, 'test_password').' 2>&1');

		return $this->json($response, $data, 200);
	}
########	MAVIS Local DB Check	###############END###########
}//END OF CLASS//
