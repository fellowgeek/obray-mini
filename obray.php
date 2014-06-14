<?php require_once("settings.php");error_reporting(E_ALL);ini_set('display_errors',true);session_set_cookie_params(0);session_start();function getDatabaseConnection(){global $conn;if(!isSet($conn)){try{$conn=new PDO('mysql:host='.__OBRAY_DATABASE_HOST__.';dbname='.__OBRAY_DATABASE_NAME__.';charset=utf8',__OBRAY_DATABASE_USERNAME__,__OBRAY_DATABASE_PASSWORD__,array(PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8'));$conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}catch(PDOException $e){echo 'ERROR: '.$e->getMessage();exit();}}return $conn;}function removeSpecialChars($string,$space='',$amp=''){$string=str_replace(' ',$space,$string);$string=str_replace('&',$amp,$string);$string=preg_replace('/[^a-zA-Z0-9\-_s]/','',$string);return $string;}Class OObject{private $delegate=FALSE;private $starttime;private $is_error=FALSE;private $status_code=200;private $content_type='application/json';private $path='';private $missing_path_handler;private $missing_path_handler_path;private $access;public $object='';public function route($path,$params=array(),$direct=TRUE){if(!$direct){$params=array_merge($params,$_GET,$_POST);}$cmd=$path;$components=parse_url($path);if(isSet($components['host'])&&$direct){$ch=curl_init();if(defined('__OBRAY_REMOTE_HOSTS__')&&defined('__OBRAY_TOKEN__')&&in_array($components['host'],unserialize(__OBRAY_REMOTE_HOSTS__))){curl_setopt($ch,CURLOPT_HTTPHEADER,array('obray-token: '.__OBRAY_TOKEN__));}$timeout=5;curl_setopt($ch,CURLOPT_URL,$path);curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);curl_setopt($ch,CURLOPT_POST,count($params));curl_setopt($ch,CURLOPT_POSTFIELDS,$params);$this->data=curl_exec($ch);$content_type=curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$this->data=json_decode($this->data);if(!empty($this->data)){if(isSet($this->data->errors)){$this->errors=$this->data->errors;}if(isSet($this->data->html)){$this->html=$this->data->html;}if(isSet($this->data->data)){$this->data=$this->data->data;}}}else{if(isSet($components['query'])){parse_str($components['query'],$tmp);$params=array_merge($tmp,$params);}$_REQUEST=$params;$path_array=preg_split('[/]',$components['path'],NULL,PREG_SPLIT_NO_EMPTY);$base_path=$this->getBasePath($path_array);$this->validateRemoteApplication($direct);if(!empty($base_path)){$obj=$this->createObject($path_array,$path,$base_path,$params,$direct);if(isSet($obj)){return $obj;}}else if(count($path_array)==1){return $this->executeMethod($path,$path_array,$direct,$params);}else{return $this->findMissingRoute($components['path'],$params);}}return $this;}private function validateRemoteApplication(&$direct){$headers=getallheaders();if(isSet($headers['obray-token'])){$otoken=$headers['obray-token'];unset($headers['obray-token']);if(defined('__OBRAY_TOKEN__')&&$otoken===__OBRAY_TOKEN__&&__OBRAY_TOKEN__!=''){$direct=TRUE;}}}private function createObject($path_array,$path,$base_path,&$params,$direct){$path='';while(count($path_array)>0){$obj_name=array_pop($path_array);$this->path=$base_path.implode('/',$path_array).'/'.$obj_name.'.php';if(file_exists($this->path)){require_once $this->path;if(!class_exists($obj_name)){$this->throwError('File exists, but could not find object: $obj_name',404,'notfound');return $this;}else{try{$obj=new $obj_name();$obj->setObject(get_class($obj));$obj->setContentType($obj->content_type);$params=array_merge($obj->checkPermissions('object',$direct),$params);if(method_exists($obj,'setDatabaseConnection')){$obj->setDatabaseConnection(getDatabaseConnection());}$obj->route($path,$params,$direct);}catch(Exception $e){$this->throwError($e->getMessage());}return $obj;}break;}else{$path='/'.$obj_name;}}$this->throwError('Route not fount object: '.$path,404,'notfound');return $this;}private function executeMethod($path,$path_array,$direct,&$params){$path=$path_array[0];if(method_exists($this,$path)){try{$params=array_merge($this->checkPermissions($path,$direct),$params);if(!$this->isError()){$this->$path($params);}}catch(Exception $e){$this->throwError($e->getMessage());}return $this;}else{return $this->findMissingRoute($path,$params);}}private function checkPermissions($object_name,$direct){$params=array();if(!$direct){$perms=$this->getPermissions();if(!isSet($perms[$object_name])){$this->throwError('You cannot access this resource.',403,'Forbidden');}else if(($perms[$object_name]==='user'&&!isSet($_SESSION['ouser']))||(is_int($perms[$object_name])&&!isSet($_SESSION['ouser']))){if(isSet($_SERVER['PHP_AUTH_USER'])&&isSet($_SERVER['PHP_AUTH_PW'])){$login=$this->route('/obray/OUsers/login/',array('ouser_email'=>$_SERVER['PHP_AUTH_USER'],'ouser_password'=>$_SERVER['PHP_AUTH_PW']),TRUE);if(!isSet($_SESSION['ouser'])){$this->throwError('You cannot access this resource.',401,'Unauthorized');}}else{$this->throwError('You cannot access this resource.',401,'Unauthorized');}}else if(is_int($perms[$object_name])&&isSet($_SESSION['ouser'])&&$_SESSION['ouser']->ouser_permission_level>$perms[$object_name]){$this->throwError('You cannot access this resource.',403,'Forbidden');}if(isSet($perms[$object_name])&&$perms[$object_name]==='user'&&isSet($_SESSION['ouser'])){$params['ouser_id']=$_SESSION['ouser']->ouser_id;}}return $params;}public function parsePath($path){$path=preg_split('([\][?])',$path);if(count($path)>1){parse_str($path[1],$params);}else{$params=array();}$path=$path[0];$path_array=preg_split('[/]',$path,NULL,PREG_SPLIT_NO_EMPTY);$path='/';$routes=unserialize(__OBRAY_ROUTES__);if(!empty($path_array)&&isSet($routes[$path_array[0]])){$base_path=$routes[array_shift($path_array)];}else{$base_path='';}return array('path_array'=>$path_array,'path'=>$path,'base_path'=>$base_path,'params'=>$params);}private function getBasePath(&$path_array){$routes=unserialize(__OBRAY_ROUTES__);if(!empty($path_array)&&isSet($routes[$path_array[0]])){$base_path=$routes[array_shift($path_array)];}else{$base_path='';}return $base_path;}public function cleanUp(){$keys=['object','errors','data','runtime','html','recordcount'];if(__OBRAY_DEBUG_MODE__){$keys[]='sql';$keys[]='filter';}foreach($this as $key=>$value){if(!in_array($key,$keys)){unset($this->$key);}}}public function isObject($path){$components=$this->parsePath($path);$obj_name=array_pop($components['path_array']);if(count($components['path_array'])>0){$seperator='/';}else{$seperator='';}$path=$components['base_path'].implode('/',$components['path_array']).$seperator.$obj_name.'.php';if(file_exists($path)){require_once $path;if(class_exists($obj_name)){return TRUE;}}return FALSE;}private function findMissingRoute($path,$params){if(isSet($this->missing_path_handler)){include $this->missing_path_handler_path;$obj=new $this->missing_path_handler();$obj->setObject($this->missing_path_handler);$obj->setContentType($obj->content_type);$params=array_merge($obj->checkPermissions('object',FALSE),$params);if(method_exists($obj,'setDatabaseConnection')){$obj->setDatabaseConnection(getDatabaseConnection());}$obj->missing('/'.ltrim(rtrim($path,'/'),'/').'/',$params,FALSE);return $obj;}return $this;}public function throwError($message,$status_code=500,$type='general'){$this->is_error=TRUE;if(empty($this->errors)||!is_array($this->errors)){$this->errors=[];}$this->errors[$type][]=$message;$this->status_code=$status_code;}public function isError(){return $this->is_error;}private function setObject($obj){$this->object=$obj;}public function getStatusCode(){return $this->status_code;}public function getContentType(){return $this->content_type;}public function setContentType($type){if($this->content_type!='text/html'){$this->content_type=$type;}}public function getPermissions(){return $this->permissions;}public function setMissingPathHandler($handler,$path){$this->missing_path_handler=$handler;$this->missing_path_handler_path=$path;}}Class ODBO extends OObject{public $dbh;private $primary_key_column;private $data_types;private $enable_column_additions=TRUE;private $enable_column_removal=TRUE;private $enable_data_type_changes=TRUE;public function __construct(){if(!isSet($this->table)){$this->table='';}if(!isSet($this->table_definition)){$this->table_definition=array();}if(!isSet($this->primary_key_column)){$this->primary_key_column='';}if(!defined('__OBRAY_DATATYPES__')){define('__OBRAY_DATATYPES__',serialize(array('varchar'=>array('sql'=>' VARCHAR(size) COLLATE utf8_general_ci ','my_sql_type'=>'varchar(size)','validation_regex'=>''),'mediumtext'=>array('sql'=>' MEDIUMTEXT COLLATE utf8_general_ci ','my_sql_type'=>'mediumtext','validation_regex'=>''),'text'=>array('sql'=>' TEXT COLLATE utf8_general_ci ','my_sql_type'=>'text','validation_regex'=>''),'integer'=>array('sql'=>' int ','my_sql_type'=>'int(11)','validation_regex'=>'/^([0-9])*$/'),'float'=>array('sql'=>' float ','my_sql_type'=>'float','validation_regex'=>'/[0-9\.]*/'),'boolean'=>array('sql'=>' boolean ','my_sql_type'=>'boolean','validation_regex'=>''),'datetime'=>array('sql'=>' datetime ','my_sql_type'=>'datetime','validation_regex'=>''),'password'=>array('sql'=>' varchar(255) ','my_sql_type'=>'varchar(255)','validation_regex'=>''))));}}public function setDatabaseConnection($dbh){$this->dbh=$dbh;if(!isSet($this->table)||$this->table==''){return;}if(__OBRAY_DEBUG_MODE__){$this->scriptTable();$this->alterTable();}}public function scriptTable($params=array()){$sql='CREATE DATABASE IF NOT EXISTS '.__OBRAY_DATABASE_NAME__.';';$statement=$this->dbh->prepare($sql);$statement->execute();if(empty($this->dbh)){return $this;}$sql='';$data_types=unserialize(__OBRAY_DATATYPES__);forEach($this->table_definition as $name=>$def){if(array_key_exists('store',$def)==FALSE||(array_key_exists('store',$def)==TRUE&&$def['store']==TRUE)){if(!empty($sql)){$sql.=',';}if(isSet($def['data_type'])){$data_type=$this->getDataType($def);$sql.=$name.str_replace('size',str_replace(')','',$data_type['size']),$data_types[$data_type['data_type']]['sql']);}if(array_key_exists('primary_key',$def)&&$def['primary_key']===TRUE){$this->primary_key_column=$name;$sql.=$name.' INT UNSIGNED NOT NULL AUTO_INCREMENT ';}}}$sql='CREATE TABLE IF NOT EXISTS '.$this->table.' ( '.$sql;$sql.=', OCDT DATETIME, OCU INT UNSIGNED, OMDT DATETIME, OMU INT UNSIGNED ';if(!empty($this->primary_key_column)){$sql.=', PRIMARY KEY ('.$this->primary_key_column.') ) ENGINE='.__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; ';}$this->sql=$sql;$statement=$this->dbh->prepare($sql);$this->script=$statement->execute();}public function alterTable(){if(empty($this->dbh)){return $this;}$this->dump();$sql='DESCRIBE '.$this->table.';';$statement=$this->dbh->prepare($sql);$statement->execute();$statement->setFetchMode(PDO::FETCH_OBJ);$data=$statement->fetchAll();$temp_def=$this->table_definition;$obray_fields=array(3=>'OCDT',4=>'OCU',5=>'OMDT',6=>'OMU');forEach($obray_fields as $of){unset($this->table_definition[$of]);}$data_types=unserialize(__OBRAY_DATATYPES__);forEach($data as $def){if(array_key_exists('store',$def)==FALSE||(array_key_exists('store',$def)==TRUE&&$def['store']==TRUE)){if(array_search($def->Field,$obray_fields)===FALSE){if(isSet($this->table_definition[$def->Field])){if($this->enable_data_type_changes&&isSet($this->table_definition[$def->Field]['data_type'])){$data_type=$this->getDataType($this->table_definition[$def->Field]);if(str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['my_sql_type'])!=$def->Type){if(!isSet($this->table_alterations)){$this->table_alterations=array();}$sql='ALTER TABLE '.$this->table.' MODIFY COLUMN '.$def->Field.' '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']);$statement=$this->dbh->prepare($sql);$this->table_alterations[]=$statement->execute();}}unset($this->table_definition[$def->Field]);}else{if($this->enable_column_removal&&isSet($_REQUEST['enableDrop'])){if(!isSet($this->table_alterations)){$this->table_alterations=array();}$sql='ALTER TABLE '.$this->table.' DROP COLUMN '.$def->Field.' ';$statement=$this->dbh->prepare($sql);$this->table_alterations[]=$statement->execute();}}}}}if($this->enable_column_additions){forEach($this->table_definition as $key=>$def){if(array_key_exists('store',$def)==FALSE||(array_key_exists('store',$def)==TRUE&&$def['store']==TRUE)){if(!isSet($this->table_alterations)){$this->table_alterations=array();}$data_type=$this->getDataType($def);$sql='ALTER TABLE '.$this->table.' ADD ('.$key.' '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']).')';$statement=$this->dbh->prepare($sql);$this->table_alterations[]=$statement->execute();}}}$this->table_definition=$temp_def;}public function getTableDefinition(){$this->data=$this->table_definition;}private function getWorkingDef(){$this->required=array();forEach($this->table_definition as $key=>$def){if(isSet($def['required'])&&$def['required']==TRUE){$this->required[$key]=TRUE;}if(isSet($def['primary_key'])){$this->primary_key_column=$key;}if(isSet($def['parent'])&&$def['parent']==TRUE){$this->parent_column=$key;}if(isSet($def['slug_key'])&&$def['slug_key']==TRUE){$this->slug_key_column=$key;}if(isSet($def['slug_value'])&&$def['slug_value']==TRUE){$this->slug_value_column=$key;}}}public function add($params=array()){if(empty($this->dbh)){return $this;}$sql='';$sql_values='';$data=array();$this->data_types=unserialize(__OBRAY_DATATYPES__);$this->getWorkingDef();if(isSet($this->slug_key_column)&&isSet($this->slug_value_column)&&isSet($params[$this->slug_key_column])){if(isSet($this->parent_column)&&isSet($params[$this->parent_column])){$parent=$params[$this->parent_column];}else{$parent=null;}$params[$this->slug_value_column]=$this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);}forEach($params as $key=>$param){if(isSet($this->table_definition[$key])){$def=$this->table_definition[$key];$data[$key]=$param;$data_type=$this->getDataType($def);if(isSet($this->required[$key])){unset($this->required[$key]);}if(isSet($def['data_type'])&&!empty($this->data_types[$data_type['data_type']]['validation_regex'])&&!preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key])){$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.','500',$key);}if(isSet($def['data_type'])&&$def['data_type']=='password'){$salt='$2a$12$'.$this->generateToken();$data[$key]=crypt($params[$key],$salt);}if(isSet($params[$key])){if(!empty($sql)){$sql.=',';$sql_values.=',';}$sql.=$key;$sql_values.=':'.$key;}}}if(!empty($this->required)){forEach($this->required as $key=>$value){$def=$this->table_definition[$key];$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$key.' is required.':$key.' is required.','500',$key);}}if($this->isError()){$this->throwError(isSet($this->general_error)?$this->general_error:'There was an error on this form, please make sure the below fields were completed correclty: ');return $this;}if(isSet($_SESSION['ouser'])){$ocu=$_SESSION['ouser']->ouser_id;}else{$ocu=0;}$this->sql=' INSERT INTO '.$this->table.' ( '.$sql.', OCDT, OCU ) values ( '.$sql_values.', \''.date('Y-m-d H:i:s').'\', '.$ocu.' ) ';$statement=$this->dbh->prepare($this->sql);$this->script=$statement->execute($data);$this->get(array($this->primary_key_column=>$this->dbh->lastInsertId()));}public function update($params=array()){if(empty($this->dbh)){return $this;}$sql='';$sql_values='';$data=array();$this->data_types=unserialize(__OBRAY_DATATYPES__);$this->getWorkingDef();if(isSet($this->slug_key_column)&&isSet($this->slug_value_column)&&isSet($params[$this->slug_key_column])){if(isSet($this->parent_column)&&isSet($params[$this->parent_column])){$parent=$params[$this->parent_column];}else{$parent=null;}$params[$this->slug_value_column]=$this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);}forEach($params as $key=>$param){if(isSet($this->table_definition[$key])){$def=$this->table_definition[$key];$data[$key]=$param;$data_type=$this->getDataType($def);if(isSet($def['required'])&&$def['required']===TRUE&&(!isSet($params[$key])||$params[$key]===NULL||$params[$key]==='')){$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is required.':$key.' is required.',500,$key);}if(isSet($def['data_type'])&&!empty($this->data_types[$data_type['data_type']]['validation_regex'])&&!preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key])){$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.',500,$key);}if(isSet($def['data_type'])&&$def['data_type']=='password'){$salt='$2a$12$'.$this->generateToken();$data[$key]=crypt($params[$key],$salt);}if(!empty($sql)){$sql.=',';$sql_values.=',';}$sql.=$key.' = :'.$key.' ';}}if(empty($this->primary_key_column)){$this->throwError('Please specify a primary key.','primary_key','500');}if(!isSet($params[$this->primary_key_column])){$this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column);}if($this->isError()){return $this;}if(isSet($_SESSION['ouser'])){$omu=$_SESSION['ouser']->ouser_id;}else{$omu=0;}$this->sql=' UPDATE '.$this->table.' SET '.$sql.', OMDT = \''.date('Y-m-d H:i:s').'\', OMU = '.$omu.' WHERE '.$this->primary_key_column.' = :'.$this->primary_key_column.' ';$statement=$this->dbh->prepare($this->sql);$this->script=$statement->execute($data);$this->get(array($this->primary_key_column=>$params[$this->primary_key_column]));}public function delete($params=array()){if(empty($this->dbh)){return $this;}$original_params=$params;$this->where=$this->getWhere($params,$values);if(empty($this->where)){$this->throwError('Please provide a filter for this delete statement',500);}if(!empty($this->errors)){return $this;}$this->sql=' DELETE FROM '.$this->table.$this->where;$statement=$this->dbh->prepare($this->sql);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$this->script=$statement->execute();}public function get($params=array()){$original_params=$params;$this->table_definition['OCDT']=array('data_type'=>'datetime');$this->table_definition['OMDT']=array('data_type'=>'datetime');$limit='';$order_by='';$filter=TRUE;if(isSet($params['start'])&&isSet($params['rows'])){$limit=' LIMIT '.$params['start'].','.$params['rows'].'';unset($params['start']);unset($params['rows']);unset($original_params['start']);unset($original_params['rows']);}if(isSet($params['filter'])&&($params['filter']=='false'||!$filter)){$filter=FALSE;unset($params['filter']);}if(isSet($params['order_by'])){$order_by=explode('|',$params['order_by']);$columns=array();forEach($order_by as $i=>&$order){$order=explode(':',$order);if(!empty($order)&&array_key_exists($order[0],$this->table_definition)){$columns[]=$order[0];if(count($order)>1){switch($order[1]){case  'ASC':case  'asc':$columns[count($columns)-1].=' ASC ';break;case  'DESC':case  'desc':$columns[count($columns)-1].=' DESC ';break;}}}}if(!empty($columns)){$order_by=' ORDER BY '.implode(',',$columns);}else{$order_by='';}}$withs=array();$original_withs=array();if(!empty($params['with'])){$withs=explode('|',$params['with']);$original_withs=$withs;}$columns=array();forEach($this->table_definition as $column=>$def){if(isSet($def['data_type'])&&$def['data_type']=='password'&&isSet($params[$column])){$password_column=$column;$password_value=$params[$column];unset($params[$column]);}$columns[]=$column;if(array_key_exists('primary_key',$def)){$primary_key=$column;}forEach($withs as $i=>&$with){if(!is_array($with)&&array_key_exists($with,$def)){unset($original_withs[$i]);$name=$with;$with=explode(':',$def[$with]);$with[]=$column;$with[]=$name;}}}if(isSet($original_params['with'])){$original_params['with']=implode('|',$original_withs);}$values=array();$where_str=$this->getWhere($params,$values,$original_params);$this->sql='SELECT '.implode(',',$columns).',OCU,OMU FROM '.$this->table.$where_str.$order_by.$limit;$statement=$this->dbh->prepare($this->sql);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$statement->execute();$statement->setFetchMode(PDO::FETCH_NUM);$this->data=$statement->fetchAll(PDO::FETCH_OBJ);if(!empty($withs)){forEach($withs as&$with){$ids_to_index=array();if(!is_array($with)){break;}$with_key=$with[0];$with_column=$with[2];$with_name=$with[3];$with_components=parse_url($with[1]);$sub_params=array();forEach($this->data as $i=>$data){if(!isSet($ids_to_index[$data->$with_column])){$ids_to_index[$data->$with_column]=array();}$ids_to_index[$data->$with_column][]=(int)$i;}$ids=array();if(count($this->data)<1000){forEach($this->data as $row){$ids[]=$row->$with_column;}}$ids=implode('|',$ids);if(!empty($with_components['query'])){parse_str($with_components['query'],$sub_params);}if(!empty($ids)){$with[0]=$with[0].'='.$ids;}else{$with[0]='';}$sub_params=array_replace($sub_params,$original_params);$new_params=array();parse_str($with[0],$new_params);$sub_params=array_replace($sub_params,$new_params);$with=$this->route($with_components['path'].'get/',$sub_params)->data;forEach($with as&$w){if(isSet($ids_to_index[$w->$with_key])){forEach($ids_to_index[$w->$with_key]as $index){if(!isSet($this->data[$index]->$with_name)){$this->data[$index]->$with_name=array();}array_push($this->data[$index]->$with_name,$w);}}}if($filter){forEach($this->data as $i=>$data){if(empty($data->$with_name)){unset($this->data[$i]);}}$this->data=array_values((array)$this->data);}}}if($this->table=='ousers'){forEach($this->data as $i=>&$data){if(isSet($password_column)&&strcmp($data->$password_column,crypt($password_value,$data->$password_column))!=0){unset($this->data[$i]);}unset($data->ouser_password);}}$this->filter=$filter;$this->recordcount=count($this->data);return $this;}private function getWhere(&$params=array(),&$values=array(),&$original_params=array()){$this->table_definition['OCDT']=array('data_type'=>'datetime');$this->table_definition['OMDT']=array('data_type'=>'datetime');$where=array();$count=0;$p=array();forEach($params as $key=>&$param){$original_key=$key;$operator='=';switch(substr($key,-1)){case '!':case '<':case '>':$operator=substr($key,-1).'=';$key=str_replace(substr($key,-1),'',$key);default:if(empty($params[$key])){$array=explode('~',$key);if(count($array)===2){$param='%'.urldecode($array[1]).'%';$key=$array[0];unset($params[$key]);$operator='LIKE';}$array=explode('>',$key);if(count($array)===2){$param=urldecode($array[1]);$key=$array[0];unset($params[$key]);$operator='>';}$array=explode('<',$key);if(count($array)===2){$param=urldecode($array[1]);$key=$array[0];unset($params[$key]);$operator='<';}}break;}if(array_key_exists($key,$this->table_definition)){if(!is_array($param)){$param=array(0=>$param);}forEach($param as&$param_value){if(empty($where)){$new_key='';}else{$new_key='AND';}$ors=explode('|',$param_value);$where[]=array('join'=>$new_key.' (','key'=>'','value'=>'','operator'=>'');$or_key='';forEach($ors as $v){++$count;$values[]=array('key'=>':'.$key.'_'.$count,'value'=>$v);$where[]=array('join'=>$or_key,'key'=>$key,'value'=>':'.$key.'_'.$count,'operator'=>$operator);$or_key='OR';}$where[]=array('join'=>')','key'=>'','value'=>'','operator'=>'');}}if(!empty($original_params)&&$key=='OMDT'){unset($original_params[$original_key]);}if(!empty($original_params)&&$key=='OCDT'){unset($original_params[$original_key]);}}$where_str='';if(!empty($where)){$where_str=' WHERE ';forEach($where as $key=>$value){$where_str.=' '.$value['join'].' '.$value['key'].' '.$value['operator'].' '.$value['value'].' ';}}return $where_str;}public function dump($params=array()){exec('mysqldump --user='.__OBRAY_DATABASE_USERNAME__.' --password='.__OBRAY_DATABASE_PASSWORD__.' --host='.__OBRAY_DATABASE_HOST__.' '.__OBRAY_DATABASE_NAME__.' '.$this->table.' | gzip > '.dirname(__FILE__).'backups/'.$this->table.'-'.time().'.sql.gz');}private function getDataType($def){if(!isSet($def['data_type'])){return FALSE;}$data_type=explode('(',$def['data_type']);if(!isSet($data_type[1])){$data_type[1]='';}$data_type[1]=str_replace(')','',$data_type[1]);return array('data_type'=>$data_type[0],'size'=>$data_type[1]);}private function getSlug($slug,$column,$parent){$count=1;$i=0;while($count>0){$new_slug=$slug;if($i==0){$appendage='';}else{$appendage=' '.$i;}$params=array('slug'=>strtolower(removeSpecialChars(str_replace('-'.($i-1),'',$new_slug).$appendage,'-','and')));if(!empty($parent)&&isSet($this->parent_column)){$parent_sql=' AND '.$this->parent_column.' = :'.$this->parent_column.' ';$params[$this->parent_column]=$parent;}else{$parent_sql='';}$sql=' SELECT '.$column.' FROM '.$this->table.' WHERE '.$this->slug_value_column.' = :slug '.$parent_sql.' ';$statement=$this->dbh->prepare($sql);$statement->execute($params);$count=count($statement->fetchAll());++$i;}return $params['slug'];}public function getFirst(){if(!isSet($this->data)||!is_array($this->data)){$this->data=array();}forEach($this->data as $i=>$data){$v=&$this->data[$i];return $v;}return reset($this->data);}public function run($sql){$statement=$this->dbh->prepare($sql);$statement->execute();$statement->setFetchMode(PDO::FETCH_OBJ);$this->data=[];while($row=$statement->fetch()){$this->data[]=$row;}return $this;}public function count($params=array()){$values=array();$where_str=$this->getWhere($params,$values);$this->sql='SELECT COUNT(*) as count FROM '.$this->table.' '.$where_str;$statement=$this->dbh->prepare($this->sql);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$statement->execute();while($row=$statement->fetch()){$this->data[]=$row;}$this->data=$this->data[0];unset($this->data[0]);return $this;}public function random($params=array()){if(!empty($params['rows'])&&is_numeric($params['rows'])){$rows=$params['rows'];}else{$rows=1;}$values=array();$where_str=$this->getWhere($params,$values);$statement=$this->dbh->prepare('SELECT * FROM '.$this->table.' '.$where_str.' ORDER BY RAND() LIMIT '.$rows);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$statement->execute();$statement->setFetchMode(PDO::FETCH_NUM);$this->data=$statement->fetchAll(PDO::FETCH_OBJ);return $this;}public function sum($params=array()){$this->math('SUM','sum',$params);}public function average($params=array()){$this->math('AVG','average',$params);}public function maximum($params=array()){$this->math('MAX','maximum',$params);}public function minimum($params=array()){$this->math('MIN','minimum',$params);}private function math($fn,$key,$params=array()){$column=$params['column'];unset($params['column']);if(array_key_exists($column,$this->table_definition)){$values=array();$where_str=$this->getWhere($params,$values);$statement=$this->dbh->prepare('SELECT '.$fn.'('.$column.') as '.$key.' FROM '.$this->table.' '.$where_str);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$statement->execute();while($row=$statement->fetch()){$this->data[]=$row;}$this->data=$this->data[0];unset($this->data[0]);return $this;}else{$this->throwError('Column does not exist.');}}public function unique($params=array()){$column=$params['column'];unset($params['column']);if(array_key_exists($column,$this->table_definition)){$values=array();$where_str=$this->getWhere($params,$values);$statement=$this->dbh->prepare('SELECT DISTINCT '.$column.' FROM '.$this->table.' '.$where_str);forEach($values as $value){if(is_integer($value)){$statement->bindValue($value['key'],trim($value['value']),PDO::PARAM_INT);}else{$statement->bindValue($value['key'],trim((string)$value['value']),PDO::PARAM_STR);}}$statement->execute();while($row=$statement->fetch()){$this->data[]=$row[$column];}return $this;}else{$this->throwError('Column does not exist.');}}protected function log($object,$label=null){if(__OBRAY_DEBUG_MODE__){$sql='CREATE TABLE IF NOT EXISTS ologs ( olog_id INT UNSIGNED NOT NULL AUTO_INCREMENT,olog_label VARCHAR(255),olog_data TEXT,OCDT DATETIME,OCU INT UNSIGNED, PRIMARY KEY (olog_id) ) ENGINE='.__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; ';$statement=$this->dbh->prepare($sql);$statement->execute();}$sql='INSERT INTO ologs(olog_label,olog_data,OCDT,OCU) VALUES(:olog_label,:olog_data,:OCDT,:OCU);';$statement=$this->dbh->prepare($sql);$statement->bindValue('olog_label',$label,PDO::PARAM_STR);$statement->bindValue('olog_data',json_encode($object,JSON_PRETTY_PRINT),PDO::PARAM_STR);$statement->bindValue('OCDT',date('Y-m-d H:i:s'),PDO::PARAM_STR);$statement->bindValue('OCU',isSet($_SESSION['ouser']->ouser_id)?$_SESSION['ouser']->ouser_id:0,PDO::PARAM_INT);$statement->execute();}private function generateToken(){$safe=FALSE;return hash('sha512',base64_encode(openssl_random_pseudo_bytes(128,$safe)));}}Class OUsers extends ODBO{public function __construct(){parent::__construct();$this->table='ousers';$this->table_definition=array('ouser_id'=>array('primary_key'=>TRUE),'ouser_first_name'=>array('data_type'=>'varchar(128)','required'=>FALSE,'label'=>'First Name','error_message'=>'Please enter the user\'s first name'),'ouser_last_name'=>array('data_type'=>'varchar(128','required'=>FALSE,'label'=>'Last Name','error_message'=>'Please enter the user\'s last name'),'ouser_email'=>array('data_type'=>'varchar(128)','required'=>TRUE,'label'=>'Email Address','error_message'=>'Please enter the user\'s email address'),'ouser_permission_level'=>array('data_type'=>'integer','required'=>FALSE,'label'=>'Permission Level','error_message'=>'Please specify the user\'s permission level'),'ouser_status'=>array('data_type'=>'varchar(20)','required'=>FALSE,'label'=>'Status','error_message'=>'Please specify the user\'s status'),'ouser_password'=>array('data_type'=>'password','required'=>TRUE,'label'=>'Password','error_message'=>'Please specify the user\'s password'),'ouser_failed_attempts'=>array('data_type'=>'integer','required'=>FALSE,'label'=>'Failed Logins'),'ouser_last_login'=>array('data_type'=>'datetime','required'=>FALSE,'label'=>'Last Login'));$this->permissions=array('object'=>'any','add'=>1,'get'=>1,'update'=>1,'login'=>'any','logout'=>'any','count'=>1);}public function login($params){if(!isSet($params['ouser_email'])){$this->throwError('Email is required',500,'ouser_email');}if(!isSet($params['ouser_password'])){$this->throwError('Password is required',500,'ouser_password');}if(!$this->isError()){$this->get(array('ouser_email'=>$params['ouser_email'],'ouser_password'=>$params['ouser_password']));if(count($this->data)===1&&$this->data[0]->ouser_failed_attempts<__OBRAY_MAX_FAILED_LOGIN_ATTEMPTS__&&$this->data[0]->ouser_status!='disabled'){$_SESSION['ouser']=$this->data[0];$this->update(array('ouser_id'=>$this->data[0]->ouser_id,'ouser_failed_attempts'=>0,'ouser_last_login'=>date('Y-m-d H:i:s')));}else if(empty($this->data)){$this->get(array('ouser_email'=>$params['ouser_email']));if(count($this->data)===1){$this->update(array('ouser_id'=>$this->data[0]->ouser_id,'ouser_failed_attempts'=>($this->data[0]->ouser_failed_attempts+1)));$this->data=array();}$this->throwError('Invalid login, make sure you have entered a valid email and password.');}else if($this->data[0]->ouser_failed_attempts>__OBRAY_MAX_FAILED_LOGIN_ATTEMPTS__){$this->throwError('This account has been locked.');}else if($this->data[0]->ouser_status==='disabled'){$this->throwError('This account has been disabled.');}else{$this->get(array('ouser_email'=>$params['ouser_email']));if(count($this->data)===1){$this->update(array('ouser_id'=>$this->data[0]->ouser_id,'ouser_failed_attempts'=>($this->data[0]->ouser_failed_attempts+1)));}$this->throwError('Invalid login, make sure you have entered a valid email and password.');}}}public function logout($params){unset($_SESSION['ouser']);}public function authorize($params=array()){if(!isSet($_SESSION['ouser'])){$this->throwError('Forbidden',403);}else if(isSet($params['level'])&&$params['level']<$_SESSION['ouser']->ouser_permission_level){$this->throwError('Forbidden',403);}}public function hasPermission($object){if(isSet($this->permissions[$object])&&$this->permissions[$object]==='any'){return TRUE;}else{return FALSE;}}}Class ORouter extends OObject{public function route($path,$params=array(),$direct=FALSE){$start_time=microtime(TRUE);if(defined('__TIMEZONE__')){date_default_timezone_set(__TIMEZONE__);}$obj=parent::route($path,$params,$direct);$status_codes=array(200=>'OK',201=>'Created',202=>'Accepted',203=>'Non-Authoritative Information',204=>'No Content',205=>'Reset Content',206=>'Partial Content',300=>'Multiple Choices',301=>'Moved Permanently',302=>'Found',303=>'See Other',304=>'Not Modified',305=>'Use Proxy',307=>'Temporary Redirect',400=>'Bad Request',401=>'Unauthorized',402=>'Payment Required',403=>'Forbidden',404=>'Not Found',405=>'Method Not Allowed',406=>'Not Acceptable',407=>'Proxy Authentication Required',408=>'Request Timeout',409=>'Conflict',410=>'Gone',411=>'Length Required',412=>'Precondition Failed',413=>'Request Entity Too Large',414=>'Request-URI Too Long',415=>'Unsupported Media Type',416=>'Requested Range Not Satisfiable',417=>'Expectation Failed',500=>'Internal Server Error',501=>'Not Implemented',502=>'Bad Gateway',503=>'Service Unavailable',504=>'Gateway Timeout',505=>'HTTP Version Not Supported');if($obj->getStatusCode()==401){header('WWW-Authenticate: Basic realm="'.__APP__.'"');}$content_type=$obj->getContentType();if(!headers_sent()){header('HTTP/1.1 '.$obj->getStatusCode().' '.$status_codes[$obj->getStatusCode()]);}if(!headers_sent()){header('Content-Type: '.$content_type);}$obj->cleanUp();switch($content_type){case  'application/json':$obj->runtime=(microtime(TRUE)-$start_time)*1000;$json=json_encode($obj,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);if($json){echo $json;}else{echo 'There was en error encoding JSON.';}break;case  'text/html':$obj->runtime=(microtime(TRUE)-$start_time)*1000;if(!headers_sent()){header('Server-Runtime: '.$obj->runtime.'ms');}echo $obj->html;break;case  'application/xml':break;}return $obj;}}$router=new ORouter();$router->route($_SERVER["REQUEST_URI"]); ?>