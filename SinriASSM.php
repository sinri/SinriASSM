<?php
// Check process with high cpu, and search in apache status

$ps_list=PSauxCPUMonitor::filterHighCPU(20);//Set as 20% CPU, if any processes go above this, shown.
if(!empty($ps_list)){
	$pids=array_keys($ps_list);

	$html=ApacheServerStatusMonitor::readPage('https://example.com/server-status','curl');
	$process_list=ApacheServerStatusMonitor::parseApacheServerStatus($html);
	echo "Current Process Count: ".count($process_list).PHP_EOL;

	$filter=array("type"=>"PID","pid_list"=>$pids);
	$process_list_filtered=ApacheServerStatusMonitor::filterLines($process_list,$filter);
	echo "Filtered to ".count($process_list_filtered).PHP_EOL;

	foreach ($ps_list as $pid => $ps) {
		$ps_list[$pid]['apache']=(isset($process_list_filtered[$pid])?implode("\t", $process_list_filtered[$pid]):'');
		echo "#PID: ".$pid.PHP_EOL;
		echo $ps_list[$pid]['PS'].PHP_EOL;
		echo $ps_list[$pid]['apache'].PHP_EOL;
	}
}else{
	echo "No High CPU Process".PHP_EOL;
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
		echo "Check any processes using beyond ".$warn_cpu_percent."% of Full CPU...".PHP_EOL;
		$raw=shell_exec($cmd);
		//USER              PID  %CPU %MEM      VSZ    RSS   TT  STAT STARTED      TIME COMMAND
		$raw_list=preg_split('/[\r\n]+/', $raw);
		$list=array();
		foreach ($raw_list as $raw_item) {
			$item=trim($raw_item);
			if(!empty($item)){
				$options=preg_split('/[ \t]+/', $item);

				$ps=array('PID'=>$options[1],'PS'=>$item);
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
