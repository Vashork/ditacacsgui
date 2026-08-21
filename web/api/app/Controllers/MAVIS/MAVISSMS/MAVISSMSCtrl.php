<?php

declare(strict_types=1);

namespace tgui\Controllers\MAVIS\MAVISSMS;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use tgui\Models\TACUsers;
use tgui\Models\MAVISSMS;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;

class MAVISSMSCtrl extends Controller
{
	public function globalStatus()

	{
		return MAVISSMS::select('enabled')->first()->enabled;
	}
################################################
########	MAVIS SMS Parameters GET	###############START###########
	public function getSMSParams(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'mavis sms',
			'action' => 'parameters',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		$data['params']=MAVISSMS::select()->first();

		$data['params']['pass'] = $this->generateRandomString( 8 );

		return $this->json($response, $data, 200);
	}
########	MAVIS SMS Parameters GET	###############END###########
################################################
########	MAVIS SMS Parameters POST	###############START###########
	public function postSMSParams(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'mavis sms',
			'action' => 'parameters',
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
		if(!$this->checkAccess(11))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'port' => v::when( v::nullType() , v::alwaysValid(), v::numericVal())
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$data['mavis_sms_update'] = MAVISSMS::where([['id','=',1]])->update($allParams);

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'edit', 'obj_name' => 'MAVIS', 'obj_id' => 'SMS', 'section' => 'MAVIS SMS', 'message' => 703);

		return $this->json($response, $data, 200);
	}
########	MAVIS SMS Parameters POST	###############END###########
################################################
########	MAVIS SMS Send	###############START###########
	public function postSMSSend(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'mavis sms',
			'action' => 'send',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(11))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'port' => v::notEmpty()->numeric(),
			'ipaddr' => v::notEmpty()->oneOf( v::ip(), v::domain() ),
			'login' => v::notEmpty(),
			'srcname' => v::notEmpty(),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$username = $this->param($request, 'username');
		$number = $this->param($request, 'number');

		if ( !empty($username) )
		{
			$number = TACUsers::select('mavis_sms_number')->where([['username', '=', $username]])->first()->mavis_sms_number;
			if ($number == null)
			{
				$data['check_result']='Number for username '. $username . ' not found';
				return $this->json($response, $data, 200);
			}
			$data['number']=$number;
		} elseif ( !empty($number) ) {
			$data['number']=$number;
			$username = '';
		} else {
			$data['check_result']='Username or Number do not set';
			return $this->json($response, $data, 200);
		}

		$pass = MAVISSMS::select('pass')->where('id',1)->first()->pass;

		$link = "/main.sh check smpp-client 'number' ".
			'"'.$this->param($request, 'ipaddr').'" '.
			'"'.$this->param($request, 'port').'" '.
			'"true" '.
			'"'.$this->param($request, 'login').'" '.
			'"'.$pass.'" '.
			'"'.$this->param($request, 'srcname').'" '.
			'"'.$number.'" '.
			'"'.$username.'" '.
			' 2>&1';

		$data['link']=$link;

		$data['check_result']=shell_exec(TAC_ROOT_PATH . $link);

		return $this->json($response, $data, 200);
	}
########	MAVIS SMS Send	###############END###########
########	MAVIS SMS Check	###############START###########
	public function postSMSCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'mavis sms',
			'action' => 'check',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(11, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'test_username' => v::notEmpty(),
			'sms_password' => v::notEmpty()->numeric()
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['test_configuration'] = $this->TACConfigCtrl->testConfiguration($this->TACConfigCtrl->createConfiguration("\n "));

		$data['check_result']=shell_exec(TAC_ROOT_PATH . '/main.sh check mavis '.$this->param($request, 'test_username').' '.$this->param($request, 'sms_password').' 2>&1');

		return $this->json($response, $data, 200);
	}
########	MAVIS SMS Check	###############END###########
}//END OF CLASS//
