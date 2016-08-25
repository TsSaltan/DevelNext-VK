<?php
namespace app\forms;

use php\gui\framework\AbstractForm;
use php\gui\event\UXWindowEvent; 
use php\gui\event\UXEvent; 
use php\gui\event\UXMouseEvent; 
use php\gui\UXWebView;
use php\gui\UXDialog;
use app\modules\vkModule as VK;
use php\lib\arr; 
use php\lib\bin; 
use php\lib\char; 
use php\lib\fs; 
use php\lib\str; 
use php\lib\num; 
use php\lib\reflect; 
use php\io\Stream; 
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
use php\lang\Thread; 
use php\lang\Environment; 
use php\lang\Process; 
use php\lang\System; 
use php\lang\ThreadGroup; 
use php\lang\ThreadPool; 
use php\format\JsonProcessor; 
use facade\Json; 
use php\gui\UXNode; 
use php\gui\UXApplication; 
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
use php\gui\UXCell; 
use php\gui\UXColorPicker; 
use php\gui\UXCanvas; 
use php\gui\layout\UXStackPane; 
use php\gui\layout\UXPane; 
use php\gui\layout\UXScrollPane; 
use php\game\event\UXCollisionEvent; 
use php\gui\event\UXKeyEvent; 
use php\gui\event\UXDragEvent; 
use php\gui\event\UXWebEvent; 
use php\gui\framework\AbstractModule; 
use action\Animation; 
use action\Collision; 
use game\Jumping; 
use action\Element; 
use action\Geometry; 
use action\Media; 
use action\Score; 
use php\framework\Logger; 

/**
 * Авторизация пользователя через встроенный компонент браузер
 **/
 
class vkAuth extends AbstractForm
{
    private $callback;

    function setCallback($callback){
        $this->callback = $callback;
    }
    /**
     * @event show 
     **/
    function doShow($event = null)
    {    
        $url = 'https://oauth.vk.com/authorize?'.
                'v='.VK::getApiVersion().'&'.
                'client_id='.VK::getAppID().'&'.
                'display=popup'.'&'.
                'redirect_uri=https://oauth.vk.com/blank.html'.'&'.
                'response_type=token'.'&'.
                'scope=friends,notify,photos,audio,video,docs,notes,pages,status,wall,groups,messages,offline'; // Всевозможные права

        $browser = new UXWebView;

        $browser->engine->watchState(function($self, $old, $new) use ($browser){
            switch($new){
                case 'RUNNING':
                    $this->showPreloader('Загрузка');
                break;

                case 'SUCCEEDED':
                    $this->hidePreloader();
                break;
            }

            $hash = parse_url($browser->engine->location);
            if($hash!== false and isset($hash['fragment'])){
                        
                $data = parse_str($hash['fragment']);
                        
                if(isset($data['access_token'])){
                    VK::setAccessToken($data['access_token']);
                    $browser->free();        
                    $this->hide();
                    call_user_func($this->callback);
                }
            }elseif($hash === false){
                //Если пользователь нажал отмена
                UXDialog::Show("Произошла ошибка во время авторизации. \r\nПопробуйте снова. В появившемся окне нажмите кнопку \"Разрешить\"", 'ERROR');
                            
                $browser->free();
                $this->doShow();
            }            
        });        

        
        $browser->engine->load($url);

        $browser->anchorFlags['left'] = true;
        $browser->anchorFlags['right'] = true;
        $browser->anchorFlags['top'] = true;
        $browser->anchorFlags['bottom'] = true;

        $browser->leftAnchor = 0;
        $browser->rightAnchor = 0;
        $browser->topAnchor = 0;
        $browser->bottomAnchor = 0;
        
        $this->add($browser);
    }

    /**
     * @event close 
     **/
    function doClose(UXWindowEvent $event = null)
    {    
        VK::setAccessToken(false);
        app()->shutdown();
    }



}

if(!function_exists('parse_str')){
    function parse_str($str) 
    {
      # result array
      $arr = array();
        
      # split on outer delimiter
      $pairs = explode('&', $str);
        
      # loop through each pair
      foreach ($pairs as $i) {
        # split into name and value
        list($name,$value) = explode('=', $i, 2);
        
        # if name already exists
        if( isset($arr[$name]) ) {
          # stick multiple values into an array
          if( is_array($arr[$name]) ) {
            $arr[$name][] = $value;
          }
          else {
            $arr[$name] = array($arr[$name], $value);
          }
        }
        # otherwise, simply stick it in a scalar
        else {
          $arr[$name] = $value;
        }
      }
      
      return $arr;
    }
}
