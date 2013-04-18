<?php

Class Postmark_Attachment extends Postmark_Inbound {

    public function __construct($attachment)
    {
        $this->Attachment = $attachment;
        $this->Name = $this->Attachment->Name;
        $this->ContentType = $this->Attachment->ContentType;
        $this->ContentLength = $this->Attachment->ContentLength;
        $this->Content = $this->Attachment->Content;
    }

    private function _read()
    {
        return base64_decode(chunk_split($this->Attachment->Content));
    }
    
    public function Download($file_path)
    {
        file_put_contents($file_path, $this->_read());
    }
}