<?php
namespace app\modules;

use php\framework\Logger;
use php\format\JsonProcessor;
use app\forms\vkCaptcha;
use php\gui\UXApplication;
use php\gui\UXDialog;
use php\io\Stream;
use php\lang\Thread;
use php\lib\Str;
use php\lib\arr; 
use php\lib\bin; 
use php\lib\char; 
use php\lib\fs; 
use php\lib\str; 
use php\lib\num; 
use php\lib\reflect; 
use php\io\File; 
use php\io\IOException; 
use php\io\FileStream; 
use php\io\MemoryStream; 
use php\io\ResourceStream; 
use php\net\NetStream; 
use php\util\Flow; 
use php\util\Locale; 
use php\util\Regex; 
use php\util\Configuration; 
use php\time\Time; 
use php\time\TimeZone; 
use php\time\TimeFormat; 
use php\net\URL; 
use php\net\Socket; 
use php\net\SocketException; 
use php\net\ServerSocket; 
use php\net\Proxy; 
use php\lang\Environment; 
use php\lang\Process; 
use php\lang\System; 
use php\lang\ThreadGroup; 
use php\lang\ThreadPool; 
use facade\Json; 
use php\gui\UXNode; 
use php\gui\event\UXEvent; 
use php\gui\animation\UXAnimationTimer; 
use php\gui\layout\UXHBox; 
use php\gui\layout\UXAnchorPane; 
use php\gui\UXClipboard; 
use php\gui\paint\UXColor; 
use php\gui\event\UXContextMenuEvent; 
use php\gui\text\UXFont; 
use php\gui\UXGeometry; 
use php\gui\UXImage; 
use php\gui\UXMedia; 
use php\gui\UXMenu; 
use php\gui\UXMenuItem; 
use php\gui\UXButton; 
use php\gui\UXTooltip; 
use php\gui\UXToggleButton; 
use php\gui\UXToggleGroup; 
use php\gui\UXImageView; 
use php\gui\UXImageArea; 
use php\gui\UXSlider; 
use php\gui\UXSpinner; 
use php\gui\layout\UXVBox; 
use php\gui\UXTitledPane; 
use php\gui\layout\UXPanel; 
use php\gui\layout\UXFlowPane; 
use php\gui\UXForm; 
use php\gui\UXWindow; 
use ide\bundle\std\UXAlert; 
use php\gui\UXContextMenu; 
use php\gui\UXControl; 
use php\gui\UXDirectoryChooser; 
use php\gui\UXFileChooser; 
use php\gui\UXFlatButton; 
use php\gui\UXHyperlink; 
use php\gui\UXList; 
use php\gui\UXListView; 
use php\gui\UXComboBox; 
use php\gui\UXChoiceBox; 
use php\gui\UXLabel; 
use php\gui\UXLabelEx; 
use php\gui\UXLabeled; 
use php\gui\UXListCell; 
use php\gui\UXMediaPlayer; 
use php\gui\UXParent; 
use php\gui\UXPopupWindow; 
use php\gui\UXPasswordField; 
use php\gui\UXProgressIndicator; 
use php\gui\UXProgressBar; 
use php\gui\UXTab; 
use php\gui\UXTabPane; 
use php\gui\UXTreeView; 
use php\gui\UXTrayNotification; 
use php\gui\UXWebEngine; 
use php\gui\UXWebView; 
use php\gui\UXCell; 
use php\gui\UXColorPicker; 
use php\gui\UXCanvas; 
use php\gui\layout\UXStackPane; 
use php\gui\layout\UXPane; 
use php\gui\layout\UXScrollPane; 
use php\game\event\UXCollisionEvent; 
use php\gui\event\UXKeyEvent; 
use php\gui\event\UXDragEvent; 
use php\gui\event\UXMouseEvent; 
use php\gui\event\UXWebEvent; 
use php\gui\event\UXWindowEvent; 
use php\gui\framework\AbstractForm; 
use php\gui\framework\AbstractModule; 
use action\Animation; 
use action\Collision; 
use game\Jumping; 
use action\Element; 
use action\Geometry; 
use action\Media; 
use action\Score; 

class vkModule {
	
	const LOG = true;
	private static	// Если есть своё приложение, поменяйте параметры $appID и $appSecret
					$appID = '5119526',
					$appSecret = 'QFWVrezg1DAypE6vCqFj',
					
					// Файл, в котором хранится access_token
					$tokenFile = './cache.vk',
					$accessToken = 'false',
					$apiVersion = '5.53',

					// Конфиги для загрузки фото
					$uploadImageConfig = [
						'photos.save' => ['method' => 'photos.getUploadServer', 'upload' => ['album_id', 'group_id'], 'POST' => 'file1'],
						'photos.saveWallPhoto' => ['method' => 'photos.getWallUploadServer', 'upload' => ['group_id'], 'POST' => 'photo'],
						'photos.saveOwnerPhoto' => ['method' => 'photos.getOwnerPhotoUploadServer', 'upload' => ['owner_id'], 'POST' => 'photo'], // * //
						'photos.saveMessagesPhoto' => ['method' => 'photos.getMessagesUploadServer', 'upload' => [], 'POST' => 'photo'],
						'messages.setChatPhoto' => ['method' => 'photos.getChatUploadServer', 'upload' => ['chat_id'], 'POST' => 'file'], // * //
					];
	private function Log($data){
        if(self::LOG) Logger::Debug('[VK] ' . var_export($data, true));
    }

	private static $longPoll, $lpAbort = false;
	public static function longPollConnect($callback, $params = false){
		if(!$params) return self::Query('messages.getLongPollServer', ['use_ssl' => 1, 'need_pts' => 1], function($answer) use ($callback){
			self::$lpAbort = false;
			return self::longPollConnect($callback, $answer['response']);
		});

		self::log(['longPollConnect' => $params]);

		$func = function() use ($params, $callback){
			self::Query(null, [], function($answer) use ($params, $callback){
				if(self::$lpAbort === true){
					self::$lpAbort = false;
					return;
				} 

				if(isset($answer['failed'])) return self::longPollConnect($callback, false); 

				UXApplication::runLater(function() use ($callback, $answer){
					$callback($answer['updates']);
				});

				$params['ts'] = $answer['ts'];
				return self::longPollConnect($callback, $params);
			}, 
			[
				'url' => 'https://'.$params['server'].'?act=a_check&key='.$params['key'].'&ts='.$params['ts'].'&wait=25&mode=2',
				'connectTimeout' => 10000,
				'readTimeout' => 35000
			]);
		};

		self::$longPoll = new Thread($func);
		self::$longPoll->start();
	}

	public static function longPollDisconnect(){
		if(self::$longPoll instanceof Thread and !self::$longPoll->isInterrupted()) self::$longPoll->interrupt();
		self::$longPoll = null;
		self::$lpAbort = true;
	}

	/**
	 * Загрузка и сохранение изображения на сервере ВК
	 * (Если передан параметр $callback, запрос будет выполнен асинхронно)
	 *
	 * @param string $method - метод вк (photos.save, photos.saveWallPhoto, photos.saveOwnerPhoto, photos.saveMessagesPhoto, messages.setChatPhoto)
	 * @param string $file - путь к загружаемому изображению
	 * @param string $filepath - путь к загружаемому файлу
	 * @param array $params - параметры для загрузки
	 * @param callable $callback - функция, которая будет вызвана по окончанию запроса
	 **/
	public static function uploadImage($method, $file, $params = [], $callback = false, $jParams = []){
		if(!isset(self::$uploadImageConfig[$method])) return false;
		$config = self::$uploadImageConfig[$method];

		$thread = new Thread(function() use ($config, $method, $file, $params, $callback, $jParams){
			// Step 1 - get upload server
			$usMethod = $config['method'];
			$usParams = [];
			
			foreach($params as $k=>$v){
				if(in_array($k, $config['upload'])){
					$usParams[$k] = $v;
				}
			}

			$server = self::Query($usMethod, $usParams)['response'];

			// Step 2 - upload file
			$uResult = self::Upload($server['upload_url'], $config['POST'], $file);

			// Step 3 - save uploaded file
			$save = self::Query($method, array_merge($params, $uResult));

			if(is_callable($callback))$callback($save);
		});

		$thread->start();
	}

	/**
	 * Загрузка файла на сервер ВК
	 *
	 * @param string $server - сервер, куда будет загружен файл
	 * @param string $field - имя поля
	 * @param string $filepath - путь к загружаемому файлу
	 * @param callable $callback - функция, которая будет вызвана по окончанию запроса
	 **/
	public static function Upload($server, $field, $filepath, $callback = false){
		$uploadParams = [
			'url' => $server,
			'postFiles' => [$field => $filepath]
		];
		return self::Query('none', [], $callback, $uploadParams);
	}

	/**
	 * Выполнение запроса. (Если передан параметр $callback, запрос будет выполнен асинхронно)
	 *
	 * @param string $method - метод VK API https://vk.com/dev/methods
	 * @param array $params - массив с параметрами
	 * @param callable $callback=false - функция, которая будет вызвана по окончанию запроса
	 * 
	 * @example vkModule::Query('users.get', ['fields'=>'photo_100'], function($answer){ });
	 **/
	public static function Query($method, $params = [], $callback = false, $jParams = [])
	{		
		$params['v'] = self::$apiVersion;
						
		if(self::$accessToken){
			$params['access_token'] = self::$accessToken;
		}
						
		$url = 'https://api.vk.com/method/'.$method.'?'.http_build_query($params);

		$connect = new jURL($url);
		$connect->setOpts($jParams);
		if(is_callable($callback)){
			$connect->asyncExec(function($content, $connect) use ($method, $params, $callback, $jParams){
				$result = self::processResult($content, $connect, $method, $params, $callback, $jParams);
				if($result !== false) $callback($result);
			});
		} else {
			$content = $connect->exec();
			return self::processResult($content, $connect, $method, $params, $callback, $jParams);
		}
	}

	private static function processResult($content, $connect, $method, $params, $callback, $jParams){
		try {
			$errors = $connect->getError();
			if($errors !== false){
				throw new vkException('Невозможно совершить запрос', -1, $errors);
			}

			$json = new JsonProcessor(JsonProcessor::DESERIALIZE_AS_ARRAYS);
			$data = $json->parse($content);

			self::log([$url=>$data]);
				 
						
			if(isset($data['error'])){
				throw new vkException($data['error']['error_msg'], $data['error']['error_code'], $data);
				return false;
			}

			return $data;
	    	
		}catch(vkException $e){
			UXApplication::runLater(function () use ($e, $method, $params, $callback, $jParams) {
				switch($e->getCode()){
					//api.vk.com недоступен, обычно из-за частых запросов
					case -2:
						wait(500);
						
					break;	
					
					case 5://Просроченный access_token
					case 10://Ошибка авторизации
						UXDialog::show('Вам необходимо повторно авторизоваться', 'ERROR');
						self::logout();
						return self::checkAuth(function(){
							self::Query($method, $params, $callback, $jParams);
						});
					break;	
						//Нужно ввести капчу
					case 14:
						$result = $e->getData();

						$vkCaptcha = app()->getForm('vkCaptcha');
						$vkCaptcha->setUrl($result['error']['captcha_img']);
        				$vkCaptcha->showAndWait();

						$params['captcha_sid'] = $result['error']['captcha_sid'];
						$params['captcha_key'] = $vkCaptcha->input->text;
					break;	

					default:
						return UXDialog::show('Ошибка VK API: '.$e->getMessage().' (code='.$e->getCode().')' . "\n\n\nDebug: " . var_export($e->getData(), true), 'ERROR');
				}

				return self::Query($method, $params, $callback, $jParams);
			
    		});
    	}

    	return false;
	}
	
	/**
	 * Проверяет, авторизован ли текущий пользователь (есть ли сохраненный access_token)
	 * + автоматически "подбирает" сохранённый access_token
	 *
	 * @return bool
	 **/	 
	public static function isAuth()
	{
		if(file_exists(self::$tokenFile) and $t = file_get_contents(self::$tokenFile) and Str::Length($t) > 85){
			$token = str::sub($t, 0, 85);
			$hash = str::sub($t, 85);

			if(self::getHash($token) == $hash){
				self::$accessToken = $token;
				return true;
			} else {
				var_dump('invalid hash', [self::getHash($token), $t, $token, $hash]);
			}
		}

		var_dump('invalid', [file_exists(self::$tokenFile), $t = file_get_contents(self::$tokenFile), Str::Length($t) > 85]);
		return false;
	}

	/**
	 * Проверяет, авторизован ли пользователь, если нет - покажет форму авторизации
	 **/
	public static function checkAuth($callback = false){
		$callback = is_callable($callback) ? $callback : function(){};

		if(!self::isAuth()){
			app()->getForm('vkAuth')->setCallback($callback);
			app()->getForm('vkAuth')->showAndWait();
		}
		else $callback();
	}

	/**
	 * Деавторизирует пользователя, удаляет access_token
	 */
	public static function logout(){
		self::$accessToken = false;
		unlink(self::$tokenFile);
	}
	
	/**
	 * Возвращает ID приложения
	 * @return string
	 */
	public static function getAppID(){
		return self::$appID;
	}

	/**
	 * Возвращает версию API
	 * @return string
	 */
	public static function getApiVersion(){
		return self::$apiVersion;
	}

	/**
	 * Устанавливает access_token и сохраняет его в файл
	 * @return string
	 */
	public static function setAccessToken($aToken){
		self::$accessToken = $aToken;
		file_put_contents(self::$tokenFile, $aToken . self::getHash($aToken));
	}

	private static function getHash($str){
		return str::hash($str . self::$appID . self::$appSecret, 'SHA-1');
	}
}


class vkException extends \Exception{
	private $data;
	public function getData(){
		return $this->data;
	}
		
	public function __construct($message = null, $code = 0, $data = []){
		$this->data = $data;
		return parent::__construct($message, $code, null);
	}
	
}

if(!function_exists('http_build_query')){
	function http_build_query($a,$b='',$c=0)
     {
            if (!is_array($a)) return false;
            foreach ((array)$a as $k=>$v)
            {
                if ($c)
                {
                    if( is_numeric($k) )
                        $k=$b."[]";
                    else
                        $k=$b."[$k]";
                }
                else
                {   if (is_int($k))
                        $k=$b.$k;
                }

                if (is_array($v)||is_object($v))
                {
                    $r[]=http_build_query($v,$k,1);
                        continue;
                }
                $r[]=urlencode($k)."=".urlencode($v);
            }
            return implode("&",$r);
        	}
}
