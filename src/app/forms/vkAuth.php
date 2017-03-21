<?php
namespace app\forms;

use php\gui\framework\AbstractForm;
use php\gui\event\UXWindowEvent; 
use php\gui\event\UXEvent; 
use php\gui\event\UXMouseEvent; 
use php\gui\UXWebView;
use php\gui\UXDialog;
use app\modules\vkModule as VK;

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
