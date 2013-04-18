<?php

include('Postmark_Exception.php');
include('Postmark_Attachments.php');
include('Postmark_Attachment.php');

/**
 * This is a simple API wrapper for Postmark Inbound Hook (http://developer.postmarkapp.com/developer-inbound.html)
 *  
 * @package    PostmarkInbound
 * @author     Joffrey Jaffeux
 * @copyright  2012 Joffrey Jaffeux
 * @license    MIT License
 * @example    $inbound = new \Postmark\Inbound(file_get_contents('php://input'));
 * @example    $inbound = new \Postmark\Inbound(file_get_contents('/path/to/Json')); 
 */
class Postmark_Inbound {

    public $Json;
    public $Source;

    public function __construct($Json = FALSE)
    {
        if(empty($Json))
        {
            throw new InboundException('Posmark Inbound Error: you must provide a Json Source');
        }

        $this->Json = $Json;
        $this->Source = $this->_jsonToArray();
    }

    private function _jsonToArray()
    {
        $Source = Json_decode($this->Json, FALSE);
        /*switch (json_last_error()) //damn php 5.2!
        {
            case JSON_ERROR_NONE:
                return $Source;
            break;
            default:
                throw new InboundException('Posmark Inbound Error: Json format error');
            break;
        }*/
        return $Source;
    }

    public function __call($name, $arguments)
    {
        return ($this->Source->$name) ? $this->Source->$name : FALSE;
    }

    public function ToEmail()
    {
        return $this->Source->To;
    }

    public function FromEmail()
    {
        return $this->Source->FromFull->Email;
    }

    public function FromFull()
    {
        return $this->Source->FromFull->Name . ' <' . $this->Source->FromFull->Email . '>';
    }

    public function FromName()
    {
        return $this->Source->FromFull->Name;
    }

    public function Headers($name = 'X-Spam-Status')
    {
        foreach($this->Source->Headers as $header)
        {
            if(isset($header->Name) AND $header->Name == $name)
            {
                if($header->Name == 'Received-SPF')
                {
                    return self::_parseReceivedSpf($header->Value);
                }

                return $header->Value;
            }
            else
            {
                unset($header);
            }
        }

        return $header ? $header : FALSE;
    }

    private static function _parseReceivedSpf($header)
    {
        preg_match_all('/^(\w+\b.*?){1}/', $header, $matches);
        return strtolower($matches[1][0]);
    }

    public function Recipients()
    {
        return self::_parseRecipients($this->Source->ToFull);
    }

    public function UndisclosedRecipients()
    {
        return self::_parseRecipients($this->Source->CcFull);
    }

    private static function _parseRecipients($recipients)
    {
        $objects = array_map(array($object, '_parseRecipientsMap'), $recipients);
        return $objects;
    }
    
    public static function _parseRecipientsMap($object)
    {
      
    }

    public function Attachments()
    {
        return new Postmark_Attachments($this->Source->Attachments);
    }

    public function HasAttachments()
    {
        return count($this->Source->Attachments) > 0 ? TRUE : FALSE;
    }

}