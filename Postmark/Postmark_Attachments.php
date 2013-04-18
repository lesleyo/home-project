<?php


Class Postmark_Attachments extends Postmark_Inbound  implements Iterator {

    public function __construct($attachments)
    {
        $this->attachments = $attachments;
        $this->position = 0;
    }

    function get($key) {
        $this->position = $key;

        if( ! empty($this->attachments[$key]))
        {
            return new Postmark_Attachment($this->attachments[$key]);
        }
        else
        {
            return FALSE;
        }
    }

    function rewind()
    {
        $this->position = 0;
    }

    function current()
    {
        return new Postmark_Attachment($this->attachments[$this->position]);
    }

    function key()
    {
        return $this->position;
    }

    function next()
    {
        ++$this->position;
    }

    function valid()
    {
        return isset($this->attachments[$this->position]);
    }

}