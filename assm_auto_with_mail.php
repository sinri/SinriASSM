<?php
// Check process with high cpu, and search in apache status
date_default_timezone_set('Asia/Shanghai');

$history=array();
$time_sep=5;
$cpu_limit_to_warn=20;

$username='';
$password='';

while(true){
	$ps_list=PSauxCPUMonitor::filterHighCPU($cpu_limit_to_warn);//Set as 20% CPU, if any processes go above this, shown.
	if(!empty($ps_list)){
		$pids=array_keys($ps_list);

		$html=ApacheServerStatusMonitor::readPage('https://example.com/server-status','curl',true,$username,$password);
		$process_list=ApacheServerStatusMonitor::parseApacheServerStatus($html);
		// echo "Current Process Count: ".count($process_list).PHP_EOL;

		$filter=array("type"=>"PID","pid_list"=>$pids);
		$process_list_filtered=ApacheServerStatusMonitor::filterLines($process_list,$filter);
		// echo "Filtered to ".count($process_list_filtered).PHP_EOL;

		if(!empty($process_list_filtered)){
			$current_time = date("Y-m-d H:i:s");
			echo "[$current_time]".PHP_EOL;

			$mail_content=array();

			foreach ($ps_list as $pid => $ps) {
				$ps_list[$pid]['apache']=(isset($process_list_filtered[$pid])?implode("\t", $process_list_filtered[$pid]):'');
				
				$time_parts=explode(':',$ps_list[$pid]['TIME']);
				if(count($time_parts)>1){
					$time_in_sec=$time_parts[0]*60+$time_parts[1];
				}else{
					$time_in_sec=60;
				}

				$ps_hash=$pid.'_'.$ps_list[$pid]['STARTED'];
				if(!empty($ps_list[$pid]['apache'])){
					$ps_hash.='_'.$process_list_filtered[$pid]['Method'].'_'.$process_list_filtered[$pid]['Request'];
				}
				$is_new_warn=pass_history($history,$ps_hash);

				echo "#PID: ".$pid.($is_new_warn?" !NEW-COME":"").' Up to '.$time_in_sec.' sec'.PHP_EOL;
				// echo "#HASH: ".$ps_hash.PHP_EOL;
				echo $ps_list[$pid]['PS'].PHP_EOL;
				echo $ps_list[$pid]['apache'].PHP_EOL;

				if($is_new_warn && $time_in_sec>5){
					//Mail Content append
					$line = "";
					$line .= "#PID: ".$pid.PHP_EOL;
					$line .= $ps_list[$pid]['PS'].PHP_EOL;
					$line .= $ps_list[$pid]['apache'].PHP_EOL;
					$mail_content[]=$line;
				}
			}

			if(!empty($mail_content)){
				$mail_content=implode(PHP_EOL, $mail_content);
				send_mail($mail_content);
			}
		}
	}else{
		// echo "No High CPU Process".PHP_EOL;
	}

	sleep($time_sep);
}

function pass_history(&$history,$ps_hash){
	if(!isset($history[$ps_hash])){
		$history[$ps_hash]=time();
		return true;
	}else{
		$old_time=$history[$ps_hash];
		$new_time=time();
		echo "PS_HASH: ".$ps_hash.PHP_EOL;
		echo "RECENT: ".$old_time.PHP_EOL;
		if($new_time-$old_time<2*$time_sep){
			$history[$ps_hash]=$new_time;
			echo "CONTINUE ".$new_time.PHP_EOL;
			return false;
		}else{
			$history[$ps_hash]=$new_time;
			echo "RENEW ".$new_time.PHP_EOL;
			return true;
		}
	}
}

/**
 * It is an example. You should realize the mailer with your own method.
 */
function send_mail($content){
	try{
		$emailUsername= 'sample@example.com';
		$emailPassword= 'sample';
		
		$mail = Helper_Mail::smtp();
	        
	    $mail->IsSMTP();                 
	    $mail->Host="smtp.exmail.qq.com";
	    $mail->SMTPAuth = true;
	    $mail->Username = $emailUsername;
	    $mail->Password = $emailPassword;
	    $mail->CharSet='UTF-8';

	    $to_list=array('sample@example.com');
	    foreach ($to_list as $address) {
	        $ll=explode('@', $address);
	        $mail->AddAddress($address, $ll[0]);
	    }
	    $mail->SetFrom($emailUsername, 'SinriMailer');
	    $mail->Subject = '[SystemWatch]ERP_HIGH_CPU_WARNING';
	    $mail->Body = "<pre>".PHP_EOL.$content.PHP_EOL."</pre>";
	    // $mail->IsHtml();

	    $sent=$mail->Send();
	    echo ($sent?'sent':'send not').PHP_EOL; 
    } catch (Exception $e) {
        echo "Exception: ".$e->getMessage().PHP_EOL;
    }
}

/**
* Read `ps aux` command result and filter with cpu percent.
*/
class PSauxCPUMonitor
{
	
	function __construct()
	{
		# code...
	}

	public static function filterHighCPU($warn_cpu_percent=25){
		$warn_cpu_percent=intval($warn_cpu_percent);
		$cmd="ps aux | awk '$3>".$warn_cpu_percent."'";
		// echo "Run PS Command: ".$cmd.PHP_EOL;
		// echo "Check any processes using beyond ".$warn_cpu_percent."% of Full CPU...".PHP_EOL;
		$raw=shell_exec($cmd);
		//USER              PID  %CPU %MEM      VSZ    RSS   TT  STAT STARTED      TIME COMMAND
		$raw_list=preg_split('/[\r\n]+/', $raw);
		$list=array();
		foreach ($raw_list as $raw_item) {
			$item=trim($raw_item);
			if(!empty($item)){
				$options=preg_split('/[ \t]+/', $item);

				//process_time
				$STARTED=$options[8];
				$TIME=$options[9];

				$ps=array('PID'=>$options[1],'STARTED'=>$STARTED,'TIME'=>$TIME,'PS'=>$item);
				$list[$options[1]]=$ps;
			}
		}
		return $list;
	}


}

/**
* Read Apache(2.2) Server Status Page and do some filter work.
*/
class ApacheServerStatusMonitor
{
	
	function __construct()
	{
		# code...
	}

	public static function readPage($url,$method="curl",$any_ssl=true,$basic_user="",$basic_password=""){
		if($method=='cmd'){
			$option="";
			if($any_ssl){
				$option.=" -k ";
			}
			if(!empty($basic_password) || !empty($basic_user)){
				$option.=" --user $basic_user:$basic_password ";
			}
			$cmd="curl $option $url";
			$html=shell_exec($cmd);
		}elseif($method=='curl'){
			$ch = curl_init($url);
			// HTTPヘッダを出力しない
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			// SSLバージョン3を利用する
			// curl_setopt($ch, CURLOPT_SSLVERSION, 3);
			// 返り値を文字列として受け取る
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			if($any_ssl){
				// サーバー証明書の検証をスキップ
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			}
			// HTTPステータスコード400の以上の場合も何も処理しない
			curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
			if(!empty($basic_password) || !empty($basic_user)){
				// Basic認証のユーザー名:パスワードをセット
				curl_setopt($ch, CURLOPT_USERPWD, $basic_user . ":" . $basic_password);
			}
			// サイトへアクセス
			$html = curl_exec($ch);
			 
			// HTTPステータスコードをチェックしエラーならエラー内容を出力
			if(curl_errno($ch)) {
			    echo 'Curl error: ' . curl_error($ch);
			}
			 
			// セッションをクローズ
			curl_close($ch);
		}else{
			$html=file_get_contents($url);
		}
		return $html;
	}

	public static function parseApacheServerStatus($html){
		$html=preg_replace('/[\r\n]+/', '', $html);
		$r=preg_match('/\<table border\="0"\>.+\<table\>/', $html,$m);
		if(!empty($m)){
			$html=$m[0];
		}else{
			throw new Exception("Cannot parse to certain list", 1);
		}

		$html=preg_replace('/\<\/tr\>/', PHP_EOL, $html);
		$html=preg_replace('/\<[^\>]+\>/', ' ', $html);

		$list=preg_split('/[\r\n]+/', $html);

		$result_list=array();

		foreach ($list as $line) {
			$line=trim($line);
			if(!empty($line)){
				$result=ApacheServerStatusMonitor::explainStatusLine($line);
				if($result){
					$result_list[]=$result;
				}
			}
		}
		return $result_list;
	}

	public static function explainStatusLine($line){
		if(empty($line)){
			return false;
		}
		//Srv	PID	Acc	M	CPU	SS	Req	Conn	Child	Slot	Client	VHost	Request
		$keys=array("Srv","PID","Acc","M","CPU","SS","Req","Conn","Child","Slot","Client","VHost","Method","Request","Protocol");
		$items=preg_split('/[ \t]+/', $line);

		$list=array();
		foreach ($keys as $key_id => $key) {
			if(isset($items[$key_id])){
				$list[$key]=$items[$key_id];
			}else{
				$list[$key]='';
			}
		}

		return $list;
	}

	public static function filterLines($lines,$filter){
		$filtered=array();
		foreach ($lines as $line) {
			if($filter['type']=='CPU_HIGHER'){
				$max_cpu=$filter['max_cpu'];

				if($line['CPU']*1.0>$max_cpu){
					$filtered[$line['PID']]=$line;
				}
			}
			elseif($filter['type']=='PID'){
				$pids=$filter['pid_list'];

				if(in_array($line['PID'], $pids)){
					$filtered[$line['PID']]=$line;
				}
			}
			else{
				$filtered[$line['PID']]=$line;
			}
		}
		return $filtered;		
	}
}
